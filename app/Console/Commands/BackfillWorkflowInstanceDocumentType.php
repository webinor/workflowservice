<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\WorkflowInstance;
use App\Services\Document\DocumentServiceClient;
use Throwable;

class BackfillWorkflowInstanceDocumentType extends Command
{
    protected $signature = 'workflow:backfill-document-type 
                            {--dry-run : Affiche uniquement sans modifier}
                            {--chunk=100 : Nombre d\'éléments par batch}';

    protected $description = 'Ajoute les informations de type document aux workflow instances existantes';


    
    private DocumentServiceClient $documentClient;


    public function __construct(
        DocumentServiceClient $documentClient
    ) {
        parent::__construct();

        $this->documentClient = $documentClient;
    }


    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');


        $total = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;


        $this->info(
            $dryRun
                ? 'MODE DRY-RUN activé : aucune modification'
                : 'MODE EXECUTION activé'
        );


        if (!$dryRun) {

            if (!$this->confirm(
                'Cette opération va modifier les workflow_instances. Continuer ?'
            )) {
                $this->warn('Opération annulée.');

                return Command::SUCCESS;
            }
        }


        WorkflowInstance::query()
            ->whereNull('document_type_slug')
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function ($instances) use (
                    &$total,
                    &$updated,
                    &$skipped,
                    &$errors,
                    $dryRun
                ) {


                    $total += $instances->count();


                    $documentIds = $instances
                        ->pluck('document_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();


                    if (empty($documentIds)) {
                        return;
                    }


                    try {

                        $documents = collect(
                            $this->documentClient
                                ->getDocumentTypesByIds($documentIds, config('services.document_service.token'))
                        );


                    } catch (Throwable $e) {

                        $errors += count($instances);


                        Log::error(
                            'Erreur récupération document-service',
                            [
                                'document_ids' => $documentIds,
                                'message' => $e->getMessage()
                            ]
                        );


                        $this->error(
                            'Erreur document-service : '.$e->getMessage()
                        );

                        return;
                    }



                    DB::beginTransaction();


                    try {


                        foreach ($instances as $instance) {


                            $document = $documents->firstWhere(
                                'id',
                                $instance->document_id
                            );


                            if (!$document) {

                                $skipped++;

                                $this->warn(
                                    "Document introuvable : {$instance->document_id}"
                                );

                                continue;
                            }



                            $data = [

                                'document_type_id' =>
                                    $document['document_type_id'] ?? null,

                                'document_type_slug' =>
                                    $document['document_type_slug'] ?? null,

                                'document_type_version' =>
                                    $document['document_type_version'] ?? null,
                            ];



                            if ($dryRun) {

                                $this->line(
                                    "[DRY] Workflow {$instance->id} => "
                                    .json_encode($data)
                                );

                                continue;
                            }



                            /*
                             * Sécurité :
                             * on ne remplace jamais une valeur existante
                             */
                            if (
                                empty($instance->document_type_slug)
                            ) {

                                $instance->update($data);

                                $updated++;

                            } else {

                                $skipped++;

                            }

                        }


                        DB::commit();


                    } catch (Throwable $e) {

                        DB::rollBack();


                        $errors += count($instances);


                        Log::error(
                            'Erreur update workflow instance',
                            [
                                'message' => $e->getMessage()
                            ]
                        );


                        $this->error(
                            $e->getMessage()
                        );
                    }



                    $this->info(
                        sprintf(
                            'Batch terminé | Total: %d | Modifiés: %d | Ignorés: %d | Erreurs: %d',
                            $total,
                            $updated,
                            $skipped,
                            $errors
                        )
                    );

                }
            );


        $this->newLine();

        $this->info('==============================');
        $this->info('Backfill terminé');
        $this->info("Total analysé : {$total}");
        $this->info("Modifié       : {$updated}");
        $this->info("Ignoré        : {$skipped}");
        $this->info("Erreur        : {$errors}");
        $this->info('==============================');


        return Command::SUCCESS;
    }
}