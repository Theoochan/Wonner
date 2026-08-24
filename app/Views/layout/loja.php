<?php

/**
 * Layout da vitrine.
 *
 * @var string $conteudo  HTML da view, já renderizado
 * @var string $titulo
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? '') ?> · Wonner</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/css/loja.css">
</head>
<body>
    <header class="topo">
        <a class="marca" href="/">WONNER</a>
    </header>

    <main>
        <?= $conteudo ?>
    </main>

    <footer class="rodape">
        <span>© MMXXVI Wonner</span>
    </footer>
</body>
</html>
