<?php

declare(strict_types=1);

namespace App\Controllers\Loja;

use App\Core\View;

/**
 * Página inicial da loja.
 *
 * Provisória: existe para verificar que roteador, view e layout estão
 * funcionando. O conteúdo real — vitrine com categorias e destaques —
 * entra quando os Models existirem.
 */
final class HomeController
{
    public function index(): void
    {
        View::renderComLayout('loja/home', 'layout/loja', [
            'titulo' => 'Won by your own',
        ]);
    }
}
