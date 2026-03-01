<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncWorkflowActionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();

        try {

            // 1️⃣ Récupérer l'id du type "validation"
            $validationTypeId = DB::table('workflow_action_types')
                ->where('label', 'validation')
                ->value('id');

            if (!$validationTypeId) {
                throw new \Exception('Workflow action type "validation" not found.');
            }

            // 2️⃣ Mettre à jour les workflow_actions
            $affected = DB::table('workflow_actions')
                ->update([
                    'workflow_action_type_id' => $validationTypeId
                ]);

            DB::commit();

            $this->command->info("Done. {$affected} rows updated.");

        } catch (Throwable $e) {

            DB::rollBack();

            $this->command->error('Error occurred. Transaction rolled back.');
            $this->command->error($e->getMessage());
        }
    }
}