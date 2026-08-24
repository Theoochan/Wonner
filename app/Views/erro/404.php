<?php

/** @var string $titulo */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Não encontrado') ?> · Wonner</title>
    <link rel="stylesheet" href="/css/loja.css">
</head>
<body>
    <main class="erro">
        <p class="etiqueta">404</p>
        <h1>Esta página não existe.</h1>
        <p><a href="/">Voltar para a loja</a></p>
    </main>
</body>
</html>
