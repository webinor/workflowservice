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

            // Conditions d'activation du bouton.
            'requirements' => 'sometimes|nullable|array',
            'requirements.conditions' => 'required_with:requirements|array|min:1',
            'requirements.conditions.*.rules' => 'required|array|min:1',
            'requirements.conditions.*.rules.*.type' => 'required|string|max:100',
            'requirements.conditions.*.rules.*.modal_open' => 'sometimes|boolean',
            'requirements.conditions.*.operator' => 'required|in:AND,OR',

            // Conditions de visibilité du bouton.
            'visibility_requirements' => 'sometimes|nullable|array',
            'visibility_requirements.conditions' => 'required_with:visibility_requirements|array|min:1',
            'visibility_requirements.conditions.*.rules' => 'required|array|min:1',
            'visibility_requirements.conditions.*.rules.*.type' => 'required|string|max:100',
            'visibility_requirements.conditions.*.rules.*.modal_open' => 'sometimes|boolean',
            'visibility_requirements.conditions.*.operator' => 'required|in:AND,OR',
        ];
    }
}