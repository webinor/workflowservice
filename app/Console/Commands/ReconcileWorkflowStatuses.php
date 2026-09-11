<?php

namespace App\Console\Commands;

use App\Models\WorkflowInstance;
use App\Services\Workflow\WorkflowStatusReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileWorkflowStatuses extends Command
{
    protected $signature = 'workflow:reconcile-statuses
                            {--execute : Autorise réellement les modifications}
                            {--document-type= : taxi_paper, fee_note ou regularization_sheet}
                            {--document-id= : Traiter uniquement ce document}
                            {--limit=1000 : Nombre maximum d\'instances à analyser}';

    protected $description =
        'Réconcilie les statuts des étapes workflow à partir des signatures';

    protected WorkflowStatusReconciliationService $workflowStatusReconciliationService;

    public function __construct(
        WorkflowStatusReconciliationService $workflowStatusReconciliationService
    ) {
        parent::__construct();

        $this->workflowStatusReconciliationService =
            $workflowStatusReconciliationService;
    }

    public function handle()
    {
        $execute =
            (bool) $this->option('execute');

        $documentType =
            $this->option('document-type');

        $documentId =
            $this->option('document-id');

        $limit =
            (int) $this->option('limit');

        /*
        |--------------------------------------------------------------------------
        | Validation limit
        |--------------------------------------------------------------------------
        */

        if ($limit <= 0) {

            $this->error(
                'Le --limit doit être supérieur à 0.'
            );

            return 1;
        }

        /*
        |--------------------------------------------------------------------------
        | Types autorisés
        |--------------------------------------------------------------------------
        */

        $allowedTypes = [
            'taxi_paper',
            'fee_note',
            'regularization_sheet',
        ];

        if (
            $documentType
            && !in_array(
                $documentType,
                $allowedTypes
            )
        ) {

            $this->error(
                'Type de document invalide.'
            );

            $this->line(
                'Valeurs autorisées : '
                . implode(', ', $allowedTypes)
            );

            return 1;
        }

        /*
        |--------------------------------------------------------------------------
        | Mode
        |--------------------------------------------------------------------------
        */

        if ($execute) {

            $this->warn(
                'MODE EXECUTION : les statuts pourront être modifiés.'
            );

        } else {

            $this->info(
                'MODE SAFE : aucune modification ne sera effectuée.'
            );
        }

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Query
        |--------------------------------------------------------------------------
        */

        $query = WorkflowInstance::query()
            ->whereIn(
                'document_type_relation_name',
                $allowedTypes
            );

        if ($documentType) {

            $query->where(
                'document_type_relation_name',
                $documentType
            );
        }

        if ($documentId) {

            $query->where(
                'document_id',
                $documentId
            );
        }

        $instances = $query
            ->orderBy('id')
            ->limit($limit)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Rien à traiter
        |--------------------------------------------------------------------------
        */

        if ($instances->isEmpty()) {

            $this->info(
                'Aucune instance workflow à analyser.'
            );

            return 0;
        }

        $this->info(
            $instances->count()
            . ' instance(s) seront analysées.'
        );

        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Counters
        |--------------------------------------------------------------------------
        */

        $analyzed = 0;
        $updated = 0;
        $unchanged = 0;
        $noSignature = 0;
        $noInstanceStep = 0;
        $noTransactionType = 0;
        $noMapping = 0;
        $ignored = 0;
        $errors = 0;

        /*
        |--------------------------------------------------------------------------
        | Traitement
        |--------------------------------------------------------------------------
        */

        foreach ($instances as $instance) {

            $analyzed++;

            try {

                $result =
                    $this->workflowStatusReconciliationService
                        ->reconcile(
                            $instance,
                            $execute
                        );

                switch ($result['reason']) {

                    /*
                    |--------------------------------------------------------------------------
                    | Mise à jour réelle
                    |--------------------------------------------------------------------------
                    */

                    case 'UPDATED':

                        $updated++;

                        $this->line(
                            sprintf(
                                '<info>[UPDATED]</info> '
                                . '#%d | doc #%d | %s | %s → %s | transaction=%s',
                                $instance->id,
                                $instance->document_id,
                                $instance->document_type_relation_name,
                                $result['old_status'],
                                $result['new_status'],
                                $result['transaction_type_code']
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | SAFE MODE
                    |--------------------------------------------------------------------------
                    */

                    case 'DRY_RUN':

                        $this->line(
                            sprintf(
                                '<comment>[WOULD UPDATE]</comment> '
                                . '#%d | doc #%d | %s | %s → %s | transaction=%s',
                                $instance->id,
                                $instance->document_id,
                                $instance->document_type_relation_name,
                                $result['old_status'],
                                $result['new_status'],
                                $result['transaction_type_code']
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Déjà correct
                    |--------------------------------------------------------------------------
                    */

                    case 'ALREADY_UPDATED':

                        $unchanged++;

                        $this->line(
                            sprintf(
                                '[OK] #'
                                . $instance->id
                                . ' | doc #'
                                . $instance->document_id
                                . ' | status='
                                . $result['new_status']
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Pas de signature
                    |--------------------------------------------------------------------------
                    */

                    case 'NO_SIGNATURE_FOUND':

                        $noSignature++;

                        $this->line(
                            sprintf(
                                '<comment>[NO SIGNATURE]</comment> '
                                . '#%d | doc #%d | %s',
                                $instance->id,
                                $instance->document_id,
                                $instance->document_type_relation_name
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Pas d'instance step
                    |--------------------------------------------------------------------------
                    */

                    case 'NO_INSTANCE_STEP_FOUND':

                        $noInstanceStep++;

                        $this->line(
                            sprintf(
                                '<comment>[NO STEP]</comment> '
                                . '#%d | doc #%d',
                                $instance->id,
                                $instance->document_id
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Pas de transaction type
                    |--------------------------------------------------------------------------
                    */

                    case 'NO_TRANSACTION_TYPE_CODE':

                        $noTransactionType++;

                        $this->line(
                            sprintf(
                                '<comment>[NO TRANSACTION TYPE]</comment> '
                                . '#%d | doc #%d',
                                $instance->id,
                                $instance->document_id
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Pas de mapping
                    |--------------------------------------------------------------------------
                    */

                    case 'NO_STATUS_MAPPING':

                        $noMapping++;

                        $this->line(
                            sprintf(
                                '<comment>[NO MAPPING]</comment> '
                                . '#%d | doc #%d | transaction=%s',
                                $instance->id,
                                $instance->document_id,
                                $result['transaction_type_code']
                            )
                        );

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Type non supporté
                    |--------------------------------------------------------------------------
                    */

                    case 'DOCUMENT_TYPE_NOT_SUPPORTED':

                        $ignored++;

                        break;

                    default:

                        $ignored++;

                        break;
                }

            } catch (\Throwable $e) {

                $errors++;

                Log::error(
                    'Erreur lors de la réconciliation des statuts workflow.',
                    [
                        'workflow_instance_id' =>
                            $instance->id,

                        'document_id' =>
                            $instance->document_id,

                        'document_type_relation_name' =>
                            $instance->document_type_relation_name,

                        'error' =>
                            $e->getMessage(),

                        'trace' =>
                            $e->getTraceAsString(),
                    ]
                );

                $this->error(
                    sprintf(
                        '[ERROR] Instance #%d : %s',
                        $instance->id,
                        $e->getMessage()
                    )
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Résumé
        |--------------------------------------------------------------------------
        */

        $this->newLine();

        $this->info(
            '===== RÉSUMÉ ====='
        );

        $this->line(
            'Analysées              : '
            . $analyzed
        );

        $this->line(
            'Modifiées              : '
            . $updated
        );

        $this->line(
            'Déjà correctes         : '
            . $unchanged
        );

        $this->line(
            'Sans signature         : '
            . $noSignature
        );

        $this->line(
            'Sans instance step     : '
            . $noInstanceStep
        );

        $this->line(
            'Sans transaction type  : '
            . $noTransactionType
        );

        $this->line(
            'Sans mapping            : '
            . $noMapping
        );

        $this->line(
            'Ignorées               : '
            . $ignored
        );

        $this->line(
            'Erreurs                : '
            . $errors
        );

        /*
        |--------------------------------------------------------------------------
        | Message SAFE
        |--------------------------------------------------------------------------
        */

        if (!$execute) {

            $this->newLine();

            $this->warn(
                'SAFE MODE : aucune modification n\'a été effectuée.'
            );

            $this->line(
                'Pour appliquer les changements :'
            );

            $this->line(
                'php artisan workflow:reconcile-statuses --execute'
            );
        }

        return $errors > 0
            ? 1
            : 0;
    }
}