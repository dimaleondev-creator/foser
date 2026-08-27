<?php

return [
    'driver' => env('DOCUMENT_SEARCH_DRIVER', 'postgres'),
    'opensearch' => [
        'host' => env('OPENSEARCH_HOST', 'http://localhost:9200'),
        'index' => env('OPENSEARCH_DOCUMENTS_INDEX', 'foser-documents'),
    ],
];
