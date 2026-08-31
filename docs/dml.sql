-- ═══════════════════════════════════════════════════════════════
-- Wonner — carga de dados (DML)
--
-- Projeto Integrador · IFPR Umuarama
-- Igor M. Delmonaco · Felipe T. Rodrigues
--
-- Corresponde à seção 4.3 do documento, e é também a carga de
-- demonstração usada na apresentação.
--
-- Uso:  mysql -u root -p < docs/dml.sql
--       (ou abrir no Workbench e executar)
--
-- Pré-requisito: docs/ddl.sql já executado.
--
-- A ordem das inserções segue as dependências entre as tabelas:
-- categoria e cor antes de produto e variante, e assim por diante.
-- Inverter a ordem faz a chave estrangeira recusar.
--
-- Não há vendas nem itens de carrinho: esses registros nascem do
-- sistema em funcionamento. Criá-los à mão esconderia defeito.
-- ═══════════════════════════════════════════════════════════════

USE wonner;

-- ───────────────────────────────────────────────────────────────
-- Para recarregar do zero, remova o comentário do bloco abaixo.
-- A ordem é a INVERSA da inserção: primeiro os filhos, depois os
-- pais, senão a chave estrangeira impede a exclusão.
--
-- DELETE FROM entrada_estoque;
-- DELETE FROM imagem_produto;
-- DELETE FROM carrinho_item;
-- DELETE FROM variante_produto;
-- DELETE FROM produto;
-- DELETE FROM categoria;
-- DELETE FROM cor;
-- DELETE FROM faixa_frete;
-- DELETE FROM usuario;
-- ───────────────────────────────────────────────────────────────


-- ═══ CATEGORIAS ════════════════════════════════════════════════

INSERT INTO categoria (id, nome, descricao) VALUES
(1, 'Camisetas',  'Algodão pesado, modelagens regular e oversized.'),
(2, 'Moletons',   'Moletom 450g, com e sem capuz.'),
(3, 'Jaquetas',   'Peças estruturadas em lã e sarja.'),
(4, 'Acessórios', 'Bonés, meias e complementos.');


-- ═══ CORES ═════════════════════════════════════════════════════
--
-- O hexadecimal desenha a amostra de cor na página do produto.
-- A jaqueta é bicolor: usa também o hex_secundario, e a amostra
-- sai dividida em duas metades.

INSERT INTO cor (id, nome, hex, hex_secundario) VALUES
(1, 'Navy',       '#0f1e3d', NULL),
(2, 'Creme',      '#f4ecd8', NULL),
(3, 'Creme/Navy', '#f4ecd8', '#0f1e3d'),
(4, 'Tijolo',     '#a63a2a', NULL);


-- ═══ PRODUTOS ══════════════════════════════════════════════════

INSERT INTO produto
    (id, categoria_id, nome, descricao, modelagem, valor, composicao, cuidados, envio_devolucao)
VALUES
(1, 3, 'Varsity "Rivalry" Wool',
    'Jaqueta college em lã batida com mangas em couro pull-up e patch W bordado no peito.',
    'regular', 899.00,
    '80% lã, 20% poliéster. Mangas em couro legítimo pull-up. Forro em cetim navy com bordado interno.',
    'Lavar a seco. Não usar alvejante. Passar a ferro morno pelo avesso.',
    'Envio em até 2 dias úteis após a confirmação do pagamento. Trocas e devoluções em até 7 dias corridos do recebimento.'),

(2, 2, 'Hoodie "Own"',
    'Moletom pesado com capuz forrado, cordão encerado e bordado frontal.',
    'oversized', 429.00,
    'Moletom 450g. 80% algodão, 20% poliéster. Punhos e barra em ribana.',
    'Lavar à mão ou em ciclo delicado, com água fria. Não torcer. Secar à sombra.',
    'Envio em até 2 dias úteis após a confirmação do pagamento. Trocas e devoluções em até 7 dias corridos do recebimento.'),

(3, 1, 'Tee "Est. MMXXV"',
    'Camiseta em algodão penteado com estampa serigráfica do ano de fundação.',
    'regular', 189.00,
    'Algodão 240g. 100% algodão penteado, fio 30.1.',
    'Lavar do avesso com água fria. Não passar sobre a estampa.',
    'Envio em até 2 dias úteis após a confirmação do pagamento. Trocas e devoluções em até 7 dias corridos do recebimento.'),

(4, 4, 'Boné "W" 6-panel',
    'Boné de seis gomos em sarja lavada, com o W bordado à frente e fecho metálico.',
    NULL, 159.00,
    'Sarja 100% algodão, lavada. Aba curva estruturada.',
    'Limpar com pano úmido. Não lavar em máquina.',
    'Envio em até 2 dias úteis após a confirmação do pagamento. Trocas e devoluções em até 7 dias corridos do recebimento.'),

