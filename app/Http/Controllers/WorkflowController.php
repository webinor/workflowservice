<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Models\DocumentTypeWorkflow;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
      
        try {
            
            $workflows = Workflow::get();

            return response()->json(['success' => true , 'data'=> $workflows] );

        } catch (\Throwable $th) {
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
        //
    }

    public function getByDocumentType($documentTypeId)
    {
 
        try {
            
            $documentTypeWorkflow = DocumentTypeWorkflow::where('document_type_id', $documentTypeId)
            ->with(['workflow'=>function ($query)  {
             //   $query->whereStatus("ACTIVE");
            },'workflow.steps'=>function ($query)  {
                   $query->orderBy("position" , "desc");
               }])
            ->first();
    
        if (!$documentTypeWorkflow) {
            // Pas de workflow pour ce type de document → on renvoie id null
            return response()->json([

                'id' => null,
                'message' => 'Aucun workflow associé à ce type de document'
            ]);
        }
    
        return response()->json($documentTypeWorkflow->workflow);

        } catch (\Throwable $th) {
            throw $th;
        }
    }
    


    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreWorkflowRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreWorkflowRequest $request)
    {
     
        try {
            DB::beginTransaction();


       /* $request->validate([
            'name' => 'required|string',
            'document_type' => 'nullable|integer|exists:document_types,id',
            'recipientMode' => 'nullable|string',
            'steps' => 'array',
        ]);*/

        // Créer le workflow
        $workflow = Workflow::create([
            'name' => $request->name,
           // 'document_type_id' => $request->document_type,
           // 'recipient_mode' => $request->recipientMode,
        ]);

        // 2️⃣ Associer le workflow au document type
        if ($request->document_type) {
            DocumentTypeWorkflow::create([
                'workflow_id' => $workflow->id,
                'document_type_id' => $request->document_type,
            ]);
        }

        // Enregistrer les étapes
        foreach ($request->steps as $index => $step) {
            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'name' => $step['stepName'],
                'assignment_mode' => $step['assignationMode'],
                'role_id' => $step['roleId'] ?? null,
                'position' => $index,
            ]);
        }

        DB::commit();


        return response()->json($workflow->load('steps'), 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function show(Workflow $workflow)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function edit(Workflow $workflow)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateWorkflowRequest  $request
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateWorkflowRequest $request, Workflow $workflow)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Workflow  $workflow
     * @return \Illuminate\Http\Response
     */
    public function destroy(Workflow $workflow)
    {
        //
    }
}
