<?php

class SignatoryQuorumService
{
    /**
     * Détermine le nombre de signatures exigées
     * selon le montant du document.
     */
    public function getRequiredSignatures(array $documentData)
    {
        // Lecture de la configuration du quorum.
    }

    /**
     * Récupère les signatures déjà obtenues
     * sur les étapes concernées.
     */
    public function getApprovedSignatories(
        $workflowInstance,
        array $stepIds
    ) {
        // Lecture des affectations APPROVED.
    }

    /**
     * Calcule le nombre de signatures restantes.
     */
    public function getRemainingSignatures(
        $workflowInstance,
        array $stepIds,
        array $documentData
    ) {
        $required = $this->getRequiredSignatures($documentData);

        $approved = $this->getApprovedSignatories(
            $workflowInstance,
            [692, 693]
        );

        return max(0, $required - $approved->count());
    }
}