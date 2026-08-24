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
    protected RecipientResolver $recipientResolver;

    public function __construct(
    DocumentServiceClient $documentClient,
    WorkflowAudienceResolver $workflow_audience_resolver,
    RecipientResolver $recipientResolver
) {
    $this->documentClient = $documentClient;
    $this->workflow_audience_resolver = $workflow_audience_resolver;
    $this->recipientResolver = $recipientResolver;
}

    /**
     * Point d'entrée du moteur
     */
    public function handle(
        string $documentUuid,
        WorkflowInstanceStep $instance,
        $actionStepId = null,
        $StepId = null,
        string $eventCode = ""
    ) {

        $actionStepEvents = WorkflowActionStepEvent::where(
            "workflow_action_step_id",
            $actionStepId
        )
            ->with(["event.handlers"])
            ->where("is_active", true)
            ->orderBy("execution_order")
            ->get();

        $document = $this->documentClient->getDocument($documentUuid);

        foreach ($actionStepEvents as $actionStepEvent) {
            /**
             * =========================================
             * 1️⃣ Exécution métier
             * =========================================
             */
            $event = $actionStepEvent->event;
            $handlers = $event->handlers;

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

                $recipients = $this->recipientResolver->resolve(
                    $audiences
                );
          



            //  throw new Exception(json_encode($recipients), 1);
            

            foreach ($handlers as $handler) {

                $handler_class = app($handler->handler_class);

                $result = $handler_class->execute(
                    $documentUuid,
                    $instance,
                    $document,
                    $event->config ?? []
                );

           

                $this->dispatchNotifications(
                    $event,
                    $audiences,
                    $instance,
                    $documentUuid,
                    $result,
                    $recipients,
                    $document
                );
            }

            return;

        }

    }

    private function dispatchNotifications(
        WorkflowEvent $event,
        array $audiences,
        WorkflowInstanceStep $instance,
        string $documentUuid,
        array $result,
        array $recipients,
        array $document
    ) {
                    $url = config("services.notification_service.base_url") . "/bulk";

                    // throw new Exception(json_encode($recipients), 1);

                

                    // throw new Exception(json_encode($data), 1);


                

                    foreach ($audiences as $channel => $audience) {


                    $recipient = $recipients[$channel]["to"][0] ?? [];

            $data = 
                [
                    "document_uuid" => $documentUuid,

                    "civilite" => $recipient["civilite"] ?? "",

                    "nom_complet" => $recipient["nom_complet"] ?? "",

                    "reference" => $document["reference"] ?? "",
                ];


                        Http::acceptJson()->post($url, [
                            "code" => $event->code,

                            "channel" => $channel,

                            "to" => $audience["to"] ?? [],

                            "cc" => $audience["cc"] ?? [],

                            "bcc" => $audience["bcc"] ?? [],

                            "attachments" => $result["attachments"] ?? [],

                            "data" => array_merge(
                                $data,
                                $result["data"] ?? []
                            ),
                        ]);
                    }
    }
}
