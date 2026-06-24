<?php

namespace App\Services\Workflow\Event;

use App\Models\WorkflowActionStepEvent;
use App\Models\WorkflowEvent;
use App\Models\WorkflowInstanceStep;
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
        WorkflowInstanceStep $instance,
        string $actionStepId
    ) {
        $actionStepEvents = WorkflowActionStepEvent::where(
            "workflow_action_step_id",
            $actionStepId
        )
        ->with(['event.handlers'])
            ->where("is_active", true)
            ->orderBy("execution_order")
            ->get();
      
        // throw new Exception(json_encode($events), 1);

        

        $document = $this->documentClient->getDocument($documentId);

        //  $events = $currentStep->workflowStep->workflowActionStepEvents;

        // throw new Exception(json_encode($document), 1);

        foreach ($actionStepEvents as $actionStepEvent) {
            /**
             * =========================================
             * 1️⃣ Exécution métier
             * =========================================
             */
            $event = $actionStepEvent -> event;
            $handlers = $event -> handlers;

        // throw new Exception(json_encode($handlers), 1);

        foreach ($handlers as $handler) {
           
        
            $handler_class = app($handler->handler_class);

        

            $result = $handler_class->execute(
                $documentId,
                $instance,
                $document,
                $event->config ?? []
            );

        // throw new Exception(json_encode($result), 1);


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

          $this->dispatchNotifications(
        $event,
        $audiences,
        $instance,
        $documentId,
        $result
    );

        }

    return;



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
                    // 'actor' => $actor,
                    // 'mission_reference' => $mission_reference,
                    // 'period' => $period,
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

  

    private function dispatchNotifications(
    WorkflowEvent $event,
    array $audiences,
    WorkflowInstanceStep $instance,
    int $documentId,
    array $result
)
{
    $url = config(
        'services.notification_service.base_url'
    ) . '/bulk';

    foreach ($audiences as $channel => $recipients) {

        Http::acceptJson()->post(
            $url,
            [
                'code' => $event->code,

                'channel' => $channel,

                'to' => $recipients['to'] ?? [],

                'cc' => $recipients['cc'] ?? [],

                'bcc' => $recipients['bcc'] ?? [],

                'attachments' =>
                    $result['attachments'] ?? [],

                'data' => array_merge(
                    [
                        'document_id' => $documentId,

                        // 'workflow_instance_id' =>$instance->id,
                    ],
                    $result['data'] ?? []
                ),
            ]
        );
    }
}
}
