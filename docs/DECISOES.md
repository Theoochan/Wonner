# Decisões

Destino de tudo que sai de [PENDENCIAS.md](PENDENCIAS.md). Este arquivo **só cresce**.

Serve a dois propósitos: manter a coerência do sistema ao longo do desenvolvimento,
e ser a fonte do capítulo de justificativas do TCC — banca não pergunta "o que você
fez", pergunta "por que assim e não de outro jeito".

Formato de cada entrada:

```
## D-nn — Título da decisão
**Pendência:** RQ-01 · **Data:** aaaa-mm-dd

**Contexto:** o que estava em aberto e por quê.
**Decisão:** o que foi decidido, em uma frase afirmativa.
**Alternativas descartadas:** o que mais estava na mesa e por que não.
**Consequências:** o que isso obriga, permite ou impede daqui em diante.
```

O campo **Alternativas descartadas** é o mais importante para a defesa: mostra que
houve escolha, não desconhecimento.

---

## D-01 — Estoque é reservado no início do checkout, não no carrinho
**Pendência:** RQ-04 · **Data:** 2026-08-17

**Contexto:** `variante_produto.qtdEstoque` existia no modelo sem nenhuma regra que o
governasse. Não havia definição de quando a peça deixa de estar disponível para outros
clientes, nem de quando a quantidade é efetivamente decrementada.

**Decisão:** ao iniciar o checkout, a quantidade dos itens é **reservada** por uma
janela de tempo determinada. A **baixa definitiva** no estoque ocorre na confirmação do
pagamento. O fim da janela sem confirmação, ou o cancelamento explícito, **libera** a
reserva. Adicionar item ao carrinho **não** reserva nada.

**Alternativas descartadas:**

- *Reservar ao adicionar ao carrinho.* Todo carrinho abandonado travaria peça. Num drop
  de 24 peças, uma dezena de curiosos com carrinho aberto esgotaria o estoque sem
  nenhuma venda ter ocorrido.
- *Baixar só na confirmação, sem reserva alguma.* Dois clientes podem pagar a última
  peça ao mesmo tempo, e um dos dois precisa ser estornado depois — pior experiência e
  trabalho manual.

**Consequências:**

- A disponibilidade exibida ao cliente passa a ser `qtdEstoque − reservado`, não
  `qtdEstoque` puro.
- Exige um processo agendado que varre reservas vencidas e as libera.
- A janela de reserva é o **mesmo** valor da validade da cobrança PIX (ver D-02): um
  número governa os dois, para que não possam divergir.
- `venda.status` passa a ter estados explícitos (ver D-02).

---

## D-02 — Pagamento é entidade própria, com confirmação assíncrona e N tentativas por venda
**Pendência:** RQ-05, MD-04 · **Data:** 2026-08-17

**Contexto:** o RF004 tratava "o cliente foi direcionado à tela de pagamento" como o fim
do fluxo, e `venda` carregava `formaRecebimento` e `qtdeParcelas` diretamente. PIX é
assíncrono — o cliente pode fechar a aba — e cartão pode ser recusado ou entrar em
análise. Não havia estado intermediário nem registro de tentativas.

**Decisão:** o pedido só avança **após confirmação** do pagamento pelo provedor.
`pagamento` é entidade própria e uma `venda` tem **N** pagamentos, um por tentativa.
`metodo` e `qtdeParcelas` pertencem ao pagamento, não à venda.

Máquina de estados da venda:

```
carrinho → aguardando_pagamento → pago → enviado → entregue
                    │              │
                    ├─ expirado    └─ cancelado / devolvido
                    └─ cancelado
```

Máquina de estados do pagamento:

```
iniciado → aguardando → aprovado
                ├─ recusado
                ├─ expirado
                └─ estornado
```

**Alternativas descartadas:**

- *Um pagamento por venda, sobrescrito a cada tentativa.* A segunda tentativa apagaria o
  registro da primeira, perdendo a informação de que houve recusa — e "quantas vendas
  falham no cartão e convertem em PIX" é justamente o que um relatório quer saber.
- *Tratar "clicou em pagar" como pago.* Simples e errado: liberaria pedido não pago.

**Consequências:**

