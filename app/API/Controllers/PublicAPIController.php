<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 05.11.15
 * Time: 17:18
 */

namespace App\API\Controllers;


use App\API\APIHelperTrait;
use App\Jobs\StoreAPPToBillingDB;
use App\Jobs\StoreAPPToChatServer;
use App\Models\App;
use App\User;
use Dingo\Api\Http\Request;
use Dingo\Api\Routing\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class PublicAPIController extends Controller{
    use Helpers, APIHelperTrait;


    public function createAPP(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'name'       => 'required|unique:app',
            'account_id' => 'required',
            'sign'       => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }
        $sign      = $this->getSign($request);
        if ($sign != $request->input('sign'))
            return $this->makeErrorResponse('Incorrect sign');
        else return $this->makeApp($request);
    }

    private function makeApp($request)
    {
        $accountId  = $request->input('account_id');
        $name       = $request->input('name');
        $alias      = Str::random(30);
        $user       = User::find($accountId);
        $app        = new App();
        $attributes = compact('name', 'alias');
        $response   = ['error' => 'APP creation failed'];
        if ($user and $app->createApp($attributes, $user)) {
            $this->dispatch(new StoreAPPToBillingDB($app, $user));
            $this->dispatch(new StoreAPPToChatServer($app, $user));
            $response = [
                'app_uuid'   => $app->key->id,
                'app_secret' => $app->key->secret,
                'duration'   => App::APP_KEYS_EXPIRE_DAYS,
            ];

        }

        return $this->defaultResponse($response);
    }



}