(5, 1, 'Tee "Rivalry Patch"',
    'Camiseta em algodão pesado com patch bordado aplicado no peito.',
    'oversized', 229.00,
    'Algodão 260g. 100% algodão. Patch bordado em fio de alta densidade.',
    'Lavar do avesso com água fria. Não passar sobre o patch.',
    'Envio em até 2 dias úteis após a confirmação do pagamento. Trocas e devoluções em até 7 dias corridos do recebimento.'),

(6, 4, 'Meia "W" par',
    'Par de meias em algodão canelado, com o W tecido no punho.',
    NULL, 69.00,
    'Algodão 70%, poliamida 25%, elastano 5%.',
    'Lavar em máquina, ciclo normal, com água fria.',
    'Envio em até 2 dias úteis após a confirmação do pagamento. Trocas e devoluções em até 7 dias corridos do recebimento.');


-- ═══ VARIANTES ═════════════════════════════════════════════════
--
-- O SKU segue o formato WNR-<produto>-<tamanho>-<cor>.
-- Duas variantes nascem com estoque zero, de propósito: servem para
-- conferir se a vitrine exibe o tamanho riscado (decisão D-11).
--
-- cor_id:  1 Navy · 2 Creme · 3 Creme/Navy · 4 Tijolo

INSERT INTO variante_produto (id, produto_id, cor_id, sku, tamanho, qtd_estoque, situacao) VALUES
-- Varsity "Rivalry" Wool · Creme/Navy
( 1, 1, 3, 'WNR-VJR-P-CN',   'P',   5, 'ativo'),
( 2, 1, 3, 'WNR-VJR-M-CN',   'M',   3, 'ativo'),
( 3, 1, 3, 'WNR-VJR-G-CN',   'G',   4, 'ativo'),
( 4, 1, 3, 'WNR-VJR-GG-CN',  'GG',  0, 'ativo'),   -- esgotado

-- Hoodie "Own" · Navy
( 5, 2, 1, 'WNR-HDO-P-NV',   'P',   8, 'ativo'),
( 6, 2, 1, 'WNR-HDO-M-NV',   'M',  12, 'ativo'),
( 7, 2, 1, 'WNR-HDO-G-NV',   'G',  10, 'ativo'),
( 8, 2, 1, 'WNR-HDO-GG-NV',  'GG',  6, 'ativo'),

-- Hoodie "Own" · Creme
( 9, 2, 2, 'WNR-HDO-P-CR',   'P',   7, 'ativo'),
(10, 2, 2, 'WNR-HDO-M-CR',   'M',   9, 'ativo'),
(11, 2, 2, 'WNR-HDO-G-CR',   'G',   8, 'ativo'),
(12, 2, 2, 'WNR-HDO-GG-CR',  'GG',  4, 'ativo'),

-- Tee "Est. MMXXV" · Creme
(13, 3, 2, 'WNR-TMX-P-CR',   'P',   0, 'ativo'),   -- esgotado
(14, 3, 2, 'WNR-TMX-M-CR',   'M',  15, 'ativo'),
(15, 3, 2, 'WNR-TMX-G-CR',   'G',  14, 'ativo'),
(16, 3, 2, 'WNR-TMX-GG-CR',  'GG',  9, 'ativo'),

-- Tee "Est. MMXXV" · Navy
(17, 3, 1, 'WNR-TMX-P-NV',   'P',  11, 'ativo'),
(18, 3, 1, 'WNR-TMX-M-NV',   'M',  18, 'ativo'),
(19, 3, 1, 'WNR-TMX-G-NV',   'G',  16, 'ativo'),
(20, 3, 1, 'WNR-TMX-GG-NV',  'GG',  7, 'ativo'),

-- Boné "W" 6-panel
(21, 4, 1, 'WNR-BNW-U-NV',   'U',  20, 'ativo'),
(22, 4, 4, 'WNR-BNW-U-TJ',   'U',  12, 'ativo'),

-- Tee "Rivalry Patch" · Navy
(23, 5, 1, 'WNR-TRP-M-NV',   'M',   6, 'ativo'),
(24, 5, 1, 'WNR-TRP-G-NV',   'G',   5, 'ativo'),
(25, 5, 1, 'WNR-TRP-GG-NV',  'GG',  3, 'ativo'),

-- Meia "W" par · Navy
(26, 6, 1, 'WNR-MEW-U-NV',   'U',  30, 'ativo');


-- ═══ IMAGENS ═══════════════════════════════════════════════════
--
-- A imagem pertence ao produto e é qualificada pela cor, não pelo
-- tamanho: a foto do moletom navy é a mesma em P, M, G e GG.
--
-- Os arquivos ainda não existem em publico/uploads — são os nomes
-- previstos. A vitrine deve tratar imagem ausente sem quebrar.

