<?php

/**
 * Página inicial — provisória.
 *
 * Serve para conferir que o encanamento funciona: roteador encontrou a
 * rota, controller chamou a view, layout envolveu o conteúdo.
 */
?>
<section class="teste">
    <p class="etiqueta">Estrutura</p>
    <h1>O encanamento está de pé.</h1>

    <ul class="checklist">
        <li>Front controller recebeu a requisição</li>
        <li>Roteador casou a rota <code>GET /</code></li>
        <li><code>HomeController::index()</code> executou</li>
        <li>Esta view foi renderizada dentro do layout</li>
        <li>Autoload PSR-4 resolveu <code>App\Controllers\Loja\HomeController</code></li>
    </ul>

    <p class="proximo">
        Próximo: <code>Database</code> conectando ao MySQL, e os Models.
    </p>
</section>
