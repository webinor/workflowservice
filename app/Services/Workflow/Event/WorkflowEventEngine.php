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
        $documentUuid,
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
      
        // throw new Exception(json_encode($actionStepEvents), 1);

        

        $document = $this->documentClient->getDocument($documentUuid);



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


        foreach ($handlers as $handler) {
           
        
            $handler_class = app($handler->handler_class);

        

            $result = $handler_class->execute(
                $documentUuid,
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
        $documentUuid,
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
                    'document_uuid' => $documentUuid,
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
    int $documentUuid,
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
                        'document_uuid' => $documentUuid,

                        // 'workflow_instance_id' =>$instance->id,
                    ],
                    $result['data'] ?? []
                ),
            ]
        );
    }
}
}
