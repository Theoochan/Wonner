<?php

declare(strict_types=1);

/**
 * Rotas da vitrine — o que o cliente acessa.
 *
 * A variável $router vem do front controller (public/index.php).
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\Loja\HomeController;

$router->get('/', [HomeController::class, 'index']);
