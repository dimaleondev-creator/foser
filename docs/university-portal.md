# Espace Université FOSER

## Fonctions couvertes

- Connexion par les routes d’authentification existantes; les routes université exigent un compte actif associé à un établissement.
- Tableau de bord avec établissement, statistiques par université, dossiers en attente, appels ouverts et notifications récentes.
- Profil d’établissement consultable et modifiable avec validation des champs et journal d’audit.
- Liste paginée des étudiants rattachés, recherche par nom, email ou INEE, fiche étudiant et validation des informations.
- Liste filtrable des candidatures et fiche détaillée avec données étudiant/académiques, projet, pièces, état de revue et historique.
- Téléchargement privé des pièces rattachées aux candidatures des seuls étudiants de l’établissement.
- Validation seulement si les informations et pièces obligatoires sont complètes; rejet avec motif requis; demande de correction avec motif requis.
- Transitions écrites dans l’historique, journalisées dans l’audit et notifiées aux étudiants concernés.
- Import XLSX/CSV/TXT, téléchargement du modèle XLSX, prévisualisation, validation d’en-têtes, rapport par ligne, historique tenant-scopé et audit des compteurs. Colonnes requises: `inee`, `name`, `email`.
- Rapports CSV et statistiques, messagerie avec affichage de l’historique et réponses limitées aux participants du fil.

## Isolation et autorisations

Les requêtes de détail, listes, pièces jointes, imports et rapports sont limitées par `university_id`. Les contrôles de transition utilisent `UniversityPolicy` en plus des permissions dédiées `university.applications.validate`, `university.applications.reject` et `university.applications.correction`. Les identifiants d’une autre université répondent 404 pour éviter de confirmer leur existence. Les opérations de modification valident leurs entrées et enregistrent l’acteur, l’établissement, le motif et/ou les compteurs pertinents.

## Tests exécutés

- `UniversityPortalTest`: accès étudiant, recherche, validation, transitions avec motif, isolation IDOR des candidatures et pièces, import réel et erreurs par ligne, modèle XLSX, aperçus, rapports, notifications et messagerie.
- `UniversityWorkspaceExpansionTest`: API tenant-scopée, exposition limitée des données, correction interdite après décaissement.
- `ApiResourceScopingTest`: isolation API des ressources étudiantes.

Les requêtes liées au module IA n’ont pas été modifiées dans le cadre de ce travail.
