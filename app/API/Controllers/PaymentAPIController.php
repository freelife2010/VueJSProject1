<?php

namespace App\API\Controllers;


use App\API\APIHelperTrait;
use App\Helpers\BillingTrait;
use App\Helpers\Misc;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\AppUser;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;

class PaymentAPIController extends Controller
{
    use Helpers, APIHelperTrait, BillingTrait;

    private $userIdValidationRule = 'required|exists:users,id,deleted_at,NULL';


    public function __construct()
    {
        $this->initAPI();
    }

    public function getBalance(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid' => $this->userIdValidationRule
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }
        $user = AppUser::find($request->userid);

        $balance = 0;

        $clientId = $this->getClientIdByAliasFromBillingDB($user->getUserAlias());
        if ($clientId)
            $balance = $this->getClientBalanceFromBillingDB($clientId, 'ingress_balance');

        return $this->response->array(['balance' => $balance]);
    }

    public function postAddCredit(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid' => $this->userIdValidationRule,
            'amount' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $user = AppUser::find($request->userid);

        $response = ['result' => 'Failed'];

        $clientId = $this->getClientIdByAliasFromBillingDB($user->getUserAlias());
        if ($clientId) {
            $this->storeClientPaymentInBillingDB($clientId, $request->amount, $request->remark);
            $response = ['result' => 'ok'];
        }

        return $this->response->array($response);

    }

    public function getCreditHistory(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid' => $this->userIdValidationRule
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }
        $user = AppUser::find($request->userid);

        $response = [];

        $clientId = $this->getClientIdByAliasFromBillingDB($user->getUserAlias());
        if ($clientId)
            $response = $this->getClientPaymentsFromBillingDB($clientId);

        return $this->response->array($response);
    }

    public function getAllowedCountry(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid' => $this->userIdValidationRule
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }
        $user = AppUser::find($request->userid);

        $response = [];
        $clientId = $this->getClientIdByAliasFromBillingDB($user->getUserAlias());
        if ($clientId) {
            $rateTableId = $this->getRateTableIdByClientId($clientId);
            if ($rateTableId)
                $response = $this->queryAllowedCountries($rateTableId);
        }

        return $this->response->array($response);
    }

    protected function queryAllowedCountries($rateTableId)
    {
        $result = [];
        $data   = $this->selectFromBillingDB('
                    select country from rate where rate_table_id = ?
                    AND ((now() BETWEEN effective_date AND end_date) OR end_date IS NULL )', [$rateTableId]);
        if ($data) {
            foreach ($data as $entry) {
                $result[] = $entry->country;
            }
        }

        return $result;
    }

    public function getRates(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid'  => $this->userIdValidationRule,
            'country' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $user = AppUser::find($request->userid);

        $response = [];
        $clientId = $this->getClientIdByAliasFromBillingDB($user->getUserAlias());

        if ($clientId) {
            $rateTableId = $this->getRateTableIdByClientId($clientId);
            if ($rateTableId)
                $response = $this->queryRates($rateTableId, $request->country);
        }

        return $this->response->array($response);
    }

    protected function queryRates($rateTableId, $country)
    {
        return $this->selectFromBillingDB('
                    select code_name, rate from rate where rate_table_id = ?
                    AND ((now() BETWEEN effective_date AND end_date) OR end_date IS NULL )
                    AND country = ?', [$rateTableId, $country]);

    }

    public function getRate(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid'  => $this->userIdValidationRule,
            'number' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $user = AppUser::find($request->userid);

        $response = [];
        $clientId = $this->getClientIdByAliasFromBillingDB($user->getUserAlias());

        if ($clientId) {
            $rateTableId = $this->getRateTableIdByClientId($clientId);
            if ($rateTableId)
                $response = $this->queryRateByNumber($rateTableId, $request->number);
        }

        return $this->response->array($response);
    }

    protected function queryRateByNumber($rateTableId, $number)
    {
        return $this->selectFromBillingDB('
                    select rate from rate where rate_table_id = ?
                    AND ((now() BETWEEN effective_date AND end_date) OR end_date IS NULL )
                    AND code @> ? ORDER BY length(code::text) desc LIMIT 1', [$rateTableId, $number]);

    }

    public function getSip(Request $request)
    {
        $validator = $this->makeValidator($request, [
            'userid'  => $this->userIdValidationRule
        ]);
        if ($validator->fails()) {
            return $this->validationFailed($validator);
        }

        $user = AppUser::find($request->userid);

        $response = [];
        $username = Misc::filterNumbers($user->getUserAlias());

        if ($username) {
            $response = $this->selectFromBillingDB('
                                SELECT password FROM resource_ip
                                WHERE username = ?', [$username]);
        }

        return $this->response->array($response);
    }

}
