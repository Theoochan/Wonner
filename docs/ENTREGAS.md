# Entregas até o MVP

Plano de execução da **fase 1** definida em [D-10](DECISOES.md): todos os requisitos
essenciais e importantes, com pagamento simulado, rodando em ambiente local.

Fora daqui: a fase 2 (provedor de pagamento real, webhook, hospedagem) e a fase 3
(RF006, RF014, RF015, preço promocional, rastreio automático).

Cada entrega termina em algo **demonstrável** — não em "código escrito". Se não dá para
abrir no navegador e mostrar funcionando, a entrega não fechou.

Tamanho é relativo: **P** cabe numa sessão, **M** em duas ou três, **G** é a maior parte
de uma semana.

---

## E0 — Esqueleto ✅

**Feito.** Branch `estrutura/php`, ainda sem commit.

Front controller, roteador, view com layout, configuração fora da raiz web, autoload
PSR-4, helpers de escape. Cinco arquivos em `app/Core`, ~250 linhas.

**Demonstra:** `GET /` responde 200 renderizando view dentro de layout; `/nao-existe`
responde 404; arquivos estáticos são servidos.

---

## E1 — Banco de dados e acesso a dados · P

| | |
|---|---|
| **Requisitos** | — (infraestrutura) |
| **Fecha no documento** | seção 4.3 (Script DML) |

- Executar `docs/ddl.sql` e conferir que as 11 tabelas e 22 restrições sobem sem erro
- `app/Core/Database.php` — conexão PDO única, consultas preparadas, transações
- Carga de demonstração: categorias, produtos, variantes, imagens, faixas de frete e um
  usuário administrador — é o "estoque fictício" da apresentação **e** o Script DML da
  seção 4.3
- Primeiro Model lendo dados reais: `Categoria::todas()`
- Página inicial listando as categorias vindas do banco

**Demonstra:** a home exibe categorias que vieram do MySQL.

**Verificar:** derrubar e recriar o banco do zero com um comando; a home continua
funcionando.

---

## E2 — Catálogo · G

| | |
|---|---|
| **Requisitos** | RF007 catálogo · RF010 detalhe do produto · RF005 busca |
| **Models** | Categoria, Produto, VarianteProduto, ImagemVariante |

- Vitrine com produtos agrupados por categoria
- Página de produto: galeria de imagens, seleção de cor e tamanho, composição, cuidados,
  produtos da mesma categoria
- **Variantes indisponíveis aparecem riscadas** (D-11) — exige o cálculo de disponibilidade
  da regra 5 da seção 2.4.1, que desconta as reservas vigentes
- Busca por nome, cor, categoria, modelagem e descrição
- Layout e tokens da marca (navy, creme, areia, tijolo; Cinzel, Oswald, Libre Caslon,
  JetBrains Mono) — a base visual, não o acabamento

**Demonstra:** navegar do catálogo até um produto, escolher tamanho, ver um tamanho
esgotado riscado, buscar por "moletom".

**Verificar:** com estoque zerado numa variante, o tamanho aparece riscado e não é
selecionável.

---

## E3 — Contas · M

| | |
|---|---|
| **Requisitos** | RF001 cadastro e autenticação |
| **Models** | Usuario |

- Cadastro com CPF, telefone, endereço e **registro do consentimento** (data, hora, versão
  dos termos — D-20)
- Login e logout com `password_hash` / `password_verify`
- `Core/Session.php` e `Core/Csrf.php`
- `session_regenerate_id(true)` no login
- Verificação de papel: rotas de `routes/admin.php` exigem `papel = 'admin'`
- Verificação de situação: `inativo` e `anonimizado` não acessam

**Demonstra:** criar conta, sair, entrar de novo; tentar abrir uma rota de administração
como comprador e receber 403.

**Verificar:** formulário sem token CSRF é rejeitado; senha não aparece em texto no banco;
CPF duplicado é recusado pela restrição de unicidade.

---

## E4 — Carrinho · M

| | |
|---|---|
| **Requisitos** | RF003 inserir itens · RF002 calcular valores |
| **Models** | CarrinhoItem |

- Adicionar variante com quantidade; a mesma variante **soma** em vez de duplicar
- Alterar quantidade e remover item
- Carrinho **persistente**: sobrevive a logout e a novos acessos (D-09)
- Exibe o preço **vigente** do catálogo, sem congelar nada
- Contador de itens no cabeçalho

**Demonstra:** adicionar peças, sair, voltar no dia seguinte e encontrar a sacola como
estava.

**Verificar:** alterar o preço do produto no banco muda o valor exibido no carrinho — é o
comportamento correto, e é o contraste com o que acontece no pedido.

---

## E5 — Checkout e pagamento simulado · G 🔴

**A entrega mais difícil, e a que concentra o risco do projeto.**

| | |
|---|---|
| **Requisitos** | RF004 realizar compra · RF008 confirmar pagamento |
| **Models** | Venda, VendaItem, Pagamento |

- Iniciar checkout: cancelar o pendente anterior (D-31), copiar os itens do carrinho,
  **congelar** o subtotal, copiar o endereço, calcular o frete pela faixa de CEP, gravar
  `reserva_expira_em` com 15 minutos
- **Verificação de disponibilidade sob bloqueio da variante** (regra 10) — sem isso, dois
  clientes simultâneos reservam a mesma peça
- Seleção de método e de parcelas, com o teto calculado pelo valor do pedido (D-03)
- `GatewayFake` **assíncrono**: cria o pagamento em `aguardando` e devolve um identificador
  externo (D-06)
