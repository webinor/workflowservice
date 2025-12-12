<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkflowRequest extends FormRequest
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
            'name' => 'required|string',
            'document_type' => 'nullable|integer',
            'recipientMode' => 'nullable|string',
            'steps' => 'array',
            //'existsTarget'=>'string'
        ];
    }


    public function withValidator($validator)
{
    $validator->after(function ($validator) {

        $steps = $this->input('steps', []);

        foreach ($steps as $index => $step) {

            if (
                isset($step['assignationMode']) &&
                $step['assignationMode'] === 'DYNAMIC'
            ) {
                if (empty($step['assignmentRule'])) {
                    $validator->errors()->add(
                        "steps.$index.assignmentRule",
                        "The assignmentRule field is required when assignationMode is dynamic."
                    );
                }
            }
        }
    });
}

}
