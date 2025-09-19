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
            "department_id" => "nullable|integer",
            "role_id" => "nullable|integer",
            "steps" => "required|array",
            "current_step_id" => "nullable|integer",
            "status" => "required|string",
            "created_by" => "required",
        ];
    }
}
