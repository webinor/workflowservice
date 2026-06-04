<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSignatureTypeRequest;
use App\Http\Requests\UpdateSignatureTypeRequest;
use App\Models\SignatureType;

class SignatureTypeController extends Controller
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
     * @param  \App\Http\Requests\StoreSignatureTypeRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreSignatureTypeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\SignatureType  $signatureType
     * @return \Illuminate\Http\Response
     */
    public function show(SignatureType $signatureType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\SignatureType  $signatureType
     * @return \Illuminate\Http\Response
     */
    public function edit(SignatureType $signatureType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateSignatureTypeRequest  $request
     * @param  \App\Models\SignatureType  $signatureType
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateSignatureTypeRequest $request, SignatureType $signatureType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\SignatureType  $signatureType
     * @return \Illuminate\Http\Response
     */
    public function destroy(SignatureType $signatureType)
    {
        //
    }
}
