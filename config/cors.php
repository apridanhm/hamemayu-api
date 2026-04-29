<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    
    'allowed_methods' => ['*'],
    
    // Pas Dev biarin '*' biar Next.js localhost:3000 bisa fetch
    // Pas Prod nanti ganti ke ['https://hamemayujogja.id' atau 'http://localhost:3000']
    'allowed_origins' => ['*'],
    
    'allowed_origins_patterns' => [],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => [],
    
    'max_age' => 0,
    
    'supports_credentials' => true,
];
