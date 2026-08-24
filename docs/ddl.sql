-- ═══════════════════════════════════════════════════════════════
-- Wonner — script de criação do esquema (DDL)
--
-- Projeto Integrador · IFPR Umuarama
-- Igor M. Delmonaco · Felipe T. Rodrigues
--
-- Gerado a partir da seção 4.2 de docs/TCC.md. Ao alterar, altere nos
-- dois lugares — o documento é a fonte, este arquivo é para execução.
--
-- Uso:  mysql -u root -p < docs/ddl.sql
--
-- Para recriar o esquema do zero, remova o comentário das linhas abaixo.
-- ATENÇÃO: isso apaga todos os dados.
--
-- DROP DATABASE IF EXISTS wonner;
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS wonner
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wonner;

-- ─────────────────────────────────────────────────────────────
-- CADASTROS
-- ─────────────────────────────────────────────────────────────

CREATE TABLE categoria (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(45)  NOT NULL,
    descricao   VARCHAR(255) NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categoria_nome (nome)
) ENGINE = InnoDB;

CREATE TABLE usuario (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome              VARCHAR(100) NOT NULL,
    cpf               VARCHAR(11)  NOT NULL,
    telefone          VARCHAR(15)  NOT NULL,
    email             VARCHAR(100) NOT NULL,
    senha             VARCHAR(255) NOT NULL,
    cep               VARCHAR(8)   NOT NULL,
    endereco          VARCHAR(100) NOT NULL,
    numero            VARCHAR(10)  NOT NULL,
    complemento       VARCHAR(100) NULL,
    cidade            VARCHAR(45)  NOT NULL,
    uf                CHAR(2)      NOT NULL,
    papel             ENUM('admin', 'comprador')                   NOT NULL DEFAULT 'comprador',
    situacao          ENUM('ativo', 'inativo', 'anonimizado')      NOT NULL DEFAULT 'ativo',
    consentimento_em  DATETIME     NOT NULL,
    versao_termos     VARCHAR(20)  NOT NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_usuario_cpf   (cpf),
    UNIQUE KEY uk_usuario_email (email)
) ENGINE = InnoDB;

