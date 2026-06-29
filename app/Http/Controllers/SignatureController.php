<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSignatureRequest;
use App\Http\Requests\UpdateSignatureRequest;
use App\Models\Signature;
use App\Models\SignatureType;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use Illuminate\Http\Request;

class SignatureController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreSignatureRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSignatureRequest $request)
    {
        //
    }

    public function storeBeneficiarySignature(Request $request)
{
    $request->validate([
        'document_id' => 'required|integer',
        'actor_type' => 'required|string',
        'actor_id' => 'required|integer',
        'actor_name' => 'required|string',
        'actor_role' => 'required|string',
        'transaction_type_code' => 'required|string',
    ]);

    $signatureType = SignatureType::whereCode($request->transaction_type_code)->first();

    if (!$signatureType) {
        return response()->json([
            'success' => false,
            'message' => 'Aucun type de signature actif'
        ], 404);
    }

    $instance = WorkflowInstance::where('document_id', $request->document_id)
    ->firstOrFail();

  $instanceStep = WorkflowInstanceStep::where('workflow_instance_id', $instance->id)
    ->where('status', 'PENDING')
    ->orderBy('position')
    ->firstOrFail();

    if (!$instanceStep) {
        return response()->json([
            'success' => false,
            'message' => 'Aucune étape active'
        ], 404);
    }

    Signature::create([
        'document_id' => $request->document_id,
        'signature_type_id' => $signatureType -> id,
        'workflow_instance_step_id' => $instanceStep->id,
        'actor_type' => $request->actor_type,
        'actor_id' => $request->actor_id,
        'actor_name' => $request->actor_name,
        'actor_role' => $request->actor_role,
        'comment' => $request->comment,
        'signed_at' => now(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Signature enregistrée'
    ]);
}

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Signature  $signature
     * @return \Illuminate\Http\Response
     */
    public function show(Signature $signature)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Signature  $signature
     * @return \Illuminate\Http\Response
     */
    public function edit(Signature $signature)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSignatureRequest  $request
     * @param  \App\Models\Signature  $signature
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSignatureRequest $request, Signature $signature)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Signature  $signature
     * @return \Illuminate\Http\Response
     */
    public function destroy(Signature $signature)
    {
        //
    }
}
