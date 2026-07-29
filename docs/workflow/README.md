# Workflow Action Requirements

La colonne `requirements` de la table `workflow_action_steps` permet de définir, sous forme de JSON, les prérequis nécessaires avant qu'une action de workflow puisse être exécutée.

Le frontend évalue ces règles via le hook `useRequirementEvaluator`.

## Structure générale

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "SIGNATURE"
        }
      ]
    }
  ]
}
```

- `conditions` : liste de groupes de conditions.
- `operator` :
  - `AND` → toutes les règles du groupe doivent être vraies.
  - `OR` → au moins une règle du groupe doit être vraie.
- `rules` : liste des règles à évaluer.

---

# Types de règles supportés

## 1. Signature obligatoire

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "SIGNATURE"
        }
      ]
    }
  ]
}
```

Équivalent :

```ts
context.signatureCompleted === true
```

---

## 2. Paiement obligatoire

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "PAYMENT"
        }
      ]
    }
  ]
}
```

Équivalent :

```ts
context.paymentCompleted === true
```

---

## 3. Tous les justificatifs requis

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "RECEIPTS"
        }
      ]
    }
  ]
}
```

Équivalent :

```ts
context.hasAllReceipts === true
```

---

## 4. Pièce jointe obligatoire

Exemple : Attestation de règlement (id = 12)

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "ATTACHMENT",
          "attachment_type": 12
        }
      ]
    }
  ]
}
```

Équivalent :

```ts
context.attachments.includes(12)
```

---

# Combinaisons de règles

## Signature ET Paiement

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "SIGNATURE"
        },
        {
          "type": "PAYMENT"
        }
      ]
    }
  ]
}
```

---

## Signature OU Paiement

```json
{
  "conditions": [
    {
      "operator": "OR",
      "rules": [
        {
          "type": "SIGNATURE"
        },
        {
          "type": "PAYMENT"
        }
      ]
    }
  ]
}
```

---

## Signature + Justificatifs + Attestation

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "SIGNATURE"
        },
        {
          "type": "RECEIPTS"
        },
        {
          "type": "ATTACHMENT",
          "attachment_type": 12
        }
      ]
    }
  ]
}
```

---

# Plusieurs groupes de conditions

```json
{
  "conditions": [
    {
      "operator": "AND",
      "rules": [
        {
          "type": "SIGNATURE"
        },
        {
          "type": "PAYMENT"
        }
      ]
    },
    {
      "operator": "OR",
      "rules": [
        {
          "type": "ATTACHMENT",
          "attachment_type": 10
        },
        {
          "type": "ATTACHMENT",
          "attachment_type": 11
        }
      ]
    }
  ]
}
```

Ce JSON signifie :

```
(Signature ET Paiement)
ET
(Attachement 10 OU Attachement 11)
```

---

# Contexte attendu par le frontend

Le hook `useRequirementEvaluator` s'appuie sur un objet `context` contenant les informations nécessaires à l'évaluation des règles.

Exemple :

```ts
const requirementsContext = {
    signatureCompleted: true,
    paymentCompleted: false,
    hasAllReceipts: true,
    attachments: [5, 10, 12]
};
```

---

# Hook d'évaluation

```ts
const requirementsSatisfied = evaluate(
    action.requirements,
    requirementsContext
);
```

Si le résultat est `true`, l'action est disponible.

Si le résultat est `false`, l'action est désactivée ou masquée selon le comportement souhaité.

---

# Règles actuellement supportées

| Type | Description |
|------|-------------|
| SIGNATURE | Vérifie que la signature requise a été effectuée |
| PAYMENT | Vérifie que le paiement a été réalisé |
| RECEIPTS | Vérifie que tous les justificatifs sont présents |
| ATTACHMENT | Vérifie la présence d'un type de pièce jointe |

---

# Évolutions prévues

La structure JSON a été conçue pour être extensible. De nouveaux types de règles pourront être ajoutés sans modifier la structure des données.

Exemples :

- DOCUMENT_REFERENCE
- ACCOUNTING_ENTRY
- WORKFLOW_DELAY
- DOCUMENT_STATUS
- ROLE
- RESPONSIBILITY
- CUSTOM_RULE

L'objectif est de conserver un moteur de workflow entièrement piloté par la configuration (`configuration-driven`) et non par du code spécifique à chaque type de document.