- Ação de confirmação **idempotente**, chamada tanto pelo botão de simulação quanto,
  na fase 2, pelo webhook — mesmo método (D-06)
- Na confirmação: baixa de estoque e mudança de situação **na mesma transação** (regra 8),
  e os itens comprados saem do carrinho
- Rotina de expiração: script que leva a `expirado` as vendas com reserva vencida e libera
  as reservas
- Cronômetro visível no checkout

**Demonstra:** comprar de ponta a ponta; deixar expirar e ver o estoque voltar; recusar o
pagamento e tentar de novo, com as duas tentativas registradas.

**Verificar:**
- disparar a confirmação duas vezes com o mesmo identificador externo → o estoque baixa uma
  vez só
- abrir dois checkouts do mesmo cliente → o primeiro fica `cancelado`
- com uma peça em estoque, dois clientes tentando ao mesmo tempo → um recebe erro claro

---

## E6 — Meus pedidos · P

| | |
|---|---|
| **Requisitos** | RF011 consultar pedidos |

- Lista dos pedidos do cliente, mais recente primeiro, com a situação
- Código de rastreio exibido junto da situação, quando houver (D-11)

**Demonstra:** o cliente entra e vê o pedido que acabou de pagar.

---

## E7 — Painel: cadastros e estoque · G

| | |
|---|---|
| **Requisitos** | RF012 gerenciar cadastros · RF009 faixas de frete · RF016 entrada de estoque |
| **Models** | FaixaFrete, EntradaEstoque |

- CRUD de categoria, produto, variante, imagens, faixas de frete e usuários, com as
  operações da seção 1.4.3
- Upload de imagem de variante, com ordenação
- Validação de **sobreposição** entre faixas de CEP (regra 3)
- Tela de entrada de estoque aceitando **várias variantes do mesmo produto numa submissão**
  (RF016) — P, M, G, GG de uma vez
- Redefinição de senha não existe: o MVP aceita a perda de conta (D-15)

**Demonstra:** cadastrar um produto do zero — categoria, produto, três variantes, imagens,
estoque — e ele aparece na vitrine.

**Verificar:** tentar excluir um produto que já foi vendido → o banco recusa (`RESTRICT`),
e a mensagem explica que se deve inativar.

---

## E8 — Painel: pedidos e relatórios · M

| | |
|---|---|
| **Requisitos** | RF013 processar pedido |
| **Fecha no documento** | seção 4.4 (consultas dos relatórios) |

- Fila de pedidos pagos, com avanço de situação: separado → enviado → entregue
- Registro do código de rastreio, obrigatório para enviar (garantido por `CHECK`)
- Registro de cancelamento e devolução (D-18)
- Os **cinco relatórios** de D-17, cada um com sua consulta SQL — que é também o conteúdo
  da seção 4.4

**Demonstra:** levar um pedido de `pago` a `entregue`; abrir os cinco relatórios com dados
reais.

**Verificar:** os relatórios de venda **não** incluem pedidos `aguardando_pagamento`,
`expirado` nem `cancelado` (regra 1 de D-17).

---

## E9 — Acabamento visual · M

| | |
|---|---|
| **Fecha pendências** | DS-01 |

- Aplicar as correções de DS-01 nas telas de design antes de traduzi-las
- Traduzir as quatro telas para os templates: homepage, produto, checkout, sobre
- Responsividade
- Estados vazios: sacola vazia, busca sem resultado, nenhum pedido

**Demonstra:** a loja parece a Wonner, não HTML sem estilo.

---

## E10 — Documento e diagramas · M

Corre em paralelo, não depende de código.

| | |
|---|---|
| **Fecha pendências** | DG-01, DG-02 |

- Redesenhar os três diagramas (2.1, 2.3, 2.4) no Astah e no Workbench
- Desenhar o diagrama de casos de uso (2.2) e associar cada RF ao seu caso de uso
- Seção 3 (interfaces de vídeo e impressas) — capturas das telas prontas
- Seção 4.1 (protótipo)
- Seção 5 (referências) — as sete citações usadas no texto

---

## Ordem e dependências

```
E0 ✅ ──> E1 ──> E2 ──────────────> E9
                 │
                 └──> E3 ──> E4 ──> E5 ──> E6
                              │      │
                              └──────┴───> E7 ──> E8

E10 corre em paralelo, a qualquer momento
```

`E5` depende de `E4` (precisa de carrinho), de `E3` (precisa de usuário) e das faixas de
frete — que na `E1` entram pela carga de dados, sem esperar a tela de cadastro da `E7`.

---

## Se o prazo apertar

A ordem honesta de sacrifício, do que menos dói para o que mais dói:

1. **Acabamento visual (E9)** — a loja fica feia, mas funciona. Uma tela bem-feita e as
   outras simples já sustenta a apresentação.
2. **Amplitude do painel (E7)** — cadastrar imagens ou usuários direto no banco, deixando
   no painel só produto, variante e estoque.
3. **Relatórios (E8)** — entregar as cinco consultas SQL documentadas na seção 4.4 mesmo
   sem a tela de cada uma.

**Não sacrificar:** `E5`. Ela é o núcleo do trabalho — reserva de estoque, confirmação
assíncrona, idempotência e transação são o que há de tecnicamente interessante para
defender. Um e-commerce sem catálogo bonito é um e-commerce; sem checkout correto, não é
nada.
