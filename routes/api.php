<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Catalog\CatalogController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dealer\DealerController;
use App\Http\Controllers\Api\Part\CartController;
use App\Http\Controllers\Api\Part\PartController;
use App\Http\Controllers\Api\Part\PofController;
use App\Http\Controllers\Api\Part\SuggestionController;
use App\Http\Controllers\Api\Promo\PromoController;
use App\Http\Controllers\Api\Sales\EfectivitasController;
use App\Http\Controllers\Api\Sales\SalesmanController;
use App\Http\Controllers\Api\Sales\VisitController;
use App\Http\Controllers\Api\Tracking\TrackingController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => 'authBasic'], function() {
    Route::controller(AuthController::class)->group(function () {
        Route::post('oauth/token', 'oauthToken');
    });
});

// Route::post('dashboard/push-notification', 'Dashboard\PushNotificationController@PushNotification');
//
// ;

Route::group(['middleware' => 'checkToken'], function() {
    Route::controller(AuthController::class)->group(function () {
        Route::post('auth/login', 'login');
        Route::post('auth/logout', 'logout');
        Route::post('auth/forgot-password', 'forgotPassword');
        Route::post('oauth/renew', 'renewLogin');
        Route::post('profile/profile', 'profile');
        Route::post('profile/change-password', 'changePassword');
    });

    Route::controller(DashboardController::class)->group(function () {
        Route::post('dashboard/index', 'index');
        Route::post('dashboard/notice', 'Notice');

    });

    Route::controller(CatalogController::class)->group(function () {
        Route::get('catalog/catalog', 'listCatalog');
    });

    Route::controller(CartController::class)->group(function () {
        Route::post('cart/add-cart', 'addCart');
        Route::post('cart/add-to-cart', 'addToCart');
        Route::post('cart/remove-cart', 'removeCart');
        Route::post('cart/list-cart', 'listCart');
        Route::post('cart/update-quantity', 'updateQuantity');
        Route::post('cart/update-harga', 'updateHarga');
        Route::post('cart/update-disc-detail', 'updateDiscDetail');
        Route::post('cart/update-disc-header', 'updateDiscHeader');
        Route::post('cart/update-tpc', 'updateTpc');
        Route::post('cart/submit-order', 'submitOrder');

    });

    Route::controller(DealerController::class)->group(function () {
        Route::get('dealer/list-dealer', 'listDealer');
        Route::get('dealer/list-competitor', 'listCompetitor');
        Route::post('dealer/add-competitor', 'addCompetitor');
        Route::post('dealer/add-new-dealer', 'addNewDealer');
        Route::post('dealer/update-dealer', 'updateDealerLocation');

        Route::post('dealer/list-kredit-limit', 'listKreditLimit');
        Route::post('dealer/list-jatuh-tempo', 'listJatuhTempo');
        Route::post('dealer/detail-jatuh-tempo', 'detailJatuhTempo');
    });

    Route::controller(PartController::class)->group(function () {
        Route::post('part/list-motor-type', 'listMotorType');
        Route::post('part/list-item-group', 'listItemGroup');
        Route::post('part/part-search', 'partSearch');
        Route::post('part/part-favorite', 'listPartFavorite');
        Route::post('part/add-favorite', 'addPartFavorite');
        Route::post('part/list-back-order', 'listBackOrder');
        Route::post('part/skema-pembelian', 'skemaPembelian');

        Route::post('part/check-stock', 'partSearch');
    });

    Route::controller(SalesmanController::class)->group(function () {
        Route::get('sales/list-salesman', 'listSalesman');
        Route::get('sales/list-selected-salesman', 'listSelectedSalesman');
        Route::get('sales/list-koordinator', 'listKoordinator');

    });

    Route::controller(SuggestionController::class)->group(function () {
        Route::post('suggest/order-suggest', 'listSuggestOrder');
        Route::post('suggest/use-suggestion', 'useSuggestion');
    });

    Route::controller(EfectivitasController::class)->group(function () {
        Route::post('sales/efectivitas-salesman', 'efectivitasSalesman');
        Route::post('sales/efectivitas-coordinator', 'efectivitasKoordinator');
        Route::post('sales/efectivitas-manager', 'efectivitasManager');

        Route::post('sales/realisasi-visit-salesman', 'efectivitasSalesman');
        Route::post('sales/realisasi-visit-coordinator', 'efectivitasKoordinator');
        Route::post('sales/realisasi-visit-manager', 'efectivitasManager');
    });

    Route::controller(PofController::class)->group(function () {
        Route::post('pof/list-pof-order', 'listPofOrder');
        Route::post('pof/detail-pof-order', 'detailPofOrder');
        Route::post('pof/order-approve', 'approveOrder');
        Route::post('pof/cancel-approve', 'cancelApprove');
        Route::post('pof/update-tpc', 'updateTpc');
        Route::post('pof/update-back-order', 'updateStatusBo');
        Route::post('pof/update-umur-pof', 'updateUmurPof');
        Route::post('pof/update-keterangan', 'updateKeterangan');
        Route::post('pof/update-disc-header', 'updateDiscHeader');
        Route::post('pof/update-quantity', 'updateQuantity');
        Route::post('pof/update-harga', 'updateHargaDetail');
        Route::post('pof/update-disc-detail', 'updateDiscDetail');
        Route::post('pof/hapus-part-number', 'hapusPartNumber');
        Route::post('pof/hapus-pof-order', 'hapusPofOrder');
    });

    Route::controller(PromoController::class)->group(function () {
        Route::get('promo/brosure-promo', 'listBrosur');
        Route::get('promo/part-promo', 'listPromoPart');
    });

    Route::controller(TrackingController::class)->group(function () {
        Route::post('tracking/tracking-order', 'trackingOrder');
        Route::post('tracking/detail-tracking', 'detailTracking');
    });

    Route::controller(VisitController::class)->group(function () {
        Route::post('visit/date-visit', 'dateVisit');
        Route::post('visit/check-checkin', 'checkCheckInDashboard');
        Route::post('visit/add-visit', 'addVisit');
        Route::post('visit/checkin', 'checkIn');
        Route::post('visit/checkin-checkout', 'checkOut');
        Route::post('visit/list-date-visit', 'listPlanningVisit');
        Route::post('visit/add-date-visit', 'addPlanningVisit');
        Route::post('visit/delete-date-visit', 'deletePlanningVisit');
    });


//     Route::group(['middleware' => 'checkLogin'], function() {
//
//
//





//         Route::post('sales/realisasi-visit-salesman', 'Sales\RealisasiVisitController@RealisasiVisitSalesman');
//         Route::post('sales/realisasi-visit-coordinator', 'Sales\RealisasiVisitController@RealisasiVisitCoordinator');
//         Route::post('sales/realisasi-visit-manager', 'Sales\RealisasiVisitController@RealisasiVisitManager');

//         Route::post('sales/', 'Sales\EfectivitasController@EfectivitasSalesman');
//         Route::post('sales/efectivitas-coordinator', 'Sales\EfectivitasController@EfectivitasKoordinator');
//         Route::post('sales/efectivitas-manager', 'Sales\EfectivitasController@EfectivitasManager');





//








//





    // });
});


