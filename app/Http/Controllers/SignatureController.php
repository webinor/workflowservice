<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSignatureRequest;
use App\Http\Requests\UpdateSignatureRequest;
use App\Models\Signature;
use App\Models\SignatureType;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\PhysicalSupportingDocumentsService;
use App\Services\Workflow\Status\WorkflowStatusManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SignatureController extends Controller
{
    protected WorkflowStatusManager $workflowStatusManager;

protected PhysicalSupportingDocumentsService $physicalSupportingDocumentsService;

public function __construct(
    WorkflowStatusManager $workflowStatusManager,
    PhysicalSupportingDocumentsService $physicalSupportingDocumentsService
) {
    $this->workflowStatusManager = $workflowStatusManager;

    $this->physicalSupportingDocumentsService =
        $physicalSupportingDocumentsService;
}

    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store(StoreSignatureRequest $request)
    {
        //
    }
    /**
 * Détermine si le type de transaction correspond
 * à la régularisation d'une fiche.
 *
 * Les avances ne doivent pas déclencher la transmission
 * automatique des justificatifs physiques.
 */
private function isRegularizationTransaction(
    string $transactionTypeCode
): bool {
    return strtoupper(trim($transactionTypeCode))
        === 'REGULARIZATION_SETTLEMENT';
}

public function storeBeneficiarySignature(Request $request)
{
    $request->validate([
        'document_id' => 'required|integer',
        'document_uuid' => 'required|string',
        'actor_type' => 'required|string',
        'actor_id' => 'required|integer',
        'actor_name' => 'required|string',
        'actor_role' => 'required|string',
        'transaction_type_code' => 'required|string',
    ]);

    return DB::transaction(function () use ($request) {

        /*
         * ---------------------------------------------------------
         * 1. Récupération du type de signature
         * ---------------------------------------------------------
         */
        $signatureType = SignatureType::whereCode(
            $request->transaction_type_code
        )->first();

        if (!$signatureType) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun type de signature actif'
            ], 404);
        }

        /*
         * ---------------------------------------------------------
         * 2. Récupération de l'instance workflow
         * ---------------------------------------------------------
         */
        $instance = WorkflowInstance::where(
            'document_id',
            $request->document_id
        )->first();

        if (!$instance) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune instance workflow trouvée'
            ], 404);
        }

        /*
         * ---------------------------------------------------------
         * 3. Récupération de l'étape PENDING courante
         * ---------------------------------------------------------
         */
        $instanceStep = WorkflowInstanceStep::where(
            'workflow_instance_id',
            $instance->id
        )
            ->where('status', 'PENDING')
            ->orderBy('position')
            ->first();

        if (!$instanceStep) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune étape active'
            ], 404);
        }

        /*
 * ---------------------------------------------------------
 * 3.1 Confirmation de la transmission des justificatifs
 * ---------------------------------------------------------
 *
 * La règle est gérée par le handler métier de la fiche
 * à régulariser.
 *
 * Cette opération est effectuée avant Signature::create().
 *
 * Si le Document Service ne confirme pas la transmission,
 * la signature ne doit pas être enregistrée.
 */
$confirmationResult =
    $this->workflowStatusManager
        ->confirmPhysicalDocumentsBeforeBeneficiarySignature(
            $instanceStep,
            $instance->document_type_relation_name,
            (int) $request->document_id,
            $request->document_uuid,
            [
                'id' => (int) $request->actor_id,
                'name' => $request->actor_name,
                'type' => $request->actor_type,
                'role' => $request->actor_role,
            ],
            "BENEFICIARY_SIGNATURE"
        );

/*
 * ---------------------------------------------------------
 * 3.2 Vérification de la confirmation
 * ---------------------------------------------------------
 *
 * null signifie qu'aucune confirmation n'était nécessaire.
 */
