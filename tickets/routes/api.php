<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PublicEventsController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\TicketTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{event}', [EventController::class, 'show']);
Route::get('/events/{event}/ticket-types', [TicketTypeController::class, 'indexForEvent']);
Route::get('/ticket-types/{ticketType}', [TicketTypeController::class, 'show']);

Route::get('/public/events', [PublicEventsController::class, 'index']);

Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::resource('events', EventController::class)
        ->only(['store', 'update', 'destroy']);

    Route::post('/events/{event}/ticket-types', [TicketTypeController::class, 'store']);
    Route::put('/ticket-types/{ticketType}', [TicketTypeController::class, 'update']);
    Route::delete('/ticket-types/{ticketType}', [TicketTypeController::class, 'destroy']);

    Route::put('/events/{event}/queue/join',   [PurchaseController::class, 'joinQueue']);
    Route::get('/events/{event}/queue/status', [PurchaseController::class, 'queueStatus']);
    Route::post('/events/{event}/queue/admit', [PurchaseController::class, 'admitNext']);

    Route::get('/purchases', [PurchaseController::class, 'index']);
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show']);
    Route::post('/events/{event}/purchases/reserve', [PurchaseController::class, 'reserve']);
    Route::post('/purchases/{purchase}/pay', [PurchaseController::class, 'pay']);
});
