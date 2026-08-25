<?php

// ────────────────────────────────────────────────────────────────
// Funções curtas usadas em várias páginas.
//
// Regra para entrar aqui: é usada em mais de uma página E não tem
// a ver com nenhuma tabela específica. Se tem a ver com uma tabela,
// vai no modelo dela.
// ────────────────────────────────────────────────────────────────


// Escapa texto antes de imprimir no HTML.
//
// Use em TODO valor que vem do banco ou do usuário. Sem isso, um
// nome de produto contendo <script> seria executado pelo navegador
// de quem visita a loja.
function escapar($texto)
{
    return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
}


// 189.9 → "R$ 189,90"
function dinheiro($valor)
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}


// 2026-08-25 14:32:00 → "25/08/2026 14:32"
function dataHora($valor)
{
    if (empty($valor)) {
        return '';
    }

    return date('d/m/Y H:i', strtotime($valor));
}


// Manda o navegador para outro endereço e para a execução.
//
// O exit é obrigatório: header() sozinho não interrompe nada, e o
// código continuaria rodando depois do redirecionamento.
function redirecionar($url)
{
    header('Location: ' . $url);
    exit;
}


function estaLogado()
{
    return isset($_SESSION['usuario_id']);
}


function ehAdmin()
{
    return isset($_SESSION['papel']) && $_SESSION['papel'] == 'admin';
}


// Guarda um aviso para mostrar na próxima página.
// Usado depois de salvar algo e redirecionar.
function avisar($mensagem)
{
    $_SESSION['aviso'] = $mensagem;
}


// Lê o aviso guardado e o apaga, para não aparecer duas vezes.
function pegarAviso()
{
    if (! isset($_SESSION['aviso'])) {
        return null;
    }

    $aviso = $_SESSION['aviso'];
    unset($_SESSION['aviso']);

    return $aviso;
}
