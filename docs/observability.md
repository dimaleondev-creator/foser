# Observabilité

Les endpoints suivants sont disponibles sans authentification pour les sondes d’orchestration :

- `/live` : processus Laravel actif.
- `/ready` : vérification base de données et cache.
- `/health` : statut détaillé et horodatage.
- `/up` : endpoint de santé Laravel natif.

Les erreurs API sont normalisées en JSON et les opérations sensibles sont journalisées par `AuditLogger`. Docker configure des healthchecks pour l’application, Nginx, PostgreSQL et Redis.

À compléter avant production : métriques de durée et taux d’erreur, suivi des jobs échoués, alertes, corrélation des requêtes et export optionnel Prometheus/OpenTelemetry.
