<?php

/**
 * Modelo de configuração. Copie para config/config.php e ajuste.
 *
 *   cp config/config.example.php config/config.php
 *
 * O arquivo config.php não é versionado, porque guarda a senha do banco.
 * Esta pasta fica FORA de public/, então nem ele nem este modelo são
 * acessíveis por URL.
 */

return [
    'app' => [
        'nome'  => 'Wonner',
        'url'   => 'http://localhost:8000',
        // Em true, exibe a mensagem e o rastro do erro na tela.
        // Em produção deve ser false: erro detalhado revela caminhos e consultas.
        'debug' => true,
    ],

    'banco' => [
        'host'    => '127.0.0.1',
        'porta'   => 3306,
        'nome'    => 'wonner',
        'usuario' => 'root',
        'senha'   => '',
        'charset' => 'utf8mb4',
    ],

    /**
     * Constantes de negócio. Ficam aqui, e não espalhadas pelo código,
     * porque decorrem de decisões registradas em docs/DECISOES.md — e
     * mudar uma regra deve ser mudar um número, em um lugar.
     */
    'negocio' => [
        // D-01: prazo de reserva do estoque no checkout, em minutos.
        // É também a validade da cobrança PIX.
        'reserva_minutos' => 15,

        // D-03: parcelamento apenas no crédito, sem juros.
        'parcelas_max' => 6,

        // D-03: valor mínimo por parcela, em centavos (R$ 50,00).
        // Em centavos para não comparar float com float.
        'parcela_valor_minimo' => 5000,

        // D-10 / D-18: prazo de arrependimento em dias corridos (CDC art. 49).
        'arrependimento_dias' => 7,
    ],
];
