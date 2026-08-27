# Assistant IA FOSER

L’assistant est exposé par `/assistant` et reçoit les questions via `/assistant/ask`, `/assistant/orientation` et `/assistant/checklist`.

Le provider est abstrait par `AIProviderInterface`. Le provider local actuel ne contacte aucun service externe. Une intégration externe doit être ajoutée derrière cette interface, configurée par variables `.env`, et soumise à une autorisation explicite avant l’envoi de documents.

Les routes valident la question, appliquent un rate limit et retournent les sources utilisées. Les contenus privés ne sont pas inclus dans la recherche actuelle.
