# Dashboard décisionnel

`DashboardStatisticsService` fournit les KPI, graphiques par période, région, université, programme, sexe et statut. Les résultats sont mis en cache 60 secondes avec une clé versionnée.

Les workflows applicatifs et financiers invalident la version du cache après chaque transition. L’API protégée `/api/v1/statistics` expose les KPI et graphiques aux utilisateurs autorisés. Les statistiques régionales publiques sont disponibles via `/api/v1/statistics/regions` sous rate limit.

À compléter avant production : diffusion push SSE/WebSocket, indicateur de dernière mise à jour dans l’interface et métriques de fraîcheur.
