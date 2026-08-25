<?php

// Começo do HTML de toda página da loja.
// A página define $titulo antes de incluir este arquivo.

$aviso = pegarAviso();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($titulo ?? '') ?> · <?= SITE_NOME ?></title>

    <!-- Tailwind pelo CDN: compila no navegador, sem nada para instalar.
         Na entrega E9 isto vira um arquivo CSS gerado pelo Tailwind CLI —
         e as classes do HTML continuam as mesmas. -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Oswald:wght@500;600&family=Libre+Caslon+Text:ital@0;1&display=swap" rel="stylesheet">

    <!-- As fontes da identidade. Só isto fica fora do Tailwind, porque
         é mais simples que declarar a família em cada elemento. -->
    <style>
        body   { font-family: 'Libre Caslon Text', Georgia, serif; }
        .titulo  { font-family: 'Cinzel', Georgia, serif; }
        .rotulo  { font-family: 'Oswald', Arial, sans-serif; }
    </style>
</head>
<body class="bg-[#f4ecd8] text-[#0f1e3d]">

<header class="bg-[#0f1e3d] px-8 py-5 flex flex-wrap items-baseline justify-between gap-x-8 gap-y-4">
    <a href="/" class="titulo text-[#f4ecd8] text-2xl font-black tracking-[0.28em]">
        WONNER
    </a>

    <nav class="rotulo flex gap-6 text-xs uppercase tracking-[0.16em] text-[#f4ecd8]">
        <a href="/" class="hover:underline">Loja</a>
        <a href="/sacola" class="hover:underline">Sacola</a>

        <?php if (estaLogado()): ?>
            <a href="/pedidos" class="hover:underline">Meus pedidos</a>
            <?php if (ehAdmin()): ?>
                <a href="/admin" class="hover:underline">Painel</a>
            <?php endif; ?>
            <a href="/sair" class="hover:underline">Sair</a>
        <?php else: ?>
            <a href="/entrar" class="hover:underline">Entrar</a>
        <?php endif; ?>
    </nav>
</header>

<main class="max-w-3xl mx-auto px-6 pt-14 pb-24">

<?php if ($aviso): ?>
    <p class="bg-[#e8dcc0] border-l-[3px] border-[#a63a2a] px-4 py-3 mb-8">
        <?= escapar($aviso) ?>
    </p>
<?php endif; ?>