- `pagamento.dataConfirmacao` é a origem de dados do Relatório de Pagamentos Recebidos
  (seção 3.2.4), que antes não tinha de onde sair.
- A confirmação precisa ser **idempotente**: o provedor reenvia a mesma notificação
  várias vezes. Resolvido com unicidade em `pagamento.idExterno`.
- Saem de `venda`: `formaRecebimento` e `qtdeParcelas`.
- Consultar "como este pedido foi pago" passa a exigir join com a tentativa aprovada.

---

## D-03 — Parcelamento é atributo do pagamento, exclusivo do crédito, sem juros
**Pendência:** RQ-05, TP-04 · **Data:** 2026-08-17

**Contexto:** a regra dizia apenas "pagamento à vista ou a prazo (crédito)", sem definir
limites, juros, ou onde a informação mora. `qtdeParcelas` estava em `venda` como
`VARCHAR(45)`.

**Decisão:** **não existe tabela de parcelas.** O parcelamento é uma única coluna,
`pagamento.qtdeParcelas TINYINT`, permitida **apenas** quando `metodo = credito`; PIX e
débito são sempre `1`. **Sem juros** — a Wonner absorve a taxa e o valor cobrado é igual
ao valor do pedido. Limite:

```
maxParcelas = min(6, floor(valorTotal / 50))    -- mínimo 1
```

O sistema **não registra, não acompanha e não consulta** as parcelas individuais. O valor
por parcela é calculado para exibição (`valor / qtdeParcelas`) e nunca persistido.
`qtdeParcelas` tem três usos, todos de leitura: informar o provedor no momento da
cobrança, exibir no pedido, e analisar o comportamento de pagamento.

**Alternativas descartadas:**

- *Tabela `parcela` com cronograma de cobranças.* Erro conceitual: quem parcela é o
  emissor do cartão, não a loja. A Wonner recebe o valor de uma vez do adquirente. Uma
  tabela de parcelas a receber do cliente descreveria uma cobrança que o sistema não faz
  — `dataPagamento` nunca seria preenchido e `status` nunca sairia de "pendente".
- *Parcelamento com juros repassados.* Exigiria guardar valor do pedido e valor cobrado
  separadamente, além de matemática financeira sem contribuição para o trabalho.

**Consequências:**

- Do ponto de vista da Wonner, venda parcelada é venda à vista com taxa maior.
- O número de opções no seletor de parcelas é derivado do total, não fixo.
- Validação: `metodo != 'credito' AND qtdeParcelas > 1` é estado inválido.

---

## D-04 — Frete calculado por faixa de CEP, em tabela própria
**Pendência:** RQ-06 · **Data:** 2026-08-17

**Contexto:** a regra dizia "a entrega é realizada pela empresa Correios, a qual a taxa
está inclusa no frete calculado", mas nenhum RF descrevia o cálculo e o produto não tem
peso nem dimensões — sem os quais nenhuma transportadora calcula nada.

**Decisão:** o frete é obtido de uma tabela de faixas de CEP (faixa → valor → prazo),
mantida pelo administrador. A transportadora passa a ser detalhe operacional, não regra
de sistema.

**Alternativas descartadas:**

- *Integração com a API dos Correios.* Exigiria peso e dimensões em todo produto, e
  colocaria uma segunda dependência externa instável no caminho crítico da compra —
  ainda mais custosa por já haver uma integração de pagamento no escopo.
- *Frete fixo único.* Simples demais para ser realista: não distingue entregar em
  Umuarama de entregar no Amazonas.

**Consequências:**

- `produto` não precisa de peso nem dimensões.
- Nova tabela de faixas + tela de manutenção no painel administrativo.
- O valor do frete é congelado em `venda.valorFrete` no fechamento (a tabela pode mudar
  depois sem alterar pedidos passados).
- A RN sobre Correios é reescrita.

---

## D-05 — "Drop" é marketing, não entidade
**Pendência:** RQ-12 · **Data:** 2026-08-17

**Contexto:** toda a homepage do design é construída em volta de "Drop 03", com número
de edição, contagem regressiva e "edição limitada" — e o documento de requisitos não
menciona drop uma única vez.

**Decisão:** drop **não** vira entidade. O número do drop, o contador e os textos de
edição limitada são conteúdo configurável da vitrine, sem vínculo com o catálogo.

