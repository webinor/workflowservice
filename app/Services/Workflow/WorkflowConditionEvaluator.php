<?php 

namespace App\Services\Workflow;

use App\Models\WorkflowCondition;

class WorkflowConditionEvaluator {



public function matches(
    WorkflowCondition $condition,
    array $document
): bool{

return true;
}


}