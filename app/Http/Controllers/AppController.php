<?php 

namespace App\Http\Controllers;

use App\Helpers\BillingTrait;
use App\Helpers\PlaySMSTrait;
use App\Http\Requests\AppRequest;
use App\Http\Requests\DeleteRequest;
use App\Jobs\StoreAPPToBillingDB;
use App\Jobs\StoreAPPToChatServer;
use App\Models\App;
use App\Http\Requests;
use App\Models\AppUser;
use Illuminate\Http\Request;
use URL;
use Yajra\Datatables\Datatables;

class AppController extends AppBaseController
{
    use BillingTrait, PlaySMSTrait;

    /**
     * AppController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        parent::__construct($request);
        $this->middleware('auth');
        $this->middleware('csrf');
        $this->middleware('role:developer', [
            'except' => [
                'getEdit',
                'postEdit',
                'getDelete',
                'postDelete'
            ]
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getIndex()
    {
        return redirect('app/list');
    }

    public function getList()
    {
        $title    = 'APP List';
        $subtitle = 'Manage APP';

        return view('app.index', compact('title', 'subtitle'));
    }

    public function getData()
    {
        $apps = App::getApps([
            'id',
            'tech_prefix',
            'name',
            'presence'
        ]);

        return Datatables::of($apps)
            ->add_column('users', function ($app) {
                $users = $app->users;

                return count($users->all());
            })
            ->add_column('actions', function ($app) {
                return $app->getDefaultActionButtons('app', [], ['delete']);
            })
            ->add_column('daily_active', function ($app) {
                return '';
            })
            ->add_column('weekly_active', function ($app) {
                return '';
            })
            ->add_column('monthly_active', function ($app) {
                return '';
            })
            ->edit_column('presence', function ($app) {
                $icon = $app->presence ? 'fa fa-check' : 'fa fa-remove';

                return '<em class="' . $icon . '"></em>';
            })
            ->setRowId('id')
            ->make(true);
    }

    public function getDashboard()
    {
        $APP      = $this->app;
        $title    = 'APP Dashboard: ' . $APP->name;
        $subtitle = 'Manage APP';

        return view('app.dashboard', compact('title', 'subtitle', 'APP'));
    }

    public function getCreate()
    {
        $title    = 'Create new APP';
        $statuses = AppUser::getUserStatuses();

        return view('app/create_edit', compact('title', 'statuses'));
    }

    public function getCheckBilling()
    {
        $currencyId = $this->getCurrencyIdFromBillingDB();
        $clientId   = $this->getCurrentUserIdFromBillingDB();

        return ['currencyId' => $currencyId, 'currentClientId' => $clientId];
    }

    public function postCreate(AppRequest $request)
    {
        $result = $this->getResult(true, 'Could not create APP');
        if (App::whereName($request->name)->whereAccountId(\Auth::user()->id)->first())
            return $this->getResult(true, 'App with the same name already exists');
        $app = new App();
        if ($app->createApp($request->input())) {
            $result = $this->tryToStoreInBillingDB($app);
            if ($result['error'])
                $app->delete();
        }

        return $result;
    }

    public function getEdit($id)
    {
        $title           = 'Edit APP';
        $model           = App::find($id);
        $statuses        = AppUser::getUserStatuses();
        $model->presence = (int)$model->presence;

        return view('app/create_edit', compact('title', 'model', 'statuses'));
    }

    public function postEdit(AppRequest $request, $id)
    {
        $result = $this->getResult(true, 'Could not edit APP');
        $model  = App::find($id);
        if ($model->fill($request->input())
            and $model->save()
        )
            $result = $this->getResult(false, "App [$model->name] saved successfully");

        return $result;
    }

    public function getDelete($id)
    {
        $title = 'Delete APP ?';
        $model = App::find($id);
        $url   = Url::to('app/delete/' . $model->id);

        return view('app.delete', compact('title', 'model', 'url'));
    }

    public function postDelete(DeleteRequest $request, $id)
    {
        $result = $this->getResult(true, 'Could not delete APP');
        $model  = App::find($id);
        $users  = $model->users;

        if ($users->count())
            $result = $this->getResult(true, 'Could not delete APP: It has users');
        elseif ($model->delete()) {
            $model->deleteAppFromBilling();
            $result = $this->getResult(false, 'APP deleted');
        }

        return $result;
    }

    public function getDailyUsage()
    {
        $APP      = $this->app;
        $title    = $APP->name . ': Daily usage';
        $subtitle = 'View daily usage';

        return view('app.daily_usage', compact('title', 'subtitle', 'APP'));
    }

    public function getDailyUsageData()
    {
        $dailyUsage = $this->app->getDailyUsage();

        return Datatables::of($dailyUsage)->make(true);
    }

    protected function tryToStoreInBillingDB($app)
    {
        $result = $this->getResult(false, "App [$app->name] created successfully");
        try {
            $this->dispatch(new StoreAPPToBillingDB($app));
            $this->dispatch(new StoreAPPToChatServer($app));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $error   = "Error: $message";
            $result  = $this->getResult(true, $error);
        }

        return $result;
    }

}
