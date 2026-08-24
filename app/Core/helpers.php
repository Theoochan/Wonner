<?php

declare(strict_types=1);

/**
 * Funções globais de apoio, carregadas pelo autoload do Composer.
 *
 * São poucas de propósito: só o que se usa em quase toda view.
 */

if (! function_exists('e')) {
    /**
     * Escapa um valor para uso seguro em HTML.
     *
     * Todo dado que vem do banco ou do usuário passa por aqui antes de ir
     * para a tela. É o que impede que um nome de produto contendo
     * <script> seja executado pelo navegador de quem visita a loja.
     */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('real')) {
    /**
     * Formata um valor monetário para exibição.
     *
     * Recebe reais como string ou float vindos de uma coluna DECIMAL e
     * devolve no formato brasileiro. O DECIMAL chega do PDO como string,
     * justamente para não perder precisão — por isso a conversão só
     * acontece aqui, na borda da exibição.
     */
    function real(string|float $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }
}

if (! function_exists('url')) {
    /** Monta uma URL absoluta a partir da raiz da aplicação. */
    function url(string $caminho = ''): string
    {
        $base = rtrim((string) \App\Core\Config::get('app.url', ''), '/');

        return $base.'/'.ltrim($caminho, '/');
    }
}
