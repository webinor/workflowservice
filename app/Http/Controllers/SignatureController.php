<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSignatureRequest;
use App\Http\Requests\UpdateSignatureRequest;
use App\Models\Signature;
use App\Models\SignatureType;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\Workflow\Status\WorkflowStatusManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignatureController extends Controller
{
    protected WorkflowStatusManager $workflowStatusManager;

    public function __construct(
        WorkflowStatusManager $workflowStatusManager
    ) {
        $this->workflowStatusManager = $workflowStatusManager;
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