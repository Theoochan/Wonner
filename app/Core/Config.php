<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Acesso à configuração da aplicação.
 *
 * Carrega config/config.php uma única vez e devolve valores por caminho
 * separado com ponto: Config::get('banco.host').
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $dados = null;

    /**
     * Lê um valor da configuração.
     *
     * @param  string  $caminho  Ex.: 'banco.host', 'negocio.reserva_minutos'
     */
    public static function get(string $caminho, mixed $padrao = null): mixed
    {
        $valor = self::carregar();

        foreach (explode('.', $caminho) as $chave) {
            if (! is_array($valor) || ! array_key_exists($chave, $valor)) {
                return $padrao;
            }
            $valor = $valor[$chave];
        }

        return $valor;
    }

    /** @return array<string, mixed> */
    private static function carregar(): array
    {
        if (self::$dados !== null) {
            return self::$dados;
        }

        $arquivo = dirname(__DIR__, 2).'/config/config.php';

        if (! is_file($arquivo)) {
            throw new RuntimeException(
                'Arquivo config/config.php não encontrado. '
                .'Copie config/config.example.php para config/config.php.'
            );
        }

        $dados = require $arquivo;

        if (! is_array($dados)) {
            throw new RuntimeException('config/config.php deve retornar um array.');
        }

        return self::$dados = $dados;
    }
}
