<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowActionRequest extends FormRequest
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
            'actionName' => 'required|string|max:255',
            'actionLabel' => 'required|string|max:255',
            'workflow_id' => 'required|exists:workflows,id',
            'workflow_step_id' => 'required|exists:workflow_steps,id',
            'permission_required' => 'nullable|string|max:255',
        ];
    }
}
