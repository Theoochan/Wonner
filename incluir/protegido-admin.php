<?php

// Exige login E papel de administrador.
// Incluído pelo index.php em todas as rotas que começam com /admin,
// de modo que não há como esquecer a verificação numa página.

require __DIR__ . '/protegido.php';

if (! ehAdmin()) {
    http_response_code(403);
    require __DIR__ . '/../publico/paginas/403.php';
    exit;
}