CREATE TABLE produto (
    id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    categoria_id     INT UNSIGNED  NOT NULL,
    nome             VARCHAR(100)  NOT NULL,
    descricao        TEXT          NOT NULL,
    modelagem        ENUM('regular', 'oversized', 'cropped') NULL,
    valor            DECIMAL(10,2) NOT NULL,
    composicao       TEXT          NULL,
    cuidados         TEXT          NULL,
    envio_devolucao  TEXT          NULL,
    created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_produto_categoria (categoria_id),
    CONSTRAINT fk_produto_categoria
        FOREIGN KEY (categoria_id) REFERENCES categoria (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_produto_valor CHECK (valor >= 0)
) ENGINE = InnoDB;

CREATE TABLE variante_produto (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    produto_id   INT UNSIGNED NOT NULL,
    sku          VARCHAR(30)  NOT NULL,
    cor          VARCHAR(45)  NOT NULL,
    tamanho      ENUM('PP', 'P', 'M', 'G', 'GG', 'XG', 'U') NOT NULL,
    qtd_estoque  INT          NOT NULL DEFAULT 0,
    situacao     ENUM('ativo', 'inativo')                   NOT NULL DEFAULT 'ativo',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_variante_sku (sku),
    UNIQUE KEY uk_variante_combinacao (produto_id, cor, tamanho),
    CONSTRAINT fk_variante_produto
        FOREIGN KEY (produto_id) REFERENCES produto (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_variante_estoque CHECK (qtd_estoque >= 0)
) ENGINE = InnoDB;

CREATE TABLE imagem_variante (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    variante_produto_id  INT UNSIGNED NOT NULL,
    arquivo              VARCHAR(255) NOT NULL,
    ordem                TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_imagem_ordem (variante_produto_id, ordem),
    CONSTRAINT fk_imagem_variante
        FOREIGN KEY (variante_produto_id) REFERENCES variante_produto (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB;

CREATE TABLE faixa_frete (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    cep_inicial  VARCHAR(8)    NOT NULL,
    cep_final    VARCHAR(8)    NOT NULL,
    valor        DECIMAL(10,2) NOT NULL,
    prazo_dias   TINYINT UNSIGNED NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_faixa_cep (cep_inicial, cep_final),
    CONSTRAINT ck_faixa_ordem CHECK (cep_final >= cep_inicial),
    CONSTRAINT ck_faixa_valor CHECK (valor >= 0)
) ENGINE = InnoDB;

-- ─────────────────────────────────────────────────────────────
-- MOVIMENTAÇÕES
-- ─────────────────────────────────────────────────────────────

CREATE TABLE carrinho_item (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id           INT UNSIGNED NOT NULL,
    variante_produto_id  INT UNSIGNED NOT NULL,
    qtde                 SMALLINT UNSIGNED NOT NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_carrinho_item (usuario_id, variante_produto_id),
    KEY idx_carrinho_variante (variante_produto_id),
    CONSTRAINT fk_carrinho_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuario (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_carrinho_variante
        FOREIGN KEY (variante_produto_id) REFERENCES variante_produto (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT ck_carrinho_qtde CHECK (qtde > 0)
) ENGINE = InnoDB;

CREATE TABLE venda (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    usuario_id         INT UNSIGNED  NOT NULL,
    situacao           ENUM('aguardando_pagamento', 'pago', 'separado', 'enviado',
                            'entregue', 'expirado', 'cancelado', 'devolvido')
                                     NOT NULL DEFAULT 'aguardando_pagamento',
    valor_frete        DECIMAL(10,2) NOT NULL,
    destinatario       VARCHAR(100)  NOT NULL,
    cep                VARCHAR(8)    NOT NULL,
    endereco           VARCHAR(100)  NOT NULL,
    numero             VARCHAR(10)   NOT NULL,
    complemento        VARCHAR(100)  NULL,
    cidade             VARCHAR(45)   NOT NULL,
    uf                 CHAR(2)       NOT NULL,
    reserva_expira_em  DATETIME      NOT NULL,
    codigo_rastreio    VARCHAR(50)   NULL,
    data_envio         DATETIME      NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_venda_usuario_data (usuario_id, created_at),
    KEY idx_venda_usuario_situacao (usuario_id, situacao),
    KEY idx_venda_reserva (situacao, reserva_expira_em),
    CONSTRAINT fk_venda_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuario (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_venda_frete CHECK (valor_frete >= 0),
    CONSTRAINT ck_venda_rastreio CHECK (
        situacao NOT IN ('enviado', 'entregue') OR codigo_rastreio IS NOT NULL
    )
) ENGINE = InnoDB;

CREATE TABLE venda_item (
    id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    venda_id             INT UNSIGNED  NOT NULL,
    variante_produto_id  INT UNSIGNED  NOT NULL,
    qtde_vendida         SMALLINT UNSIGNED NOT NULL,
    subtotal             DECIMAL(10,2) NOT NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_venda_item (venda_id, variante_produto_id),
    KEY idx_venda_item_variante (variante_produto_id),
    CONSTRAINT fk_venda_item_venda
        FOREIGN KEY (venda_id) REFERENCES venda (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_venda_item_variante
        FOREIGN KEY (variante_produto_id) REFERENCES variante_produto (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_venda_item_qtde     CHECK (qtde_vendida > 0),
    CONSTRAINT ck_venda_item_subtotal CHECK (subtotal >= 0)
) ENGINE = InnoDB;

CREATE TABLE pagamento (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    venda_id           INT UNSIGNED  NOT NULL,
    metodo             ENUM('pix', 'credito', 'debito')  NOT NULL,
    qtde_parcelas      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    valor              DECIMAL(10,2) NOT NULL,
    situacao           ENUM('iniciado', 'aguardando', 'aprovado',
                            'recusado', 'expirado', 'estornado')
                                     NOT NULL DEFAULT 'iniciado',
    id_externo         VARCHAR(100)  NULL,
    data_confirmacao   DATETIME      NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pagamento_externo (id_externo),
    KEY idx_pagamento_venda (venda_id),
    KEY idx_pagamento_confirmacao (situacao, data_confirmacao),
    CONSTRAINT fk_pagamento_venda
        FOREIGN KEY (venda_id) REFERENCES venda (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_pagamento_valor CHECK (valor > 0),
    CONSTRAINT ck_pagamento_parcelas CHECK (
        qtde_parcelas BETWEEN 1 AND 6
        AND (metodo = 'credito' OR qtde_parcelas = 1)
    )
) ENGINE = InnoDB;

CREATE TABLE entrada_estoque (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    variante_produto_id  INT UNSIGNED NOT NULL,
    qtde                 INT          NOT NULL,
    motivo               ENUM('compra', 'devolucao', 'ajuste') NOT NULL,
    observacao           VARCHAR(255) NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entrada_variante (variante_produto_id, created_at),
    CONSTRAINT fk_entrada_variante
        FOREIGN KEY (variante_produto_id) REFERENCES variante_produto (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT ck_entrada_qtde CHECK (qtde <> 0 AND (qtde > 0 OR motivo = 'ajuste'))
) ENGINE = InnoDB;