**Alternativas descartadas:**

- *Drop como coleção com data de início e fim.* Daria dimensão temporal ao catálogo
  (produto pertence a um drop, drop abre e fecha), o que é fiel ao design mas acrescenta
  entidade, regras de visibilidade por data e uma nova tela administrativa — sem
  sustentar nenhum requisito existente.

**Consequências:**

- O catálogo é atemporal: produto está ativo ou inativo, sem janela de venda.
- Se o negócio realmente operar por drops, isso volta como evolução.

---

## D-06 — Gateway atrás de uma porta, entregue em duas fases
**Pendência:** RQ-05 · **Data:** 2026-08-17

**Contexto:** era preciso decidir entre integrar um gateway real em sandbox ou simular o
pagamento, sabendo que o objetivo é primeiro garantir os demais requisitos e só depois
fechar o pagamento de ponta a ponta, com teste ao vivo e sistema hospedado.

**Decisão:** o domínio conversa apenas com uma interface `GatewayPagamento`. Duas
implementações: `GatewayFake` (fase 1) e o provedor real (fase 2), intercambiáveis por
configuração. **O fake é assíncrono** — cria o pagamento em `aguardando` e a confirmação
chega por uma ação separada, exatamente como o webhook fará.

**Alternativas descartadas:**

- *Só o gateway real, desde o início.* Bloquearia todos os outros requisitos atrás de
  uma integração externa, e exigiria túnel HTTP público durante todo o desenvolvimento.
- *Só o simulado, para sempre.* Perderia o único problema técnico genuíno de um
  e-commerce (confirmação assíncrona idempotente), que é bom material de implementação.
- *Fake síncrono ("clicou → pago") na fase 1.* É a alternativa mais tentadora e a mais
  custosa: o sistema inteiro passaria a assumir pagamento instantâneo, e a fase 2
  exigiria refazer a máquina de estados, a reserva de estoque e as notificações. O fake
  precisa ser assíncrono desde o primeiro dia justamente para que a fase 2 seja uma
  troca de classe.

**Consequências:**

- O fake permanece em uso nos testes automatizados e na apresentação, para que a
  demonstração nunca dependa de rede.
- A idempotência da confirmação é implementada na fase 1, não adaptada na fase 2.
- Hospedagem entra junto com a fase 2, pois o webhook exige URL pública com HTTPS.

---

## D-07 — Fronteira de escopo confirmada
**Pendência:** RQ-17 · **Data:** 2026-08-17

**Contexto:** era preciso declarar explicitamente quais recursos usuais de e-commerce
ficam fora, para que a ausência de cada um seja escolha registrada e não omissão. Quatro
dos recursos avaliados apareciam nas telas desenhadas, o que torna o corte também uma
edição de design (ver D-08).

**Decisão:** ficam fora avaliações/comentários, cupom de desconto, favoritos, múltiplos
endereços por cliente, rastreio automático por API, recuperação de carrinho abandonado,
multi-vendedor, internacionalização e emissão de NF-e (ver tabela ao final).

**Mantido, contra a proposta inicial:** **produtos relacionados**, na forma de "produtos
da mesma categoria" — uma consulta por categoria, sem mecanismo de recomendação. O design
já reserva espaço para a seção em duas telas e a categoria vai existir de todo modo.

**Consequências:**

- Cortar rastreio por API implica um campo manual de código de rastreio na venda,
  preenchido pelo administrador.
- Cortar múltiplos endereços **não** dispensa congelar o endereço de entrega no pedido
  (pendência MD-03), que é problema distinto.
- Cortar recuperação de carrinho abandonado **não** afeta a persistência do carrinho
  (ver D-09), que é requisito.

---

## D-08 — O design é alinhado ao documento, não o contrário
**Pendência:** RQ-18 · **Data:** 2026-08-17

**Contexto:** a varredura de `PDP.dc.html` e `Checkout.dc.html` encontrou quatorze
recursos presentes nas telas e ausentes do documento de requisitos. O design foi
produzido sem considerar o documento.

**Decisão:** onde design e requisitos divergem, **o documento prevalece** e o design é
corrigido. Exceções são apenas os achados que revelaram lacuna real do documento, listados
abaixo como incorporados.