INSERT INTO imagem_produto (produto_id, cor_id, arquivo, ordem) VALUES
-- Varsity · Creme/Navy · duas fotos, para exercitar a galeria
(1, 3, 'varsity-rivalry-creme-navy-01.jpg', 1),
(1, 3, 'varsity-rivalry-creme-navy-02.jpg', 2),

-- Hoodie "Own"
(2, 1, 'hoodie-own-navy-01.jpg',  1),
(2, 2, 'hoodie-own-creme-01.jpg', 1),

-- Tee "Est. MMXXV"
(3, 2, 'tee-mmxxv-creme-01.jpg', 1),
(3, 1, 'tee-mmxxv-navy-01.jpg',  1),

-- Boné "W" 6-panel
(4, 1, 'bone-w-navy-01.jpg',   1),
(4, 4, 'bone-w-tijolo-01.jpg', 1),

-- Tee "Rivalry Patch"
(5, 1, 'tee-rivalry-patch-navy-01.jpg', 1),

-- Meia "W" par
(6, 1, 'meia-w-navy-01.jpg', 1);


-- ═══ ENTRADAS DE ESTOQUE ═══════════════════════════════════════
--
-- Uma entrada por variante, com a mesma quantidade do qtd_estoque
-- correspondente. Assim o contador e o histórico nascem coerentes:
-- somar as entradas de uma variante dá exatamente o seu estoque.
--
-- As duas variantes esgotadas (4 e 13) não recebem entrada.

INSERT INTO entrada_estoque (variante_produto_id, qtde, motivo, observacao) VALUES
( 1,  5, 'compra', 'Produção inicial'),
( 2,  3, 'compra', 'Produção inicial'),
( 3,  4, 'compra', 'Produção inicial'),
( 5,  8, 'compra', 'Produção inicial'),
( 6, 12, 'compra', 'Produção inicial'),
( 7, 10, 'compra', 'Produção inicial'),
( 8,  6, 'compra', 'Produção inicial'),
( 9,  7, 'compra', 'Produção inicial'),
(10,  9, 'compra', 'Produção inicial'),
(11,  8, 'compra', 'Produção inicial'),
(12,  4, 'compra', 'Produção inicial'),
(14, 15, 'compra', 'Produção inicial'),
(15, 14, 'compra', 'Produção inicial'),
(16,  9, 'compra', 'Produção inicial'),
(17, 11, 'compra', 'Produção inicial'),
(18, 18, 'compra', 'Produção inicial'),
(19, 16, 'compra', 'Produção inicial'),
(20,  7, 'compra', 'Produção inicial'),
(21, 20, 'compra', 'Produção inicial'),
(22, 12, 'compra', 'Produção inicial'),
(23,  6, 'compra', 'Produção inicial'),
(24,  5, 'compra', 'Produção inicial'),
(25,  3, 'compra', 'Produção inicial'),
(26, 30, 'compra', 'Produção inicial');


-- ═══ FAIXAS DE FRETE ═══════════════════════════════════════════
--
-- Cobrem 01000000 a 99999999 sem sobreposição — a regra 3 da seção
-- 2.4.1 exige, e o banco não consegue verificar isso sozinho.
-- Origem considerada: São Paulo.

INSERT INTO faixa_frete (cep_inicial, cep_final, valor, prazo_dias) VALUES
('01000000', '19999999', 19.90,  3),   -- São Paulo
('20000000', '39999999', 24.90,  4),   -- Rio de Janeiro, Espírito Santo, Minas Gerais
('40000000', '65999999', 39.90,  9),   -- Nordeste
('66000000', '69999999', 49.90, 12),   -- Norte
('70000000', '79999999', 34.90,  7),   -- Centro-Oeste
('80000000', '99999999', 27.90,  5);   -- Sul


-- ═══ USUÁRIO ADMINISTRADOR ═════════════════════════════════════
--
-- A senha gravada aqui NÃO É VÁLIDA: não é um hash, então a
-- comparação sempre falha e ninguém entra com esta conta.
--
-- Para definir a sua senha, gere o hash e atualize o registro —
-- o procedimento está no LEIAME.md. O hash não é versionado de
-- propósito: este repositório é público.

INSERT INTO usuario
    (id, nome, cpf, telefone, email, senha,
     cep, endereco, numero, complemento, cidade, uf,
     papel, situacao, consentimento_em, versao_termos)
VALUES
(1, 'Administrador', '11144477735', '11987654321', 'admin@wonner.com.br',
    '*SENHA_NAO_DEFINIDA*',
    '01310000', 'Av. Paulista', '1578', NULL, 'São Paulo', 'SP',
    'admin', 'ativo', NOW(), '1.0');
