<?php

namespace App\Models;

use App\Helpers\BillingTrait;
use App\Helpers\Misc;
use Auth;
use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Venturecraft\Revisionable\RevisionableTrait;

class App extends BaseModel
{
    const APP_KEYS_EXPIRE_DAYS = 5;
    use RevisionableTrait, SoftDeletes, BillingTrait;

    protected $table = 'app';

    protected $fillable = [
        'name',
        'alias',
        'email',
        'password',
        'account_id',
        'presence',
        'tech_prefix'
    ];

    public function developer()
    {
        return $this->belongsTo('App\User', 'account_id');
    }

    public function users()
    {
        return $this->hasMany('App\Models\AppUser', 'app_id');
    }

    public function conferences()
    {
        return $this->hasMany('App\Models\Conference', 'app_id');
    }

    public function queues()
    {
        return $this->hasMany('App\Models\Queue', 'app_id');
    }

    public function keys()
    {
        return $this->hasMany('App\Models\AppKey', 'app_id');
    }

    public function did()
    {
        return $this->hasMany('App\Models\DID', 'app_id')->whereNull('deleted_at');
    }

    public function createApp($attributes, $user = null)
    {
        $user = $user ?: Auth::user();
        $user = DB::table('accounts')->select([
            'email',
            'password',
            'id AS account_id'
        ])->find($user->id);
        $this->fill((array)$user);
        $this->name        = $attributes['name'];
        $this->alias       = $attributes['alias'];
        $this->tech_prefix = Misc::generateUniqueId(99999, 'tech_prefix', 'app');
        $appKey            = new AppKey();
        $expireDays        = self::APP_KEYS_EXPIRE_DAYS;

        return ($this->save() and $appKey->generateKeys($this, $expireDays));
    }

    public static function getApps($fields = [])
    {
        $user = Auth::user();
        $apps = App::whereAccountId($user->id);
        if ($fields)
            $apps->select($fields);

        return $apps;
    }

    public static function generateDashboardAppMenu($activeApp = '')
    {
        $apps = App::getApps()->get();
        $html = '';

        foreach ($apps as $app) {
            $html .= sprintf("
            <li class=\" %3\$s\">
                <a href=\"%1\$s\" title=\"%2\$s\">
                    <span>%2\$s</span>
                </a>
            </li>", url('app/dashboard/?app=' . $app->id),
                $app->name,
                $app->id == $activeApp ? "active" : '');
        }

        return $html;

    }

    public function getDailyUsage()
    {

        $resource   = $this->getResourceByAliasFromBillingDB($this->getAppAlias());
        $dailyUsage = new Collection();
        if ($resource)
            $dailyUsage = $this->getDailyUsageFromBillingDB($resource->resource_id);

        return $dailyUsage;

    }

    public function createDidResource()
    {
        return $this->insertGetIdToBillingDB("
                              insert into resource
                              (alias,ingress,active)
                              values (?,'t','t')
                              RETURNING resource_id",
            ["{$this->getAppAlias()}_DID"],
            'resource_id');
    }

    /**
     * App menu config
     * @return array
     */
    public function getManageAppMenu()
    {
        return [
            [
                'name'    => 'APP Info',
                'icon'    => 'icon-drawer',
                'url'     => 'app-data',
                'subMenu' => [
                    [
                        'name' => 'View APP CDR',
                        'icon' => 'icon-call-out',
                        'url'  => 'app-cdr/index',
                    ],
                    [
                        'name' => 'Daily usage',
                        'icon' => 'icon-calculator',
                        'url'  => 'app/daily-usage',
                    ],
                ]
            ],
            [
                'name'    => 'Finance',
                'icon'    => 'fa fa-money',
                'url'     => 'app-rates',
                'subMenu' => [
                    [
                        'name' => 'Sell rates',
                        'icon' => 'fa fa-phone',
                        'url'  => 'app-rates',
                    ]
                ]
            ],
            [
                'name'    => 'PBX',
                'icon'    => 'fa fa-phone',
                'url'     => 'app-pbx',
                'subMenu' => [
                    [
                        'name' => 'Conference log',
                        'icon' => 'fa fa-file-text',
                        'url'  => 'conferences/log',
                    ],
                    [
                        'name' => 'Agent session log',
                        'icon' => 'fa fa-file-text-o',
                        'url'  => 'pbx/agent-log',
                    ],
                    [
                        'name' => 'Caller session log',
                        'icon' => 'fa fa-file-text-o',
                        'url'  => 'pbx/caller-log',
                    ]
                ]
            ],
            [
                'name' => 'SMS',
                'icon' => 'icon-speech',
                'url'  => 'sms/inbox'
            ],
            [
                'name' => 'SIP Accounts',
                'icon' => 'icon-earphones',
                'url'  => 'app-users/sip'
            ],
            [
                'name'       => 'API keys',
                'icon'       => 'icon-key',
                'url'        => 'app-keys/index',
                'labelCount' => 'keys'
            ],
            [
                'name'       => 'Manage DID',
                'icon'       => 'fa fa-phone-square',
                'url'        => 'did/index',
                'labelCount' => 'did'
            ],
            [
                'name'       => 'Conferences',
                'icon'       => 'fa fa-list',
                'url'        => 'conferences/index',
                'labelCount' => 'conferences'
            ],
            [
                'name'       => 'Queues',
                'icon'       => 'fa fa-list',
                'url'        => 'queues/index',
                'labelCount' => 'queues'
            ],
            [
                'name'       => 'Users',
                'icon'       => 'icon-user',
                'url'        => 'app-users/index',
                'labelCount' => 'users'
            ],
        ];
    }

    public function deleteAppFromBilling()
    {
        $resource = $this->getResourceByAliasFromBillingDB($this->getAppAlias());
        if ($resource) {
            $this->getFluentBilling('resource')->whereAlias($this->alias)->delete();
            $this->getFluentBilling('route_strategy')->whereName($this->name)->delete();
        }
    }

    public function getAppAlias()
    {
        return $this->tech_prefix;
    }

    public function getCDR()
    {
        $fields = [
            'session_id',
            'start_time_of_date',
            'release_tod',
            'ani_code_id',
            'dnis_code_id',
            'call_duration',
            'agent_rate',
            'agent_cost',
            'origination_source_number',
            'origination_destination_number',
            'resource.alias'
        ];

        $resource = $this->getResourceByAliasFromBillingDB($this->getAppAlias());
        $cdr      = $this->getFluentBilling('client_cdr')->select($fields)
            ->whereEgressClientId($resource->resource_id)
            ->leftJoin('resource', 'ingress_client_id', '=', 'resource_id');

        return $cdr;
    }
}
