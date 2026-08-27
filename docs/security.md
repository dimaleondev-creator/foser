# Sécurité

Les routes sensibles utilisent Sanctum, les permissions Spatie, des policies et des rate limits. Les documents privés sont servis par contrôleur autorisé et le stockage local privé n’est pas exposé directement.

Les uploads d’images des modules Agenda, Partenaires et Témoignages sont limités aux MIME image acceptés et à 5 Mo dans Filament. Les headers de sécurité sont ajoutés par `SecurityHeaders`.

Avant production : définir `APP_DEBUG=false`, configurer une CSP adaptée aux assets réellement utilisés, activer HTTPS/HSTS et vérifier les secrets uniquement dans l’environnement de déploiement.