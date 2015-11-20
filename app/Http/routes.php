<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/


use LucaDegasperi\OAuth2Server\Facades\Authorizer;

Route::pattern('id', '[0-9]+');

Route::controllers([
    'auth' => 'Auth\AuthController',
    'password' => 'Auth\PasswordController'
]);


Route::group(['middleware' => ['auth', 'csrf']], function() {
    Route::get('/', 'HomeController@getIndex');
    Route::controller('home', 'HomeController');
    Route::controller('app', 'AppController');
    Route::controller('did', 'DIDController');
    Route::controller('payments', 'PaymentController');
    Route::controller('app-users', 'AppUsersController');
    Route::controller('app-keys', 'AppKeysController');
    Route::controller('cdr', 'CDRController');
    Route::controller('app-cdr', 'AppCDRController');
});

Route::get('/resendEmail', 'Auth\AuthController@resendEmail');

Route::get('/activate/{code}', 'Auth\AuthController@activateAccount');

Route::group(['middleware' => ['admin', 'csrf']], function() {
    Route::controller('emails', 'EmailController');
    Route::controller('revisions', 'RevisionsController');
    Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
});


//API Routes

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) {
    $api->post('apps', 'App\API\Controllers\PublicAPIController@CreateAPP');
});

$api->version('v1', ['middleware' => 'api.auth'], function ($api) {

    $api->get('token', 'App\API\Controllers\PublicAPIController@getTokenInfo');

    $api->get('users', 'App\API\Controllers\UserController@getUsers');
    $api->post('users', 'App\API\Controllers\UserController@createUsers');

    $api->get('users/{username}', 'App\API\Controllers\UserController@getUserInfo');
});

//Grants access token
Route::post('api/token', function() {
    return Response::json(Authorizer::issueAccessToken());
});
