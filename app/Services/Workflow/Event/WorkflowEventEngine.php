<?php

namespace App\Services\Workflow\Event;

use App\Models\WorkflowActionStepEvent;
use App\Services\Document\DocumentServiceClient;
use Exception;
use Illuminate\Support\Facades\Http;

class WorkflowEventEngine
{
    protected DocumentServiceClient $documentClient;
    protected WorkflowAudienceResolver $workflow_audience_resolver;

    public function __construct(
        DocumentServiceClient $documentClient,
        WorkflowAudienceResolver $workflow_audience_resolver
    ) {
        $this->documentClient = $documentClient;
        $this->workflow_audience_resolver = $workflow_audience_resolver;
    }

    /**
     * Point d'entrée du moteur
     */
    public function handle(
        $documentId,
        $instance,
        $currentStep,
        string $actionStepId
    ) {
        $events = WorkflowActionStepEvent::where(
            "workflow_action_step_id",
            $actionStepId
        )
            ->where("is_active", true)
            ->orderBy("execution_order")
            ->get();

        // throw new Exception(json_encode($actionStepId), 1);
        // throw new Exception(json_encode($events), 1);
        

        $document = $this->documentClient->getDocument($documentId);

         $events = $currentStep->workflowStep->workflowActionStepEvents;

        // throw new Exception(json_encode($events), 1);

        foreach ($events as $event) {
            /**
             * =========================================
             * 1️⃣ Exécution métier
             * =========================================
             */
            $handler = app($event->handler_class);

            $result = $handler->execute(
                $documentId,
                $instance,
                $event->config ?? []
            );

            /**
             * =========================================
             * 2️⃣ Résolution audiences
             * =========================================
             */
            $audiences = $this->workflow_audience_resolver->resolve(
                $event,
                $instance,
                $document
            );

        // throw new Exception(json_encode($audiences), 1);


            /**
             * =========================================
             * 3️⃣ Dispatch notifications
             * =========================================
             */
            $url = config("services.notification_service.base_url") . "/bulk";
            
            
            foreach ($audiences as $channel => $recipients) {

    $response = Http::acceptJson()->post(
        $url,
        [

            'code' => $event->code,

            'channel' => $channel,

            'to' => $recipients['to'] ?? [],

            'cc' => $recipients['cc'] ?? [],

            'bcc' => $recipients['bcc'] ?? [],

            'attachments' => $result['attachments'] ?? [],

            'data' => array_merge(
                [
                    'document_id' => $documentId,
                    'workflow_instance_id' => $instance->id,
                ],
                $result ?? []
            ),
        ]
    );

    if (!$response->successful()) {

        throw new Exception(
            "Notification service error : "
            . $response->body()
        );
    }
}
        }

        return ["ok"];
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
    private function onLogisticsValidated(int $documentId, $instance)
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
