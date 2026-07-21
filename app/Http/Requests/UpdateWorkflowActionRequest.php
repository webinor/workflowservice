<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkflowActionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'actionName' => 'required|string|max:255',
            'actionLabel' => 'required|string|max:255',
            'workflow_step_id' => 'required|exists:workflow_steps,id',
            'transaction_type_code' => 'nullable|string|max:50',
            'action_step_message' => 'required|string|max:100',
            'workflow_action_type_id' => 'required|exists:workflow_action_types,id',
            'permission_required' => 'required|string|max:255',
        ];
    }
}