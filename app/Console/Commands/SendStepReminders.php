<?php

namespace App\Console\Commands;

use App\Models\DocumentTypeWorkflow;
use App\Models\WorkflowInstanceStep;
use App\Notifications\StepReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class SendStepReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoie les relances aux validateurs des étapes en retard';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        
        
        // Récupère les étapes PENDING dont la date limite est dépassée
        $instanceSteps = WorkflowInstanceStep:://with('roles.user') // ou avec workflow_step_roles si statique
            with(['workflowInstance', 'workflowStep.stepRoles'])
            ->where('status', 'PENDING')
            ->where('due_date', '<', Carbon::now())
            ->get();

        foreach ($instanceSteps as $instanceStep) {
            $usersToNotify = collect();

            if ($instanceStep->user_id) {
                        $userIds = [$instanceStep->user_id];
                        continue;
                    }

                if ($instanceStep->workflowStep->assignment_mode === 'STATIC') {
                    // étape statique : on a les role_ids dans workflow_step_roles
                    $roleIds = $instanceStep->workflowStep->stepRoles->pluck('role_id'); // IDs des rôles depuis workflow
                } elseif ($instanceStep->workflowStep->assignment_mode === 'DYNAMIC') {
                    // étape dynamique
                    if ($instanceStep->user_id) {
                        $userIds = [$instanceStep->user_id];
                    } elseif ($instanceStep->role_id) {
                        //$roleIds = [$instanceStep->role_id];
                         // étape dynamique : récupérer les rôles assignés à cette instance d'étape
                        $roleIds = $instanceStep->roles()->pluck('role_id'); 
                    }
                }
            

                //    return $roleIds;
         //   $users = collect();

           
            $workflowInstance = $instanceStep->workflowInstance;
      //  $documentId = $workflowInstance->document_id;
      //  $stepName = $stepInstance->workflowStep->name;


        $workflowId = $workflowInstance->workflow_id;

        // Récupérer le type de document associé au workflow
        $documentTypeWorkflow = DocumentTypeWorkflow::where(
            "workflow_id",
            $workflowId
        )->first();

        $documentTypeId = $documentTypeWorkflow
            ? $documentTypeWorkflow->document_type_id
            : null; // null si pas trouvé

            $payload = [
    'instance_step_id' => $instanceStep->id,
    'document_id' => $instanceStep->workflowInstance->document_id,
    'workflow_instance_id' => $instanceStep->workflow_instance_id,
    'workflow_step_name' => $instanceStep->workflowStep->name,
    'role_ids' => $roleIds->toArray(),        // pour les étapes statiques ou dynamiques
    'user_id' => $instanceStep->user_id,     // pour les assignations directes
    'notification_channel' => $instanceStep->workflowStep->notification_channel ?? 'mail',
    "document_type_id" => $documentTypeId,
            ];

            // Appel microservice pour récupérer les users par role
            if ($roleIds->isNotEmpty()) {
              
            $response = Http::acceptJson()->post(config('services.user_service.base_url') . '/send-step-reminder', $payload);
   
            }


            // Incrémente le compteur de relances
            $instanceStep->increment('reminder_count');
    }
    
    

        $this->info('Relances envoyées aux validateurs en retard.');

    }
}
