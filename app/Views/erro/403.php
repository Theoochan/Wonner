<?php

/** @var string $titulo */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo ?? 'Acesso negado') ?> · Wonner</title>
    <link rel="stylesheet" href="/css/loja.css">
</head>
<body>
    <main class="erro">
        <p class="etiqueta">403</p>
        <h1>Você não tem acesso a esta página.</h1>
        <p><a href="/">Voltar para a loja</a></p>
    </main>
</body>
</html>
