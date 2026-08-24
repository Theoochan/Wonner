<?php

declare(strict_types=1);

/**
 * Front controller — o único ponto de entrada da aplicação.
 *
 * Toda requisição chega aqui, seja /produto/7 ou /admin/pedidos. O
 * .htaccess reescreve as URLs para este arquivo.
 *
 * A vantagem de haver um só: verificação de sessão, tratamento de erro e
 * carregamento de configuração acontecem em um lugar. Com um arquivo por
 * página, cada arquivo repetiria isso — e um dia um deles esqueceria.
 */

use App\Core\Config;
use App\Core\Router;

require dirname(__DIR__).'/vendor/autoload.php';

// ── Tratamento de erro ────────────────────────────────────────────
// Em desenvolvimento, mostra o erro na tela. Em produção, registra e
// mostra uma página genérica: mensagem de erro detalhada revela caminhos
// de arquivo e trechos de consulta a quem não deveria vê-los.
$debug = (bool) Config::get('app.debug', false);

ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

// ── Rotas ─────────────────────────────────────────────────────────
$router = new Router();

require dirname(__DIR__).'/routes/loja.php';
require dirname(__DIR__).'/routes/admin.php';

// ── Despacho ──────────────────────────────────────────────────────
// parse_url separa o caminho da query string: /busca?q=hoodie → /busca
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$router->despachar($metodo, $uri);
