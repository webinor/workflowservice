<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportWorkflowDocumentsRequest extends FormRequest
{
    /**
     * Autorisation de la requête.
     *
     * L'authentification de l'utilisateur est déjà gérée
     * par le middleware d'authentification du workflow-service.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Règles de validation.
     *
     * @return array
     */
    public function rules()
    {
        return [
            /*
             * ------------------------------------------------------------------
             * Type de document
             * ------------------------------------------------------------------
             *
             * Exemple :
             * taxi_paper
             * fee_note
             * regularization_sheet
             *
             * On vérifie uniquement qu'il s'agit d'une chaîne non vide.
             *
             * La vérification métier du type et des permissions reste
             * dans le workflow-service.
             */
            'document_type' => [
                'required',
                'string',
                'max:100',
            ],

            /*
             * ------------------------------------------------------------------
             * Contexte workflow
             * ------------------------------------------------------------------
             *
             * Les valeurs actuellement utilisées par le workflow sont :
             *
             * TO_VALIDATE
             * MY_DOCUMENTS
             */
            'context' => [
                'required',
                'string',
                Rule::in([
                    'TO_VALIDATE',
                    'MY_DOCUMENTS',
                ]),
            ],

            /*
             * ------------------------------------------------------------------
             * Filtres
             * ------------------------------------------------------------------
             *
             * Les filtres sont volontairement peu contraints ici.
             *
             * Pourquoi ?
             *
             * Parce que le workflow-service possède déjà sa propre logique
             * de résolution des filtres via :
             *
             * resolveFilterContext($context, $filters)
             *
             * Il ne faut donc pas dupliquer ici toutes les règles métier
             * des différents types de documents.
             */
            'filters' => [
                'nullable',
                'array',
            ],

            /*
             * ------------------------------------------------------------------
             * Colonnes Excel
             * ------------------------------------------------------------------
             *
             * Le frontend envoie uniquement les clés des colonnes.
             *
             * Exemple :
             *
             * [
             *     "title",
             *     "actor_full_name",
             *     "dynamic_amount",
             *     "created_at",
             *     "workflow_status"
             * ]
             *
             * La validation de la liste exacte des colonnes autorisées
             * est ensuite faite par le resolver d'export.
             */
            'columns' => [
                'required',
                'array',
                'min:1',
            ],

            /*
             * Chaque colonne doit être une clé textuelle.
             */
            'columns.*' => [
                'required',
                'string',
                'max:100',
            ],
        ];
    }

    /**
     * Messages de validation personnalisés.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'document_type.required' =>
                'Le type de document est obligatoire.',

            'document_type.string' =>
                'Le type de document doit être une chaîne de caractères.',

            'context.required' =>
                'Le contexte workflow est obligatoire.',

            'context.in' =>
                'Le contexte workflow sélectionné est invalide.',

            'filters.array' =>
                'Les filtres doivent être transmis sous forme de tableau.',

            'columns.required' =>
                'Les colonnes à exporter sont obligatoires.',

            'columns.array' =>
                'Les colonnes doivent être transmises sous forme de tableau.',

            'columns.min' =>
                'Au moins une colonne doit être sélectionnée.',

            'columns.*.string' =>
                'Chaque colonne doit être une clé valide.',
        ];
    }

    /**
     * Préparation des données avant validation.
     *
     * On normalise uniquement la structure de la requête.
     *
     * Aucune règle métier n'est appliquée ici.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'filters' => $this->input('filters', []),
            'columns' => array_values(
                array_unique(
                    $this->input('columns', [])
                )
            ),
        ]);
    }
}