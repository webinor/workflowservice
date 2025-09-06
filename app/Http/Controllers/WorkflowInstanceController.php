<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowInstanceRequest;
use App\Http\Requests\UpdateWorkflowInstanceRequest;
use App\Models\WorkflowInstance;
use App\Models\WorkflowInstanceStep;
use App\Services\ResolveDepartmentValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowInstanceController extends Controller
{

    use ResolveDepartmentValidator;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function getCurrentStep(WorkflowInstance $instance): ?WorkflowInstanceStep
    {
        return $instance->instance_steps()
            ->where('status', 'PENDING')
            ->orderBy('position', 'asc')
            ->first();
    }

    public function validateStep(Request $request, $documentId)
{
    
    DB::beginTransaction();
  
    $user = $request->get('user');

    // 3️⃣ Récupérer l'instance globale
  $instance = WorkflowInstance::whereDocumentId($documentId)->first();
   
      // 1️⃣ Récupérer l'étape en cours
      $currentStep = $this->getCurrentStep($instance);

  // 2️⃣ Marquer l’étape comme validée
  $currentStep->update([
      'status' => 'COMPLETE',
      'user_id' => $user['id'],
      'validated_at' => now(),
  ]);

  

  // 4️⃣ Déterminer l’étape suivante
  $nextStep = WorkflowInstanceStep::where('workflow_instance_id', $instance->id)
      ->where('position', '>', $currentStep->position)
      ->orderBy('position', 'asc')
      ->first();

  if ($nextStep) {
      // Activer la prochaine étape
      $nextStep->update([
          'status' => 'PENDING'
      ]);

      // Mettre à jour l'instance comme "toujours en cours"
      $instance->update([
          'status' => 'PENDING'
      ]);
  } else {
      // Pas d’étape suivante → Workflow terminé
      $instance->update([
          'status' => 'COMPLETE'
      ]);
  }


  DB::commit();

    return response()->json(['success'=>true,  'message' => 'Étape validée avec succès', 'currentStep'=>$currentStep]);

    try {
        
    } catch (\Throwable $th) {
        DB::rollBack();
        throw $th;
    }
}


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {




        /*
        DB::transaction(function() use ($step, $userId, $newStatus) {
            // 1. Récupérer l'ancien statut
            $oldStatus = $step->status;
        
            // 2. Mettre à jour l'étape
            $step->status = $newStatus;
            $step->save();
        
            // 3. Ajouter un historique
            DB::table('workflow_instance_steps_history')->insert([
                'workflow_instance_step_id' => $step->id,
                'changed_by' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });*/
        
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowInstanceRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowInstanceRequest $request)
    {
        

        try {

         
            

            DB::beginTransaction();

            $validated = $request->validated();
            $userConnected = $validated['created_by'];
            $STATUS_NOT_STARTED = 'NOT_STARTED';
$STATUS_PENDING     = 'PENDING';
$STATUS_COMPLETE    = 'COMPLETE';

            $workflowInstance = WorkflowInstance::create([
                'workflow_id' => $validated['workflow_id'],
                'document_id' => $validated['document_id'],
                'status' => 'PENDING',

            ]);


      //  $department_position = $this->resolveDepartmentValidator($validated["department_id"]) ;
       // return  $role = $this->resolveRoleValidator($department_position['position']['name'])['results'] ;
  
  

          //  return $validated['steps'];

                       // 4️⃣ Créer les étapes de l'instance
   // return $step;
   $userRoleId = $userConnected['role_id']; // ou $userConnected->role_id selon ton modèle

   foreach ($validated['steps'] as $index => $step) {

    if ($index === 0 && $step['role_id'] === $userRoleId) {
        $initialStatus = $STATUS_COMPLETE; // l'utilisateur réalise l'étape dès la création
        $stepUserId = $userConnected['id'];
    } elseif ($index === 0) {
        $initialStatus = $STATUS_PENDING; // première étape à réaliser par un autre
        $stepUserId = null;
    } else {
        $initialStatus = $STATUS_NOT_STARTED; // les étapes suivantes ne sont pas encore activées
        $stepUserId = null;
    }

  

   $step_instance =  WorkflowInstanceStep::create([
        'workflow_instance_id' => $workflowInstance->id,
        'workflow_step_id' => $step['id'],
        'role_id' => $step["assignment_mode"] == "STATIC" ? ( $step['role_id'] ?? null ) : $this->getRoleValidator($validated["department_id"])['id'],
        'user_id' => $stepUserId,
        'status' => $initialStatus,
        'position'  => $step['position'], // copie depuis le template

    ]);

    if ($step["assignment_mode"] != "STATIC") {
         // return $step_instance;
      }
}

 $nextStep = $workflowInstance->instance_steps()
->where('status', $STATUS_NOT_STARTED)
->orderBy('position')
->first();

if ($nextStep) {
$nextStep->update([
'status' => $STATUS_PENDING,
]);

// notifier le user assigné


}


   


            DB::commit();
        
            return response()->json($workflowInstance, 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
 
      

        

        





    }

    public function getRoleValidator($departmentId)
    {

        $department_position = $this->resolveDepartmentValidator($departmentId) ;
      return  $role = $this->resolveRoleValidator($department_position['position']['name'])['results'] ;


    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function show(WorkflowInstance $workflowInstance)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function edit(WorkflowInstance $workflowInstance)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowInstanceRequest  $request
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowInstanceRequest $request, WorkflowInstance $workflowInstance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\WorkflowInstance  $workflowInstance
     * @return \Illuminate\Http\Response
     */
    public function destroy(WorkflowInstance $workflowInstance)
    {
        //
    }
}
