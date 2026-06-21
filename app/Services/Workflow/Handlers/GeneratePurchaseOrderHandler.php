<?php

use App\Contracts\WorkflowEventHandlerInterface;

class GeneratePurchaseOrderHandler
    implements WorkflowEventHandlerInterface
{
        public function execute(
        int $documentId,
        $instance,
        array $documentData,
        array $config = []
    ):array 
    {
        return [

            'data' => [

                // 'purchase_reference' => $purchase->reference,

                // 'supplier' => $purchase->supplier_name,

                // 'amount' => $purchase->amount,
            ],

            'attachments' => [
                // ...
            ]
        ];
    }
}