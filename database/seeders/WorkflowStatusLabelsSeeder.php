<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowStatusLabelsSeeder extends Seeder
{
    public function run()
    {
        DB::table('workflow_status_labels')->insert([

            [
                'code' => 'UNDER_REVIEW',
                'label' => 'Validation en cours',
                'emoji' => '🟡',
                'color' => 'warning',
                'is_configurable' => true,
                'status_type' => 'COMMON',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'WAITING_PAYMENT',
                'label' => 'En attente de paiement',
                'emoji' => '🟡',
                'color' => 'warning',
                'is_configurable' => true,
                'status_type' => 'INVOICE',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'PARTIALLY_PAID',
                'label' => 'Partiellement payée',
                'emoji' => '🟠',
                'color' => 'warning',
                'is_configurable' => false,
                'status_type' => 'INVOICE',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'PAID',
                'label' => 'Payée',
                'emoji' => '✅',
                'color' => 'success',
                'is_configurable' => false,
                'status_type' => 'INVOICE',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'APPROVED',
                'label' => 'Approuvée',
                'emoji' => '✅',
                'color' => 'success',
                'is_configurable' => true,
                'status_type' => 'DEMAND',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'REJECTED',
                'label' => 'Rejetée',
                'emoji' => '❌',
                'color' => 'danger',
                'is_configurable' => true,
                'status_type' => 'COMMON',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            [
                'code' => 'ARCHIVED',
                'label' => 'Archivée',
                'emoji' => '📦',
                'color' => 'secondary',
                'is_configurable' => true,
                'status_type' => 'COMMON',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}