**Incorporados ao documento** (o design estava certo, faltava requisito):

| Achado | Onde entra |
|---|---|
| Telefone do cliente | atributo de Usuário — o entregador precisa |
| SKU da variante | atributo de Variante Produto — código legível no admin e nos relatórios |
| Galeria de imagens | várias imagens **por variante**, ordenadas (fecha MD-02) |
| Composição, cuidados, envio & devolução | campos de texto do Produto |
| Janela de reserva de 15 minutos | o design já exibia o cronômetro; fixa o prazo de D-01 |

**Corrigidos no design** (o documento estava certo):

| Achado | Correção |
|---|---|
| Checkout não pedia CPF | passa a exigir CPF — a regra de negócio sempre exigiu |
| Peça numerada ("nº 007 de 024") | remover a numeração por unidade; a menção ao acabamento manual pode ficar como texto |
| Retirada no atelier | remover — terceira modalidade de entrega sem requisito |
| Compra sem conta | remover a ambiguidade: conta é obrigatória (RF001 e a regra do CPF) |
| "10× sem juros" no checkout | alinhar em 6×, conforme D-03 (o PDP já exibia 6×) |
| Frete grátis e nº do drop | remover — já decidido em D-05 |
| "Troca grátis em 30 dias" | ajustar ao prazo que vier de RQ-10 (7 dias, CDC art. 49) |
| "PT / EN" | remover — internacionalização fora de escopo (D-07) |
| Newsletter | remover — captação de e-mail é marketing, sem requisito |

**Alternativas descartadas:**

- *Peça com numeração individual real.* Transformaria estoque de contador em item
  individual: cada unidade física viraria registro próprio, e reserva, baixa e devolução
  passariam a operar sobre unidades identificadas. É um modelo legítimo para artigos de
  luxo ou numerados, e desproporcional aqui — muda o núcleo do sistema para sustentar um
  texto de vitrine.
- *Retirada no atelier.* Um segundo caminho de entrega, sem CEP e sem frete, logo depois
  de simplificar deliberadamente o cálculo de frete (D-04).
- *Peso como campo de cálculo.* Permanece texto descritivo: D-04 dispensou peso.

---

## D-09 — Carrinho é persistente e independente da venda
**Pendência:** RQ-18 · **Data:** 2026-08-17

**Contexto:** o RF003 vinculava o item do carrinho "à tabela de venda", o que faz do
carrinho uma venda em estado inicial. Combinado com a expiração de D-01, isso destruiria
o carrinho junto com o checkout expirado — contrário à intenção de manter o carrinho
indefinidamente.

**Decisão:** o carrinho é entidade própria, pertence ao usuário e **não expira**. A venda
é criada **no início do checkout**, a partir de uma cópia dos itens do carrinho. Itens
saem do carrinho em apenas duas situações: **compra confirmada** ou **remoção pelo
cliente**. Expiração ou cancelamento do pedido não afetam o carrinho.

Como cada usuário tem um único carrinho, não há tabela de cabeçalho — apenas os itens,
com unicidade em (usuário, variante), o que também impede duplicar a mesma variante.

**Alternativas descartadas:**

- *Carrinho como venda em estado "carrinho", revertendo o status na expiração.* Não exige
  tabela nova, mas põe na tabela `venda` linhas que não são vendas: todo relatório passaria
  a depender de filtrar o status, cada carrinho abandonado consumiria um identificador de
  pedido (deixando lacunas na numeração), e uma "venda" que volta de estado contradiz a
  regra de histórico inalterável.

**Consequências:**

- O RF003 é reescrito: adicionar ao carrinho não cria venda.
- O carrinho exibe o preço vigente, sem congelamento — congelar é papel de
  `venda_item.subTotal`, no fechamento.
- A reserva de estoque continua ocorrendo apenas no checkout (D-01), nunca no carrinho.

---

## D-10 — Entrega em três fases; preço é único por produto
**Pendência:** RQ-15 · **Data:** 2026-08-17

**Contexto:** era preciso fixar a ordem de entrega, já que decisões anteriores (D-06,
hospedagem) pressupõem faseamento, e definir se o preço pode variar por variante ou por
promoção.

