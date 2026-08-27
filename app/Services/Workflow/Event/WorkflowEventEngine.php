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
     * ============================================================
     * ÉVÉNEMENTS LIÉS À UNE ACTION DE WORKFLOW
     * ============================================================
     */
    public function handleActionStep(
        string $documentUuid,
        WorkflowInstanceStep $instance,
        int $actionStepId,
        array $context = []
    ): void {

        $actionStepEvents =
            WorkflowActionStepEvent::query()
                ->where(
                    "workflow_action_step_id",
                    $actionStepId
                )
                ->with([
                    "event.handlers"
                ])
                ->where("is_active", true)
                ->orderBy("execution_order")
                ->get();

        if ($actionStepEvents->isEmpty()) {
            return;
        }

        foreach ($actionStepEvents as $actionStepEvent) {

            $event = $actionStepEvent->event;

            $this->executeEvent(
                $event,
                $documentUuid,
                $instance,
                $context
            );
        }
    }


    /**
     * ============================================================
     * DÉCLENCHEMENT DIRECT D'UN ÉVÉNEMENT
     * ============================================================
     */
    public function handleEvent(
        string $documentUuid,
        string $eventCode,
        ?WorkflowInstanceStep $instance=null,
        array $context = []
    ): void {

        $event = WorkflowEvent::query()
            ->where("code", $eventCode)
            ->where("enabled", true)
            ->with([
                "handlers"
            ])
            ->first();

        if (!$event) {

            throw new \Exception(
                "Événement workflow introuvable : {$eventCode}"
            );
        }

        $this->executeEvent(
             $event,
             $documentUuid,
             $instance,
             $context
        );
    }


    /**
     * ============================================================
     * MOTEUR D'EXÉCUTION COMMUN
     * ============================================================
     */
    protected function executeEvent(
        WorkflowEvent $event,
        string $documentUuid,
        WorkflowInstanceStep $instance,
        array $context = []
    ): void {


                    if ($event->handlers->count() == 0) {

            throw new \Exception(
                "Handlers introuvable : {$event->code}"
            );
        }

        /*
         * ============================================
         * DOCUMENT
         * ============================================
         */

        $document =
            $this->documentClient->getDocument(
                $documentUuid
            );


        /*
         * ============================================
         * AUDIENCES
         * ============================================
         */

        $audiences =
            $this->workflow_audience_resolver->resolve(
                $event,
                $instance,
                $document,
                $context
            );


        /*
         * ============================================
         * DESTINATAIRES
         * ============================================
         */

        $recipients =
            $this->recipientResolver->resolve(
                $audiences
            );


        /*
         * ============================================
         * HANDLERS
         * ============================================
         */

        foreach ($event->handlers as $handler) {

            $handlerClass =  app($handler->handler_class);


            $result =
                $handlerClass->execute(
                    $documentUuid,
                    $instance,
                    $document,
                    array_merge(
                        $handler->config ?? [],
                        $context
                    )
                );


            /*
             * ========================================
             * NOTIFICATIONS
             * ========================================
             */

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
