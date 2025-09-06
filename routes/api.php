<?php

use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowInstanceController;
use App\Http\Controllers\WorkflowValidationController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware('jwt.check')->prefix("workflows")->group(function () {//middleware('jwt.check')->

    // routes/api.php (microservice workflow)
Route::get('/by-document-type/{documentTypeId}', [WorkflowController::class, 'getByDocumentType']);

Route::get('/documents-to-validate', [WorkflowValidationController::class, 'getDocumentsToValidateByRole']);

Route::post('/workflow-instances/{documentId}/validate', [WorkflowInstanceController::class, 'validateStep']
);



Route::post('/workflow-instances', [WorkflowInstanceController::class, 'store']);




   Route::apiResource('/', WorkflowController::class);




});
