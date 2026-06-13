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
        'employee_id' => 'required|integer',
        'user_id' => 'required|integer',
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
        'employee_id' => $request->employee_id,
        'user_id' => $request->user_id,
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
