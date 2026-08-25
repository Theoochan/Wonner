<?php

// Página inicial da loja.
//
// Provisória: existe para conferir que a estrutura funciona. O
// conteúdo real — categorias e destaques vindos do banco — entra
// na entrega E1, quando o modelo Categoria existir.

$titulo = 'Won by your own';

require __DIR__ . '/../../incluir/topo.php';
?>

<p class="rotulo text-[11px] uppercase tracking-[0.3em] text-[#a63a2a] mb-3">
    Estrutura
</p>

<h1 class="titulo text-4xl md:text-5xl font-black leading-tight mb-7">
    A estrutura está de pé.
</h1>

<ul class="border-t border-[#0f1e3d]">
    <li class="py-3 pl-7 relative border-b border-[#0f1e3d]/20">
        <span class="absolute left-0 text-[#a63a2a] font-bold">✓</span>
        O <code class="bg-[#e8dcc0] px-1.5 rounded-sm font-mono text-sm">index.php</code> recebeu a requisição
    </li>
    <li class="py-3 pl-7 relative border-b border-[#0f1e3d]/20">
        <span class="absolute left-0 text-[#a63a2a] font-bold">✓</span>
        Encontrou <code class="bg-[#e8dcc0] px-1.5 rounded-sm font-mono text-sm">/</code> na lista de rotas
    </li>
    <li class="py-3 pl-7 relative border-b border-[#0f1e3d]/20">
        <span class="absolute left-0 text-[#a63a2a] font-bold">✓</span>
        Carregou <code class="bg-[#e8dcc0] px-1.5 rounded-sm font-mono text-sm">paginas/home.php</code>
    </li>
    <li class="py-3 pl-7 relative border-b border-[#0f1e3d]/20">
        <span class="absolute left-0 text-[#a63a2a] font-bold">✓</span>
        Esta página incluiu o topo e o rodapé
    </li>
    <li class="py-3 pl-7 relative">
        <span class="absolute left-0 text-[#a63a2a] font-bold">✓</span>
        O Tailwind está estilizando tudo isto
    </li>
</ul>

<p class="mt-8 italic text-[#0f1e3d]/75">
    Próximo (entrega E1): instalar o MySQL, rodar
    <code class="bg-[#e8dcc0] px-1.5 rounded-sm font-mono text-sm not-italic">docs/ddl.sql</code>,
    criar <code class="bg-[#e8dcc0] px-1.5 rounded-sm font-mono text-sm not-italic">incluir/modelos/Categoria.php</code>
    e listar as categorias aqui.
</p>

<?php require __DIR__ . '/../../incluir/rodape.php'; ?>
