<?php

namespace App\Services\Workflow\Handlers\Leave;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;
use App\Services\Leave\LeaveBalanceService;

class DeductLeaveDaysHandler implements WorkflowEventHandlerInterface
{


//  private LeaveBalanceService $leaveBalanceService;
    protected DocumentServiceClient $documentClient;

    public function __construct(
        DocumentServiceClient $documentClient
        // LeaveBalanceService $leaveBalanceService

    ) {

        $this->documentClient = $documentClient;
    }

    //     public function handle(array $document): void
    // {
    //     $this->leaveBalanceService->applyLeaveDeduction($document);
    // }

    public function execute(
        int $documentId,
        $instance,
        array $documentData,
        array $config = []
    ): array {


        $template = $config['template']
            ?? 'logistics_validated';

        $result = $this->documentClient
            ->deductLeaveDays(
                $documentId,
                $instance->id,
            );

        // $dates = $this->buildMissionDates($documentData["mission"]);


        return [

            'data' => [

                'actor' =>
                    $documentData["actor_details"]['nom'] ?? '',

                'mission_reference' =>
                    $documentData["mission"]['code'] ?? '',

                'destination' =>
                    $documentData["mission"]['destination'] ?? '',

                // 'period' =>   $dates['period'] ?? '',
            ],

            'attachments' =>
                $result['attachments'] ?? [],
        ];
    }
}