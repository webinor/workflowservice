<?php

use App\Http\Controllers\DocumentWorkflowController;
use App\Http\Controllers\WorkflowActionController;
use App\Http\Controllers\WorkflowActionStepController;
use App\Http\Controllers\WorkflowController;
use App\Http\Controllers\WorkflowInstanceController;
use App\Http\Controllers\WorkflowInstanceStepController;
use App\Http\Controllers\WorkflowStepController;
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
Route::middleware("jwt.check")
    ->prefix("workflows")
    ->group(function () {
        // WorkflowController
        Route::controller(WorkflowController::class)->group(function () {
            Route::get(
                "/by-document-type/{documentTypeId}",
                "getByDocumentType"
            );
            Route::post(
                "/check-if-inject-departments/{documentTypeId}",
                "checkIfInjectDepartments"
            );
            Route::get("/{id}/steps", "steps");
            Route::apiResource("/", WorkflowController::class);
        });

        // WorkflowController
        Route::controller(WorkflowController::class)->group(function () {
            Route::get("/workflow-steps/{stepId}/attachment-types", [
                WorkflowStepController::class,
                "attachmentTypes",
            ]);
        });

        // WorkflowInstanceController
        Route::controller(WorkflowInstanceController::class)->group(
            function () {
                Route::post("/workflow-instances", "store");
                Route::post(
                    "/workflow-instances/{documentId}/validate",
                    "validateStep"
                );
                Route::post(
                    "/workflow-instances/{documentId}/reject",
                    "rejectStep"
                );
                Route::post(
                    "/workflow-instances/{documentId}/check-for-blocker",
                    "checkIfHasBlocker"
                );
                Route::get("/document/{id}", "history");
                Route::get(
                    "/documents/{documentId}/current-step",
                    "getCurrentStepOfDocument"
                );
                Route::post(
                    "/test-notify/{workflowInstanceStep}/{departmentId}",
                    "testNotify"
                );
                Route::get("/test-remind", "testRemind");
            }
        );

        // WorkflowValidationController
        Route::get("/documents-to-validate", [
            WorkflowValidationController::class,
            "getDocumentsToValidateByRole",
        ]);

        // WorkflowTransferController
        Route::post("/documents/transfer", [
            WorkflowTransferController::class,
            "transferDocument",
        ]);

        // WorkflowInstanceStepController
        Route::get("/documents/{documentId}/comments", [
            WorkflowInstanceStepController::class,
            "getWorkflowComments",
        ]);

        // WorkflowActionController & WorkflowActionStepController
        Route::post("/workflow-actions", [
            WorkflowActionController::class,
            "store",
        ]);
        Route::get("workflow-steps/{instanceStep}/actions", [
            WorkflowActionStepController::class,
            "getActionsByStep",
        ]);

        // DocumentWorkflowController
        Route::controller(DocumentWorkflowController::class)->group(
            function () {
                Route::get(
                    "/documents/{documentId}/validation-history",
                    "validationHistory"
                );
                Route::get(
                    "/documents/{documentId}/preview-history",
                    "previewHistory"
                );
            }
        );
    });
