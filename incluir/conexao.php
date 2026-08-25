<?php

// ────────────────────────────────────────────────────────────────
// Conexão com o banco de dados.
//
// A conexão é criada na primeira vez que alguém chama conexao(),
// e não antes. Assim uma página que não usa o banco não tenta
// conectar — e o site continua abrindo mesmo com o MySQL desligado.
// ────────────────────────────────────────────────────────────────

function conexao()
{
    // "static" faz a variável guardar o valor entre chamadas.
    // Na primeira vez ela é null, cria a conexão e guarda; nas
    // seguintes devolve a mesma. Uma conexão por requisição.
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . BANCO_HOST
             . ';dbname=' . BANCO_NOME
             . ';charset=utf8mb4';

        $pdo = new PDO($dsn, BANCO_USER, BANCO_SENHA);

        // Erro de SQL passa a lançar exceção, em vez de falhar calado.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // O resultado vem como array com nome de coluna:
        // $produto['nome'] em vez de $produto[1].
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    return $pdo;
}
