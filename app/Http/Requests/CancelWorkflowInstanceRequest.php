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
            "comment" => [
                "required",
                "string",
                "max:500",
            ],
        ];
    }


    public function messages()
    {
        return [
            "comment.required" =>
                "Le motif d'annulation est obligatoire.",

            "comment.max" =>
                "Le motif d'annulation ne doit pas dépasser 500 caractères.",
        ];
    }
}