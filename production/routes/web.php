<?php

use Firebase\JWT\JWT;
/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    //return $router->app->version();

    $curl = curl_init();
    
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api1.indiplex.co.in/api/payout/transaction/status',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS =>'{
        "bankRefId": "510414236417",
        "apiRefNum" : "PGPETXN5994410"
    }',
      CURLOPT_HTTPHEADER => array(
        'x-api-key: fO8d2yHFZUtbVUULQTiMLwgopRoYppHwePiXcchFVqw',
        'Content-Type: application/json',
        'Authorization: Basic SU5ESTExNTpNakF5TlRBME1EUXhOelU1TWpnPQ=='
      ),
    ));
    
    $response = curl_exec($curl);
    
    curl_close($curl);
    echo "https://api1.indiplex.co.in/api/payout/transaction/status"."<br>";
    echo '{
        "bankRefId": "510414236417",
        "apiRefNum" : "PGPETXN5994410"
    }'."<br>";
    echo $response;
    
        
});

$router->get('getcode', 'AuthController@getcode');
$router->get('api/getip', 'AuthController@getip');
$router->get('api/utility/provider', 'Services\RechargeController@providersList');
$router->get('api/bbps/provider', 'Services\BillpayController@providersList');


$router->group(['prefix' => 'api/webhook'], function () use ($router) {
    $router->post('{api}/QR', 'Services\UpiController@webhook');
    $router->post('{api}/payin', 'Services\UpiController@webhook');
    $router->get('{api}/payin', 'Services\UpiController@webhook');
    $router->post('{api}/payout', 'Services\PayoutController@webhook');
    $router->get('{api}/payout', 'Services\PayoutController@webhook');
});

$router->group(['prefix' => 'api', 'middleware' => 'auth'], function () use ($router) {
    $router->post('wallet/balance', 'AuthController@getbalance');

    $router->group(['prefix' => 'collection/order'], function () use ($router) {
        $router->post('create', 'Services\UpiController@create');
        $router->post('paymenturl', 'Services\UpiController@paymenturl');
        $router->post('status' , 'Services\UpiController@status');
    });

    $router->group(['prefix' => 'payment/order'], function () use ($router) {
        $router->post('create', 'Services\PayoutController@create');
        $router->post('bulkpay', 'Services\PayoutController@bulkpayout');
        $router->post('upi', 'Services\PayoutController@upipayment');
        $router->post('status' , 'Services\PayoutController@status');
    });
});
