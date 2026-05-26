<?php

namespace App\Services\Workflow\Event;

use App\Models\WorkflowActionStepEvent;
use App\Services\Document\DocumentServiceClient;
use Exception;

class WorkflowEventEngine
{
    protected DocumentServiceClient $documentClient;

    public function __construct( DocumentServiceClient $documentClient){

        $this->documentClient = $documentClient;
    }

    /**
     * Point d'entrée du moteur
     */
    public function handle($documentId, $instance, $currentStep, string $actionStepId)
    {

    $events = WorkflowActionStepEvent::where(
        'workflow_action_step_id',
        $actionStepId
    )
    ->where('is_active', true)
    ->orderBy('execution_order')
    ->get();

    //  $events = $currentStep->workflowStep->workflowActionStepEvents;


    // throw new Exception(json_encode($events), 1);
    

    foreach ($events as $event) {

    $handler = app($event->handler_class);

    $handler->execute(
        $documentId,
        $instance,
        $event->config ?? []
    );
}



        return null;



        $code = $currentStep->workflowStep->code;

        // 🔥 CAS 1 : validation logistique
        // if ($code === "LOGISTICS_VALIDATION" && $action === "validate") {
        if ($code) {

            return $this->onLogisticsValidated($documentId, $instance);
        }

        // 🔥 CAS 2 : validation finale mission
        if ($code === "MISSION_FINAL_VALIDATION" && $action === "validate") {

            return $this->onMissionValidated($instance);
        }

        return null;
    }

    //  private function execute(int $documentId,  $instance)
    // {
    //     return $this->documentClient->generateMissionDocuments(
    //         $documentId,
    //         $instance->id,
    //         "logistics_validated"
    //     );
    // }

    /**
     * LOGISTIQUE VALIDÉE
     */
    private function onLogisticsValidated(int $documentId,  $instance)
    {
        return $this->documentClient->generateMissionDocuments(
            $documentId,
            $instance->id,
            "logistics_validated"
        );
    }

    /**
     * MISSION COMPLÈTEMENT VALIDÉE
     */
    private function onMissionValidated($instance)
    {
        return $this->documentClient->generateMissionDocuments(
            "",
            $instance->id,
            "mission_completed"
        );
    }
}