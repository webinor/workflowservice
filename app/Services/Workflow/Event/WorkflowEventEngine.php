<?php

namespace App\Services\Workflow\Event;


use App\Services\Document\DocumentServiceClient;

class WorkflowEventEngine
{
    protected DocumentServiceClient $documentClient;

    public function __construct( DocumentServiceClient $documentClient){

        $this->documentClient = $documentClient;
    }

    /**
     * Point d'entrée du moteur
     */
    public function handle($documentId, $instance, $currentStep, string $action)
    {
        $code = $currentStep->workflowStep->code;

        // 🔥 CAS 1 : validation logistique
        // if ($code === "LOGISTICS_VALIDATION" && $action === "validate") {
        if (true) {

            return $this->onLogisticsValidated($documentId, $instance);
        }

        // 🔥 CAS 2 : validation finale mission
        if ($code === "MISSION_FINAL_VALIDATION" && $action === "validate") {

            return $this->onMissionValidated($instance);
        }

        return null;
    }

    /**
     * LOGISTIQUE VALIDÉE
     */
    private function onLogisticsValidated($documentId, $instance)
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