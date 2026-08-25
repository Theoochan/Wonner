<?php

// Exige que o visitante esteja logado.
// Incluído pelo index.php nas rotas que precisam de conta.

if (! estaLogado()) {
    redirecionar('/entrar');
}
