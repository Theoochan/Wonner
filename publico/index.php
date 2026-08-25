<?php

// ════════════════════════════════════════════════════════════════
// Ponto de entrada do site.
//
// Toda requisição passa por aqui — o .htaccess manda tudo para este
// arquivo. Por isso os require ficam neste lugar só, e as páginas
// não precisam incluir nada.
// ════════════════════════════════════════════════════════════════

require __DIR__ . '/../incluir/config.php';
require __DIR__ . '/../incluir/conexao.php';
require __DIR__ . '/../incluir/funcoes.php';
require __DIR__ . '/../incluir/modelos.php';

session_start();

// Em desenvolvimento, mostra o erro na tela. Em produção, esconde.
ini_set('display_errors', MOSTRAR_ERROS ? '1' : '0');
error_reporting(E_ALL);


// ── Rotas ───────────────────────────────────────────────────────
// Liga o endereço ao arquivo da página. Para criar uma tela nova,
// acrescente uma linha aqui e crie o arquivo em paginas/.

$rotas = [

    // Loja
    '/'          => 'paginas/home.php',

    // As demais entram conforme as entregas:
    // '/produto'   => 'paginas/produto.php',
    // '/busca'     => 'paginas/busca.php',
    // '/sacola'    => 'paginas/sacola.php',
    // '/entrar'    => 'paginas/entrar.php',
    // '/cadastro'  => 'paginas/cadastro.php',
    // '/pedidos'   => 'paginas/pedidos.php',

    // Painel
    // '/admin'           => 'paginas/admin/inicio.php',
    // '/admin/produtos'  => 'paginas/admin/produtos.php',
];


// ── Descobre qual página o visitante pediu ──────────────────────
// parse_url separa o caminho do resto: "/produto?id=7" vira "/produto"

$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove a barra do final, para /sacola/ e /sacola serem a mesma coisa
if ($caminho != '/') {
    $caminho = rtrim($caminho, '/');
}


// ── Verificação de acesso ───────────────────────────────────────
// Feita aqui, antes de a página carregar. Assim toda rota /admin
// está protegida sem depender de a página lembrar de verificar.

if (str_starts_with($caminho, '/admin')) {
    require __DIR__ . '/../incluir/protegido-admin.php';
}


// ── Carrega a página ────────────────────────────────────────────

if (isset($rotas[$caminho])) {
    require __DIR__ . '/' . $rotas[$caminho];
} else {
    http_response_code(404);
    require __DIR__ . '/paginas/404.php';
}
