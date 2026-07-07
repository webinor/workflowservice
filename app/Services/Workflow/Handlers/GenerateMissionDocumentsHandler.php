<?php

namespace App\Services\Workflow\Handlers;

use App\Contracts\WorkflowEventHandlerInterface;
use App\Services\Document\DocumentServiceClient;
use Carbon\Carbon;

class GenerateMissionDocumentsHandler
    implements WorkflowEventHandlerInterface
{
    protected DocumentServiceClient $documentClient;

    public function __construct(
        DocumentServiceClient $documentClient
    ) {
        $this->documentClient = $documentClient;
    }

    public function execute(
        int $documentId,
        $instance,
        array $documentData,
        array $config = []
    ): array {


        $template = $config['template']
            ?? 'logistics_validated';

        $result = $this->documentClient
            ->generateMissionDocuments(
                $documentId,
                $instance->id,
                $template
            );

        $dates = $this->buildMissionDates($documentData["mission"]);

// $dates['expected_departure'];
// $dates['expected_return'];
// $dates['period'];

        return [

            'data' => [

                'actor' =>
                    $documentData["actor_details"]['nom'] ?? '',

                'mission_reference' =>
                    $documentData["mission"]['code'] ?? '',

                'destination' =>
                    $documentData["mission"]['destination'] ?? '',

                'period' =>
                    $dates['period'] ?? '',
            ],

            'attachments' =>
                $result['attachments'] ?? [],
        ];
    }


private function buildMissionDates(array $mission): array
{
    $expectedDeparture = Carbon::parse(
        $mission['departure_date_base_planned']// . ' ' .
        //$mission['departure_time_base_planned']
    );

    $expectedReturn = Carbon::parse(
        $mission['arrival_date_base_planned']// . ' ' .
        //$mission['arrival_time_base_planned']
    );

    return [
        'expected_departure' => $expectedDeparture,
        'expected_return' => $expectedReturn,
        'period' => 'Du '. $expectedDeparture->format('d/m/Y')
            . ' au '
            . $expectedReturn->format('d/m/Y'),
    ];
}
}