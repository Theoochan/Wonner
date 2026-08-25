<?php

// ────────────────────────────────────────────────────────────────
// Modelo de configuração.
//
// Copie este arquivo para incluir/config.php e ajuste os valores.
// O config.php não vai para o Git, porque tem a senha do banco.
// ────────────────────────────────────────────────────────────────


// ── Banco de dados ──────────────────────────────────────────────

const BANCO_HOST  = '127.0.0.1';
const BANCO_NOME  = 'wonner';
const BANCO_USER  = 'root';
const BANCO_SENHA = '';


// ── Aplicação ───────────────────────────────────────────────────

const SITE_NOME = 'Wonner';

// Em true, mostra o erro na tela. Em produção deve ser false:
// mensagem de erro revela caminho de arquivo e trecho de consulta.
const MOSTRAR_ERROS = true;


// ── Regras de negócio ───────────────────────────────────────────
// Ficam aqui, e não espalhadas pelo código, porque vêm de decisões
// registradas em docs/DECISOES.md. Mudar a regra é mudar o número.

// D-01: minutos que o estoque fica reservado no checkout.
const RESERVA_MINUTOS = 15;

// D-03: número máximo de parcelas no cartão de crédito.
const PARCELAS_MAX = 6;

// D-03: valor mínimo de cada parcela, em reais.
const PARCELA_MINIMA = 50.00;

// D-18: prazo de arrependimento em dias corridos (CDC art. 49).
const ARREPENDIMENTO_DIAS = 7;
