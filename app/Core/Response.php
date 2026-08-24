<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Saídas HTTP: redirecionamento e páginas de erro.
 *
 * Centralizar aqui evita `header('Location: ...')` espalhado, e garante
 * que todo redirecionamento encerre a execução — esquecer o `exit` depois
 * de um redirecionamento faz o código seguir rodando e é fonte clássica
 * de comportamento inexplicável.
 */
final class Response
{
    public static function redirect(string $destino): never
    {
        header('Location: '.$destino, true, 302);
        exit;
    }

    public static function naoEncontrado(): never
    {
        http_response_code(404);
        View::render('erro/404', ['titulo' => 'Página não encontrada']);
        exit;
    }

    public static function naoAutorizado(): never
    {
        http_response_code(403);
        View::render('erro/403', ['titulo' => 'Acesso negado']);
        exit;
    }
}
