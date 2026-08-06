<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelWorkflowInstanceRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }


    public function rules()
    {
        return [
            "reason" => [
                "required",
                "string",
                "max:500",
            ],
        ];
    }


    public function messages()
    {
        return [
            "reason.required" =>
                "Le motif d'annulation est obligatoire.",

            "reason.max" =>
                "Le motif d'annulation ne doit pas dépasser 500 caractères.",
        ];
    }
}