<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowStatusLabelsSeeder extends Seeder
{
    public function run()
    {$statuses = [
    [
        'code' => 'UNDER_REVIEW',
        'label' => 'Validation en cours',
        'emoji' => '🟡',
        'color' => 'warning',
        'is_configurable' => true,
        'status_type' => 'COMMON',
    ],
    [
        'code' => 'WAITINGG_REGULARIZATION',
        'label' => 'En attente de régularisation',
        'emoji' => '🛠️',
        'color' => 'info',
        'is_configurable' => true,
        'status_type' => 'COMMON',
    ],
        [
        'code' => 'IN_REGULARIZATION',
        'label' => 'En cours de régularisation',
        'emoji' => '🛠️',
        'color' => 'info',
        'is_configurable' => true,
        'status_type' => 'COMMON',
    ],
    [
        'code' => 'WAITING_PAYMENT',
        'label' => 'En attente de paiement',
        'emoji' => '🟡',
        'color' => 'warning',
        'is_configurable' => true,
        'status_type' => 'INVOICE',
    ],
    [
        'code' => 'PARTIALLY_PAID',
        'label' => 'Partiellement payée',
        'emoji' => '🟠',
        'color' => 'warning',
        'is_configurable' => false,
        'status_type' => 'INVOICE',
    ],
    [
        'code' => 'PAID',
        'label' => 'Payée',
        'emoji' => '✅',
        'color' => 'success',
        'is_configurable' => false,
        'status_type' => 'INVOICE',
    ],
    [
        'code' => 'APPROVED',
        'label' => 'Approuvée',
        'emoji' => '✅',
        'color' => 'success',
        'is_configurable' => true,
        'status_type' => 'DEMAND',
    ],
    [
        'code' => 'REJECTED',
        'label' => 'Rejetée',
        'emoji' => '❌',
        'color' => 'danger',
        'is_configurable' => true,
        'status_type' => 'COMMON',
    ],
    [
        'code' => 'ARCHIVED',
        'label' => 'Archivée',
        'emoji' => '📦',
        'color' => 'secondary',
        'is_configurable' => true,
        'status_type' => 'COMMON',
    ],
];foreach ($statuses as $status) {
    DB::table('workflow_status_labels')->updateOrInsert(
        ['code' => $status['code']],
        array_merge($status, [
            'updated_at' => now(),
        ])
    );
}
    }
}