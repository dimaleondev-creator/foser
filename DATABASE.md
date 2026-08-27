# Modele de donnees FOSER

La base cible est PostgreSQL. Les tables exposees utilisent des UUID; `users` conserve la cle Laravel existante en entier et les tables d'infrastructure existantes ne sont pas dupliquees.

## Tables et role

| Table | Role |
|---|---|
| `users` | Identites et authentification Laravel existantes. |
| `student_profiles` | Informations academiques et identifiant etudiant. |
| `researcher_profiles` | Identite scientifique, ORCID et specialite. |
| `universities` | Etablissements partenaires. |
| `university_users` | Habilitations des utilisateurs par universite. |
| `laboratories` | Laboratoires rattaches a une universite. |
| `programs` | Cadre commun des programmes et budgets. |
| `financial_aids` | Modalites d'aides financieres d'un programme. |
| `study_loans` | Modalites des prets d'etudes. |
| `research_programs` | Specialisation recherche d'un programme. |
| `innovation_programs` | Specialisation innovation d'un programme. |
| `eligibility_rules` | Regles parametrables d'eligibilite en JSONB. |
| `required_documents` | Pieces attendues par programme. |
| `call_categories` | Categories des appels. |
| `calls` | Appels a candidatures avec periode et statut. |
| `applications` | Dossiers soumis par un utilisateur sur un appel. |
| `application_documents` | Pieces rattachees a un dossier. |
| `application_status_histories` | Historique immuable des changements de statut. |
| `evaluations` | Affectation d'un dossier a un evaluateur. |
| `evaluation_criteria` | Criteres et ponderations d'evaluation. |
| `evaluation_scores` | Notes donnees par critere et evaluation. |
| `application_results` | Decision et score publie d'un dossier. |
| `research_projects` | Projets de recherche finances ou candidats. |
| `research_project_members` | Membres et roles d'un projet. |
| `research_publications` | Publications produites par un projet. |
| `research_project_documents` | Documents d'un projet. |
| `research_project_evaluations` | Evaluations propres aux projets. |
| `research_project_disbursements` | Association explicite entre projets et decaissements. |
| `financial_commitments` | Engagements financiers lies a un dossier ou projet. |
| `disbursements` | Decaissements planifies ou executes. |
| `payment_records` | Traces de paiement d'un decaissement. |
| `news_categories` | Categories editoriales. |
| `news` | Actualites publiees. |
| `events` | Evenements institutionnels. |
| `press_releases` | Communiques officiels. |
| `announcements` | Annonces ciblees ou generales. |
| `documents` | Metadonnees et emplacement prive des fichiers. |
| `document_categories` | Classification documentaire. |
| `document_versions` | Versions immuables d'un document. |
| `downloads` | Journal des telechargements. |
| `media_albums` | Albums de la mediatheque. |
| `media` | Images et medias stockes. |
| `videos` | Metadonnees video associees a un media. |
| `notifications` | Notifications Laravel existantes, creees par sa migration standard. |
| `message_threads` | Conversations. |
| `message_thread_users` | Participants aux conversations. |
| `messages` | Messages envoyes dans une conversation. |
| `claims` | Reclamations et affectation a un agent. |
| `audit_logs` | Traces d'actions, valeurs avant/apres et contexte reseau. |
| `system_settings` | Parametres techniques versionnables, sans secret. |

## Relations principales

- Un `program` porte plusieurs aides, prets, regles, pieces requises et appels.
- Un `call` accepte plusieurs `applications`; un utilisateur ne peut soumettre qu'un dossier par appel.
- Une `application` porte ses documents, son historique, ses evaluations et au plus un resultat.
- Un `research_project` depend optionnellement d'un programme de recherche et d'un laboratoire; il porte membres, publications, documents et evaluations.
- Un `financial_commitment` est rattache a un dossier ou projet et porte plusieurs decaissements; chaque decaissement porte ses paiements.
- Les contenus editoriaux sont separes des documents prives et de la mediatheque.
- Les conversations relient plusieurs utilisateurs par `message_thread_users` et contiennent plusieurs messages.
- Les profils et organismes referencent `users` sans creer de nouvelle table d'identites.

## Workflow des dossiers

1. Un administrateur publie un `program`, ses `eligibility_rules`, `required_documents` et un `call`.
2. Le candidat cree un `application` en statut `draft` et depose ses pieces dans `documents` puis `application_documents`.
3. La soumission fixe `submitted_at` et fait passer le dossier a `submitted`; chaque transition est ajoutee a `application_status_histories`.
4. Le controle administratif fait passer le dossier a `under_review`, `incomplete` ou `eligible`.
5. Les evaluateurs affectes creent leurs `evaluations`, puis leurs `evaluation_scores`; le dossier devient `evaluated`.
6. La commission enregistre `application_results` avec `accepted`, `rejected` ou `waitlisted`, puis publie la decision.
7. Une decision acceptee peut produire un `financial_commitment`, des `disbursements` et des `payment_records`.
8. Toute modification sensible est journalisee dans `audit_logs`; les notifications sont envoyees via Laravel Notifications et les traitements longs via la queue.

## Regles metier de base

- Les references publiques, dossiers, documents et contenus utilisent des UUID et des slugs/references uniques.
- Les suppressions metier sont logiques lorsque l'historique ou la publication le justifie; les historiques et journaux ne sont pas supprimables par cascade applicative.
- Les dates d'ouverture/fermeture, montants, devises, scores et poids sont valides cote serveur par Form Requests et Policies.
- Les statuts sont des chaines bornees par le domaine, centralisees dans des enums PHP lors de l'implementation des cas d'usage; les migrations fournissent des valeurs initiales coherentes.
- Les fichiers prives ne sont jamais servis depuis `public`; `documents.path` pointe vers un disque protege.
- Les contraintes uniques evitent les doublons de candidature, d'evaluation, de participant et de version.
- Les foreign keys utilisent `restrict`, `cascade` ou `nullOnDelete` selon la conservation requise.
