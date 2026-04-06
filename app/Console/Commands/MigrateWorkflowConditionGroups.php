<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateWorkflowConditionGroups extends Command
{
    protected $signature = 'workflow:migrate-groups';
    protected $description = 'Assign group_id to existing workflow conditions';

    public function handle()
    {
        $this->info("Migration des groupes...");

        $conditions = DB::table('workflow_conditions')
            ->orderBy('workflow_transition_id')
            ->get()
            ->groupBy('workflow_transition_id');

        foreach ($conditions as $transitionId => $conds) {
            $groupId = Str::uuid()->toString();

            DB::table('workflow_conditions')
                ->whereIn('id', $conds->pluck('id'))
                ->update([
                    'group_id' => $groupId
                ]);

            $this->info("Transition $transitionId migrée");
        }

        $this->info("✅ Terminé !");
    }
}