**Decisão:** o preço é **único por produto** — a variante não tem preço próprio e não há
preço promocional no MVP. A entrega segue três fases:

| Fase | Conteúdo | Condição de início |
|---|---|---|
| **1 — MVP** | Todos os requisitos com `GatewayFake` assíncrono; roda em ambiente local | — |
| **2 — Integração e implantação** | Provedor de pagamento real em sandbox, webhook, hospedagem com HTTPS, teste ao vivo com estoque fictício | MVP entregue |
| **3 — Fila de espera** | Preço promocional (coluna anulável + selo "-15%" na vitrine) | Fase 2 concluída; **pode ser riscado** se representar retrabalho significativo |

**Alternativas descartadas:**

- *Preço por variante.* Permitiria GG mais caro e promoção por cor, ao custo de mover o
  preço para a variante e replicá-lo em todas — sem nenhum requisito que o exija.
- *Preço promocional no MVP.* Uma coluna anulável e uma regra de exibição; barato, mas
  não sustenta nenhum requisito e o MVP não precisa vender com desconto.

**Consequências:**

- O selo "-15%" sai do design agora (DS-01) e só volta se a fase 3 acontecer.
- Fica registrado que a fase 3 é descartável: não constitui promessa de entrega.

---

## Fora de escopo

Recursos avaliados e deliberadamente não incluídos. Estar aqui é uma escolha defendida,
não um esquecimento — declarar o limite do escopo é o que impede a pergunta "e por que
não tem X?" de virar uma falha.

| Recurso | Motivo |
|---|---|
| Frete grátis acima de valor | Interage com cálculo de frete e com devolução parcial. Estava só no design, nunca foi requisito. (D-05) |
| Drop como entidade | Ver D-05. |
| Peso e dimensões de produto | Consequência de D-04: sem cálculo por transportadora, não têm uso. |
| Parcelamento com juros | Ver D-03. |
| Controle das parcelas do cliente | Quem parcela é o emissor do cartão. A loja recebe o valor integral do adquirente; se o cliente não honrar a fatura, é problema entre ele e o banco. (D-03) |
| Conciliação financeira dos repasses | Conferir se o dinheiro prometido pelo gateway entrou na conta bancária é tesouraria, não venda. O sistema conhece a aprovação da cobrança, não o extrato. |
| Avaliações e comentários de produto | Não afeta o ciclo de venda, e loja recém-aberta não tem avaliação nenhuma. (D-07) |
| Cupom de desconto | Exige tabela de cupons, regras de validade, acumulação e valor mínimo. Custo alto, sem contribuição acadêmica. (D-07) |
| Lista de desejos / favoritos | Conforto do usuário, sem efeito no ciclo de venda. (D-07) |
| Múltiplos endereços por cliente | Um endereço por cliente resolve. O que importa é congelar o endereço no pedido, que é outro problema. (D-07) |
| Rastreio automático via API dos Correios | Um campo de texto com o código de rastreio entrega quase todo o valor com uma fração do esforço. (D-07) |
| Recuperação de carrinho abandonado | Exige tarefa agendada e e-mail de marketing. Não confundir com carrinho persistente, que é requisito. (D-07, D-09) |
| Multi-vendedor / marketplace | A Wonner é uma marca única; multi-vendedor mudaria o modelo na raiz. (D-07) |
| Internacionalização (PT / EN) | Estava no design, nunca foi requisito. (D-07) |
| Emissão de NF-e | Exige certificado digital e integração fiscal. O CPF é coletado para identificação e responsabilização, não para emissão pelo sistema. (D-07) |
| Numeração individual das peças | Transformaria estoque de contador em item individual, alterando reserva, baixa e devolução. (D-08) |
| Retirada no atelier | Modalidade de entrega paralela, sem CEP e sem frete, sem requisito que a ampare. (D-08) |
| Compra sem conta (visitante) | Conta é obrigatória: RF001 e a regra do CPF pressupõem cadastro. (D-08) |
| Newsletter | Captação de e-mail é marketing, não venda. (D-08) |

_A lista mais ampla de cortes (avaliações, cupom, favoritos, múltiplos endereços,
rastreio automático, recuperação de carrinho, recomendação, multi-vendedor,
internacionalização) está em análise na pendência RQ-17._
