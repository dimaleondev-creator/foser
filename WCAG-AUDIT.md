# Audit WCAG FOSER

## Périmètre

Audit statique initial des vues publiques, des espaces authentifiés et des composants interactifs présents dans le dépôt. Un audit manuel avec lecteur d’écran et un outil axe/Lighthouse reste nécessaire avant une déclaration de conformité AA.

| Problème | Emplacement | Gravité | Correction | Statut |
|---|---|---:|---|---|
| Absence de lien de saut vers le contenu principal | Vues publiques Blade | Moyenne | Ajouter un lien `skip-link` et une cible `main` | À faire |
| Contraste et focus à vérifier sur les liens secondaires | `resources/css/app.css` | Moyenne | Tester avec axe et renforcer `:focus-visible` | À faire |
| Menu mobile à tester au clavier après ouverture | `resources/views/welcome.blade.php`, `resources/js/app.js` | Moyenne | Gérer focus entrant, fermeture avec Échap et retour du focus | Partiel |
| Carte régionale | `resources/views/welcome.blade.php` | Faible | Sélecteur clavier et `aria-live` présents, vérifier les données visuelles | Partiel |
| Témoignages | `resources/views/testimonials/index.blade.php` | Faible | Pas de carousel automatique, lecture linéaire compatible clavier | Corrigé |
| Formulaires | Vues publiques et espaces | Moyenne | Vérifier messages d’erreur associés et annonces `aria-live` | Partiel |
| Images téléversées | Agenda, partenaires, témoignages | Faible | Attributs `alt` générés selon le contexte | Corrigé |

## Contrôles réalisés

- Les boutons et liens principaux utilisent des éléments HTML natifs.
- Les champs de recherche et formulaires possèdent des labels visibles ou accessibles.
- Les contenus non publics ne sont pas rendus dans les listes publiques.
- Le menu mobile expose `aria-expanded`.

## Suite recommandée

Installer et exécuter axe-core ou Pa11y sur les pages `/`, `/agenda`, `/partenaires`, `/temoignages`, `/faq`, `/student/login` et `/assistant`, puis effectuer un parcours clavier complet.