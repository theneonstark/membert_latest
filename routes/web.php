<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Report\DataController;
use App\Http\Controllers\Report\ReportController;
use App\Http\Controllers\Report\ExportController;
use App\Http\Controllers\Report\StatementController;
use App\Http\Controllers\Report\CommonController;
use App\Http\Controllers\PortalSetting\ComplaintController;
use App\Http\Controllers\Member\SettingController;
use App\Http\Controllers\Fund\PayoutController;
use App\Http\Controllers\Fund\FundController;
use App\Http\Controllers\Fund\UpiController;
use App\Http\Controllers\Fund\RoleController;
use App\Http\Controllers\PortalSetting\ResourceController;
use App\Http\Controllers\PortalSetting\ActionController;
use App\Http\Controllers\Member\MemberController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [UserController::class, 'loginpage'])->middleware('guest')->name('mylogin');
Route::get('login', [UserController::class, 'loginpage'])->middleware('guest');
Route::get('start', [UserController::class, 'signup'])->name("signup");

Route::group(['prefix' => 'auth'], function() {
    Route::post('check', [UserController::class, 'login'])->name('authCheck')->middleware("location");
    Route::get('logout', [UserController::class, 'logout'])->name('logout');
    Route::post('reset', [UserController::class, 'passwordReset'])->name('authReset');
    Route::post('getotp', [UserController::class, 'getotp'])->name('getotp');
    Route::post('setpin', [UserController::class, 'setpin'])->name('setpin');
    Route::post('onboard', [UserController::class, 'web_onboard'])->name('web_onboard');
});

Route::group(['middleware' => ['auth', "service"]], function() {
    Route::get('dashboard', [HomeController::class, 'index'])->name('home');
    Route::get('onboard/complete', [HomeController::class, 'onboarding'])->name('onboarding');
    Route::get('invoice/{id}', [HomeController::class, 'invoice'])->name('invoice');
    Route::post('complete_kyc', [HomeController::class, 'complete_kyc'])->name("onboardingUpdate");
});

Route::group(['middleware' => ['auth', 'company', 'service']], function() {
    /*Common Route*/
    Route::get('getmyip', [HomeController::class, 'getmysendip']);
    Route::get('balance', [HomeController::class, 'getbalance'])->name('getbalance');
    Route::get('statics', [HomeController::class, 'statics'])->name('statics');

    Route::group(['prefix' => 'developer/api', 'middleware' => ['company', 'checkrole:apiuser|whitelable']], function() {
        Route::get('download', [ApiController::class, 'download'])->name('downloadkey');
        Route::get('{type}', [ApiController::class, 'index'])->name('apisetup');
        Route::post('update', [ApiController::class, 'update'])->name('apitokenstore');
        Route::post('ip', [ApiController::class, 'ip'])->name('apiiptore');
        Route::post('token/delete', [ApiController::class, 'tokenDelete'])->name('tokenDelete');
        Route::post('ip/delete', [ApiController::class, 'ipDelete'])->name('ipDelete');
        Route::post('aes', [ApiController::class, 'aes'])->name('aes');
    });

    /* Reporting & Actions */
    Route::group(['prefix'=> 'statement'], function() {
        Route::post('data/statics', [DataController::class, 'fetchData'])->name('datastatics');

        Route::get('report/{type?}/{id?}', [ReportController::class, 'index'])->name('reports');
        Route::post('report/static', [ReportController::class, 'fetchData'])->name('reportstatic');

        Route::get('list/{type}/{id?}/{status?}', [StatementController::class, 'index'])->name('statement');
        Route::post('list/fetch/{type}/{id?}/{returntype?}', [CommonController::class, 'fetchData']);
    });

    Route::group(['prefix'=> 'export'], function() {
        Route::get('report/{type}', [ExportController::class, 'export']);
        Route::get("statement/{type}", [StatementController::class, 'export'])->name('export');
    });

    Route::group(['prefix' => 'report/action', 'middleware' => 'service'], function() {
        Route::post('complaint', [ActionController::class, 'complaint'])->name('complaint');
    });

    /* Members Route */
    Route::group(['prefix'=> 'member'], function() {
        Route::get('profile/view/{id?}', [SettingController::class, 'index'])->name('profile');
        Route::post('profile/update', [SettingController::class, 'profileUpdate'])->name('profileUpdate');
    });

    /* Members Route */
    Route::group(['prefix'=> 'member'], function() {
        Route::get('{type}/{action?}', [MemberController::class, 'index'])->name('member');
        Route::post('store', [MemberController::class, 'create'])->name('memberstore');
        Route::post('commission/update', [MemberController::class, 'commissionUpdate'])->name('commissionUpdate');
        Route::post('getcommission', [MemberController::class, 'getCommission'])->name('getMemberCommission');
    });

    /* Fund Manager */
    Route::group(['prefix'=> 'fund', 'middleware' => ['service', 'location']], function() {
        Route::get('utility/{type}/{action?}', [FundController::class, 'index'])->name('fund');
        Route::post('utility/transaction', [FundController::class, 'transaction'])->name('fundtransaction');
        Route::post('utility/statics/{type}', [FundController::class, 'fetchData']);

        Route::get('other/{type}/{action?}', [PayoutController::class, 'index'])->name('payout');
        Route::post('other/transaction', [PayoutController::class, 'transaction'])->middleware(["pincheck", "servicepermission:aepssettlement"])->name('payouttransaction');
        Route::post('other/statics/{type}', [PayoutController::class, 'fetchData']);

        Route::any('upi/initiate', [UpiController::class, 'index'])->name('upi');
        Route::post('upi/payment', [UpiController::class, 'transaction'])->name('upipay');
    });

    /* Resource Routes */
    Route::group(['prefix' => 'company'], function() {
        Route::get('resources/{type}', [ResourceController::class, 'index'])->name('resource');
        Route::post('resources/get/{type}/commission', [ResourceController::class, 'getCommission']);
    });

    /* Complaint Mamager*/
    Route::group(['prefix' => 'help'], function() {
        Route::get('{type}/{id?}/{product?}', [ComplaintController::class, 'index'])->name('help');
        Route::post('store', [ComplaintController::class, 'store'])->name('helpsubmit');
    });

    /* Resource Routes */
    Route::group(['prefix' => 'company'], function() {
        Route::get('resources/{type}', [ResourceController::class, 'index'])->name('resource');
        Route::post('resources/update', [ResourceController::class, 'update'])->name('resourceupdate');
    });
});