if (
    $confirmationResult !== null
    && empty($confirmationResult['success'])
) {
    Log::error(
        '[SignatureController] Signature non enregistrée : '
        . 'transmission des justificatifs non confirmée.',
        [
            'document_id' => $request->document_id,
            'document_uuid' => $request->document_uuid,
            'workflow_instance_id' => $instance->id,
            'workflow_instance_step_id' => $instanceStep->id,
            'transaction_type_code' => $request->transaction_type_code,
            'actor_id' => $request->actor_id,
            'document_service_status' => $confirmationResult['status'] ?? null,
            'document_service_message' => $confirmationResult['message'] ?? null,
        ]
    );

    return response()->json([
        'success' => false,
        'message' => 'La transmission des justificatifs physiques '
            . 'n’a pas pu être confirmée. '
            . 'La signature n’a pas été enregistrée.',
        'error_code' => 'PHYSICAL_DOCUMENTS_CONFIRMATION_FAILED',
    ], 502);
}

        /*
         * ---------------------------------------------------------
         * 4. Enregistrement de la signature
         * ---------------------------------------------------------
         *
         * Cette étape n'est atteinte que si la confirmation
         * des justificatifs a réussi lorsqu'elle était requise.
         */
        $signature = Signature::create([
            'document_id' => $request->document_id,
            'document_uuid' => $request->document_uuid,
            'signature_type_id' => $signatureType->id,
            'workflow_instance_step_id' => $instanceStep->id,
            'actor_type' => $request->actor_type,
            'actor_id' => $request->actor_id,
            'actor_name' => $request->actor_name,
            'actor_role' => $request->actor_role,
            'comment' => $request->comment,
            'signed_at' => now(),
        ]);

        /*
         * ---------------------------------------------------------
         * 5. Détermination du type de document
         * ---------------------------------------------------------
         *
         * IMPORTANT :
         * Ne pas utiliser transaction_type_code ici.
         *
         * Le transaction_type_code concerne la signature /
         * transaction.
         *
         * Le WorkflowStatusManager doit travailler avec
         * le type de document.
         */
        $documentTypeCode = $instance->document_type_relation_name;

        /*
         * ---------------------------------------------------------
         * 6. Application du statut spécifique au document
         * ---------------------------------------------------------
         */
        $instanceStep = $this->workflowStatusManager->handle(
            $instanceStep,
            $documentTypeCode
        );

        /*
         * ---------------------------------------------------------
         * 7. Réponse
         * ---------------------------------------------------------
         */
        return response()->json([
            'success' => true,
            'message' => 'Signature enregistrée',
            'signature' => $signature,
            'workflow_instance_step' => $instanceStep,
        ]);
    });
}

    public function OldstoreBeneficiarySignature(Request $request)
    {
        $request->validate([
            'document_id' => 'required|integer',
            'document_uuid' => 'required|string',
            'actor_type' => 'required|string',
            'actor_id' => 'required|integer',
            'actor_name' => 'required|string',
            'actor_role' => 'required|string',
            'transaction_type_code' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {

            /*
             * ---------------------------------------------------------
             * 1. Récupération du type de signature
             * ---------------------------------------------------------
             */
            $signatureType = SignatureType::whereCode(
                $request->transaction_type_code
            )->first();

            if (!$signatureType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun type de signature actif'
                ], 404);
            }

            /*
             * ---------------------------------------------------------
             * 2. Récupération de l'instance workflow
             * ---------------------------------------------------------
             */
            $instance = WorkflowInstance::where(
                'document_id',
                $request->document_id
            )->first();

            if (!$instance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune instance workflow trouvée'
                ], 404);
            }

            /*
             * ---------------------------------------------------------
             * 3. Récupération de l'étape PENDING courante
             * ---------------------------------------------------------
             */
            $instanceStep = WorkflowInstanceStep::where(
                'workflow_instance_id',
                $instance->id
            )
                ->where('status', 'PENDING')
                ->orderBy('position')
                ->first();

            if (!$instanceStep) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune étape active'
                ], 404);
            }

            /*
             * ---------------------------------------------------------
             * 4. Enregistrement de la signature
             * ---------------------------------------------------------
             */
            $signature = Signature::create([
                'document_id' => $request->document_id,
                'document_uuid' => $request->document_uuid,
                'signature_type_id' => $signatureType->id,
                'workflow_instance_step_id' => $instanceStep->id,
                'actor_type' => $request->actor_type,
                'actor_id' => $request->actor_id,
                'actor_name' => $request->actor_name,
                'actor_role' => $request->actor_role,
                'comment' => $request->comment,
                'signed_at' => now(),
            ]);

            /*
             * ---------------------------------------------------------
             * 5. Détermination du type de document
             * ---------------------------------------------------------
             *
             * IMPORTANT :
             * Ne pas utiliser transaction_type_code ici.
             *
             * Le transaction_type_code concerne la signature /
             * transaction.
             *
             * Le WorkflowStatusManager doit travailler avec
             * le type de document.
             */
            $documentTypeCode = $instance->document_type_relation_name;

            /*
             * ---------------------------------------------------------
             * 6. Application du statut spécifique au document
             * ---------------------------------------------------------
             */
            $instanceStep = $this->workflowStatusManager->handle(
                $instanceStep,
                $documentTypeCode
            );

            /*
             * ---------------------------------------------------------
             * 7. Réponse
             * ---------------------------------------------------------
             */
            return response()->json([
                'success' => true,
                'message' => 'Signature enregistrée',
                'signature' => $signature,
                'workflow_instance_step' => $instanceStep,
            ]);
        });
    }

    public function show(Signature $signature)
    {
        //
    }

    public function edit(Signature $signature)
    {
        //
    }

    public function update(
        UpdateSignatureRequest $request,
        Signature $signature
    ) {
        //
    }

    public function destroy(Signature $signature)
    {
        //
    }
}