# Flux financier FOSER

Le module conserve `application_awards`, `financial_commitments`, `disbursements` et `payment_records` comme registre métier. L’attribution est créée par le workflow de décision; l’engagement reprend le bénéficiaire, le programme, le budget, l’exercice et la référence de décision. Un engagement peut être décaissé en tranches, chacune pouvant ensuite recevoir un ou plusieurs paiements.

## Cycle de paiement

1. Autoriser une opération en créant un paiement `pending` avec une clé `Idempotency-Key` unique.
2. Faire évoluer le paiement par `processing`, `paid`, `failed`, `cancelled` ou `reversed`, selon les transitions permises.
3. Chaque changement crée une ligne `financial_transactions` et une entrée `financial_audit_logs`, avec l’acteur, les références et les métadonnées.
4. Un paiement confirmé produit un reçu PDF accessible par un jeton aléatoire; seul son hash est stocké. Un paiement confirmé ne peut plus être modifié depuis le CRUD.
5. Rapprocher un paiement confirmé en comparant montant attendu, payé et reçu, et en conservant la référence externe.

## Opérations API

- `POST /api/v1/disbursements/{disbursement}/payments` : autorisation; exige `Idempotency-Key`.
- `POST /api/v1/payments/{payment}/transition` : exécution ou changement d’état autorisé.
- `POST /api/v1/payments/{payment}/reconciliations` : rapprochement.
- `GET /api/v1/payments/{payment}/reconciliations`, `GET /api/v1/transactions` et `GET /api/v1/financial-history` : consultation.
- `POST /api/v1/disbursements/{disbursement}/supporting-document` : ajout privé d’un justificatif PDF ou image.

Les permissions `finance.view`, `finance.validate`, `finance.authorize`, `finance.execute` et `finance.reconcile` séparent lecture, validation, autorisation, exécution et rapprochement. `finance.manage` reste accepté par le service comme compatibilité avec les rôles historiques.

## Futurs opérateurs

`FinancialPaymentProvider` est le contrat d’extension pour un adaptateur opérateur. Il doit normaliser le résultat en `FinancialPaymentResult` et reporter les références externes et métadonnées au workflow financier. Aucun adaptateur ni appel réel Orange Money, Wave, Moov ou Telecel n’est installé; le registre financier interne reste la source de vérité.