# Assistant IA FOSER

L’assistant est exposé par `/assistant` et reçoit les questions via `/assistant/ask`, `/assistant/orientation` et `/assistant/checklist`.

Le provider est abstrait par `AIProviderInterface` et sélectionné par `AIService` via `config/ai.php`. Les providers Gemini, OpenAI, Mistral et local sont disponibles derrière une configuration `.env`; le fallback mock permet au portail de rester fonctionnel sans clé externe.

Les routes valident la question, appliquent un rate limit, journalisent les interactions dans `ai_interactions` et retournent les sources utilisées. Les contenus privés ne sont pas inclus dans la recherche actuelle. Les motifs courants de prompt injection sont refusés avant retrieval/provider. Le feedback est limité à la session ou à l’utilisateur propriétaire.
