<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Roteador.
 *
 * Guarda uma lista de rotas — método HTTP, padrão de URL e o que executar —
 * e despacha a requisição para a primeira que casar.
 *
 * O padrão aceita parâmetros entre chaves:
 *
 *     $r->get('/produto/{id}', [ProdutoController::class, 'mostrar']);
 *
 * O valor capturado é passado como argumento ao método, na ordem em que
 * aparece na URL.
 */
final class Router
{
    /** @var list<array{metodo: string, regex: string, params: list<string>, acao: mixed}> */
    private array $rotas = [];

    public function get(string $padrao, mixed $acao): void
    {
        $this->adicionar('GET', $padrao, $acao);
    }

    public function post(string $padrao, mixed $acao): void
    {
        $this->adicionar('POST', $padrao, $acao);
    }

    private function adicionar(string $metodo, string $padrao, mixed $acao): void
    {
        // Extrai os nomes dos parâmetros: '/produto/{id}' → ['id']
        preg_match_all('/\{(\w+)\}/', $padrao, $encontrados);

        // Transforma o padrão em expressão regular, escapando o resto:
        // '/produto/{id}' → '#^/produto/([^/]+)$#'
        $regex = preg_replace('/\{(\w+)\}/', '([^/]+)', $padrao);
        $regex = '#^'.$regex.'$#';

        $this->rotas[] = [
            'metodo' => $metodo,
            'regex'  => $regex,
            'params' => $encontrados[1],
            'acao'   => $acao,
        ];
    }

    /**
     * Encontra a rota correspondente e a executa.
     *
     * @param  string  $metodo  Método HTTP da requisição
     * @param  string  $uri     Caminho da URL, sem query string
     */
    public function despachar(string $metodo, string $uri): void
    {
        // Normaliza: remove a barra final, exceto na raiz.
        $uri = rtrim($uri, '/') ?: '/';

        foreach ($this->rotas as $rota) {
            if ($rota['metodo'] !== $metodo) {
                continue;
            }

            if (! preg_match($rota['regex'], $uri, $casados)) {
                continue;
            }

            array_shift($casados);          // descarta a correspondência inteira
            $this->executar($rota['acao'], $casados);

            return;
        }

        Response::naoEncontrado();
    }

    /**
     * @param  list<string>  $argumentos
     */
    private function executar(mixed $acao, array $argumentos): void
    {
        // Rota apontando para [Controller::class, 'metodo']
        if (is_array($acao)) {
            [$classe, $metodo] = $acao;
            (new $classe())->{$metodo}(...$argumentos);

            return;
        }

        // Rota apontando para uma função anônima
        $acao(...$argumentos);
    }
}
