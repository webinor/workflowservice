<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Assignment;
use App\Models\WorkflowInstanceStepAssignment;

class FillAssignmentSourceValue extends Command
{
    protected $signature = 'assignment:fill-source-value';

    protected $description = 'Fill source_value in assignments from instance_step step assignment_rule';

    public function handle()
    {
        $this->info("Starting backfill of assignment.source_value...");

        WorkflowInstanceStepAssignment::with([
            'instanceStep.workflowStep'
        ])
        // ->whereNull('source_value')
        ->chunkById(200, function ($assignments) {

            foreach ($assignments as $assignment) {

              if ($assignment) {
                    $this->warn("Traitement de {$assignment->id}");
                }

                $instanceStep = $assignment->instanceStep;
                $step = $instanceStep->workflowStep;

                if (!$step) {
                    $this->warn("Assignment {$assignment->id} has no step");
                    continue;
                }

                $assignmentRule = $step->assignment_rule ?? $step->assignment_mode;

                if (!$assignmentRule) {
                    $this->warn("Step {$step->id} has no assignment_rule");
                    continue;
                }

                $assignment->source_value = $assignmentRule;
                $assignment->save();
            }
        });

        $this->info("Backfill completed.");
    }
}