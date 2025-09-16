<?php

use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\WorkflowActionController;
use App\Http\Controllers\WorkflowActionStepController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowInstanceController;
use App\Http\Controllers\WorkflowInstanceStepController;
use App\Http\Controllers\WorkflowTransferController;
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

Route::post('/workflow-instances/{documentId}/validate', [WorkflowInstanceController::class, 'validateStep']);

Route::get('/document/{id}', [WorkflowInstanceController::class, 'history']);

Route::post('/documents/transfer', [WorkflowTransferController::class, 'transferDocument']);


Route::post('/workflow-instances', [WorkflowInstanceController::class, 'store']);

Route::get('/documents/{documentId}/comments', [WorkflowInstanceStepController::class, 'getWorkflowComments']);

Route::get('/{id}/steps', [WorkflowController::class, 'steps']);

Route::post('/workflow-actions', [WorkflowActionController::class, 'store']);

Route::get('workflow-steps/{stepId}/actions', [WorkflowActionStepController::class, 'getActionsByStep']);

Route::get('/documents/{documentId}/current-step', [WorkflowInstanceController::class, 'getCurrentStepOfDocument']);

Route::get('/documents/{documentId}/validation-history', [DocumentWorkflowController::class, 'validationHistory']);

Route::get('/documents/{documentId}/preview-history', [DocumentWorkflowController::class, 'previewHistory']);



   Route::apiResource('/', WorkflowController::class);

});
