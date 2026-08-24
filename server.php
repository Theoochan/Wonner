<?php

declare(strict_types=1);

/**
 * Script roteador para o servidor embutido do PHP, usado em desenvolvimento.
 *
 *     php -S 127.0.0.1:8000 -t public server.php
 *
 * Por que existe: o servidor embutido, ao receber um script roteador,
 * encaminha TODA requisição para ele — inclusive as de css, js e imagem.
 * Devolver `false` aqui faz o servidor entregar o arquivo real, e é o
 * equivalente da primeira regra do .htaccess.
 *
 * Em produção quem faz esse papel é o Apache, e este arquivo não é usado.
 */

$caminho = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$arquivo = __DIR__.'/public'.$caminho;

// Arquivo estático existente: o servidor entrega direto.
if ($caminho !== '/' && is_file($arquivo)) {
    return false;
}

require __DIR__.'/public/index.php';
