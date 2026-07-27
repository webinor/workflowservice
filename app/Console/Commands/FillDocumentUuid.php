<?php

namespace App\Console\Commands;

use App\Models\Signature;
use App\Models\WorkflowInstance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FillDocumentUuid extends Command
{
    protected $signature = 'workflow:fill-document-uuid
                            {--exec : Execute database updates}';

    protected $description = 'Remplit document_uuid dans workflow_instances et signatures depuis document-service';

    public function handle()
    {
        $execute = $this->option('exec');

        if ($execute) {
            $this->warn('MODE EXECUTION');
        } else {
            $this->info('MODE SAFE (aucune modification)');
        }

        WorkflowInstance::query()
            ->whereNull('document_uuid')
            ->chunkById(1000, function ($instances) use ($execute) {

                $documentIds = $instances
                    ->pluck('document_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (empty($documentIds)) {
                    return;
                }

                $response = Http::withHeaders([
                    'X-Service-Token' => config('services.document_service.token'),
                ])
                    ->acceptJson()
                    ->get(
                        config('services.document_service.base_url') . '/by-ids',
                        [
                            'from' => 'command',
                            'ids' => $documentIds,
                        ]
                    );

                if (!$response->successful()) {

                    $this->error($response->body());

                    return;
                }

                $documents = collect($response->json());

                $this->info(
                    'Documents trouvés : ' . $documents->count()
                );

                $uuidMap = $documents->mapWithKeys(function ($document) {
                    return [
                        $document['id'] => $document['uuid'],
                    ];
                });

                // Nombre de signatures par document (uniquement pour le mode SAFE)
                $signatureMap = Signature::query()
                    ->whereIn('document_id', $documentIds)
                    ->get(['id', 'document_id'])
                    ->groupBy('document_id');

                foreach ($instances as $instance) {

                    if (!$uuidMap->has($instance->document_id)) {

                        $this->warn(
                            "Document introuvable : {$instance->document_id}"
                        );

                        continue;
                    }

                    $uuid = $uuidMap[$instance->document_id];

                    if ($execute) {

                        // WorkflowInstance
                        $instance->update([
                            'document_uuid' => $uuid,
                        ]);

                        // Signatures
                        $updatedSignatures = Signature::query()
                            ->where('document_id', $instance->document_id)
                            ->update([
                                'document_uuid' => $uuid,
                            ]);

                        $this->info(
                            "UPDATED workflow={$instance->id} "
                            . "document={$instance->document_id} "
                            . "uuid={$uuid} "
                            . "signatures={$updatedSignatures}"
                        );

                    } else {

                        $signatureCount = ($signatureMap[$instance->document_id] ?? collect())
                            ->count();

                        $this->line(
                            "[SAFE] "
                            . "workflow={$instance->id} "
                            . "document={$instance->document_id} "
                            . "uuid={$uuid} "
                            . "signatures={$signatureCount}"
                        );

                    }
                }
            });

        $this->newLine();

        if ($execute) {
            $this->info('Migration terminée.');
        } else {
            $this->info('Simulation terminée. Utilisez --exec pour appliquer.');
        }

        return Command::SUCCESS;
    }
}