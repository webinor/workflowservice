<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowInstanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "workflow_id" => "required|integer",

            "document_id" => "required|integer",
            "document_uuid" => "required|string",

            "document_type_id" => "required|integer",
            "document_type_slug" => "required|string|max:255",
            "document_type_version" => "required|string|max:255",

            "department_id" => "nullable|integer",
            "role_id" => "nullable|integer",

            "steps" => "required|array",

            "current_step_id" => "nullable|integer",

            "status" => "required|string",

            "created_by" => "required",
        ];
    }
}