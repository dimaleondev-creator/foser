# RAG FOSER

Le pipeline actuel est une première base de retrieval public : FAQ, actualités, programmes, appels, événements et partenaires publiés sont récupérés, comparés lexicalement à la question et limités aux trois meilleures sources.

Une réponse est refusée si le score de couverture est inférieur au seuil `0.34`. Chaque réponse acceptée contient les titres et URLs des sources. Le provider reçoit uniquement ces extraits publics.

À compléter avant production : extraction de fichiers, nettoyage, chunking, embeddings, vector store, reranking, journalisation des requêtes, détection d’instructions malveillantes et séparation formelle des espaces public/interne/privé.
