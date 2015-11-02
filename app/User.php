<?php namespace App;

use App\Helpers\BillingTrait;
use Hash;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Bican\Roles\Traits\HasRoleAndPermission;
use Bican\Roles\Contracts\HasRoleAndPermission as HasRoleAndPermissionContract;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract, HasRoleAndPermissionContract {

	use Authenticatable, CanResetPassword, HasRoleAndPermission, BillingTrait;

	/**
	 * The database table used by the model.
	 *
	 * @var string
	 */
	protected $table = 'accounts';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = ['name', 'email', 'password'];

	/**
	 * The attributes excluded from the model's JSON form.
	 *
	 * @var array
	 */
	protected $hidden = ['password', 'remember_token'];

	public function accountIsActive($code) {
		$user = User::where('activation_code', '=', $code)->first();
		$user->active = 1;
		$user->activation_code = '';
		if($user->save()) {
			\Auth::login($user);
		}
        $this->fill($user->attributesToArray());
		return true;
	}

    public function setPasswordAttribute($pass){

        $this->attributes['password'] = Hash::make($pass);

    }

    public function getFullNameAttribute()
    {
        return $this->first_name. ' ' . $this->last_name;
    }

    /**
     * Inserts current user to Billing DB
     */
    public function addToBillingDB()
    {
        $currencyId = $this->selectFromBillingDB("
                            select currency_id
                            from currency where code = ?", ['USA']);
        if (isset($currencyId[0]))
            $currencyId = $currencyId[0]->currency_id;
        else return false;
        $cliendId   = $this->insertGetIdToBillingDB("
                            insert into client
                            (name,currency_id,unlimited_credit,mode,enough_balance)
                            values (?,?,?,?,?) RETURNING client_id",
                                [$this->email, $currencyId, true, 2, true]);
        if (isset($cliendId[0]))
            $cliendId = $cliendId[0]->client_id;
        $this->insertToBillingDB("
                    insert into client_balance (client_id,balance,ingress_balance)
                    values (?,?,?)",
                        [$cliendId, 0, 0]);

        return $cliendId;
    }
	
}
