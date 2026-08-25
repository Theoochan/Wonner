<?php

// Usado só em desenvolvimento, com o servidor embutido do PHP:
//
//     php -S localhost:8000 -t publico server.php
//
// O servidor embutido não lê o .htaccess e manda TODA requisição
// para cá — inclusive css e imagens. O "return false" abaixo diz
// "entregue o arquivo real", e faz o mesmo que a primeira regra do
// .htaccess. Em produção o Apache cuida disso e este arquivo não é
// usado.

$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$arquivo = __DIR__ . '/publico' . $caminho;

if ($caminho != '/' && is_file($arquivo)) {
    return false;
}

require __DIR__ . '/publico/index.php';
