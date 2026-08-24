<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renderização de views.
 *
 * Uma view é um arquivo PHP em app/Views que só monta HTML. Os dados
 * chegam como variáveis, extraídas do array passado em render().
 *
 * A função e() escapa a saída e deve envolver TODO valor que venha do
 * banco ou do usuário — é a defesa contra XSS. Sem framework, ninguém
 * escapa por você.
 */
final class View
{
    /**
     * @param  string  $view   Caminho dentro de app/Views, sem extensão. Ex.: 'loja/home'
     * @param  array<string, mixed>  $dados
     */
    public static function render(string $view, array $dados = []): void
    {
        echo self::capturar($view, $dados);
    }

    /**
     * Renderiza uma view dentro de um layout.
     *
     * O layout recebe o HTML já pronto na variável $conteudo.
     *
     * @param  array<string, mixed>  $dados
     */
    public static function renderComLayout(string $view, string $layout, array $dados = []): void
    {
        $conteudo = self::capturar($view, $dados);

        echo self::capturar($layout, $dados + ['conteudo' => $conteudo]);
    }

    /**
     * Executa o arquivo da view e devolve o HTML gerado, em vez de imprimi-lo.
     *
     * @param  array<string, mixed>  $dados
     */
    private static function capturar(string $view, array $dados): string
    {
        $arquivo = dirname(__DIR__).'/Views/'.$view.'.php';

        if (! is_file($arquivo)) {
            throw new RuntimeException("View não encontrada: {$view}");
        }

        // extract() transforma as chaves do array em variáveis locais:
        // ['titulo' => 'Home'] passa a ser $titulo dentro da view.
        extract($dados, EXTR_SKIP);

        ob_start();
        require $arquivo;

        return (string) ob_get_clean();
    }
}
