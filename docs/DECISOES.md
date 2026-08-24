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

Máquina de estados da venda (atualizada por D-09 e D-12):

```
aguardando_pagamento → pago → separado → enviado → entregue
          │                                 │
          ├─ expirado                       └─ devolvido
          └─ cancelado
```

A venda nasce em `aguardando_pagamento`, no início do checkout — não existe estado
`carrinho`, pois o carrinho é entidade separada (D-09). As transições a partir de `pago`
são ações do administrador (D-12).

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

- Sem integração com a transportadora, a venda tem um campo de código de rastreio
  preenchido manualmente pelo administrador. A automação não foi cortada: foi **adiada
  para a fase 3** (D-10), e enquanto não existir, todo o acompanhamento de entrega é
  manual.
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
- **Não há chave estrangeira entre venda e carrinho.** O item de venda é cópia do item de
  carrinho, não referência a ele: uma chave apontaria para um registro que deixa de existir
  no instante em que a compra se confirma, e manteria o pedido atrelado a um carrinho que
  segue sendo alterado. A ligação entre os dois é de processo, executada no checkout, e não
  aparece no modelo relacional — o que precisa estar dito, por ser a primeira coisa que se
  procura ao ler o diagrama.
- **`carrinho_item` e `venda_item` são tabelas distintas**, ainda que de forma semelhante.
  Unificá-las numa só, com um discriminador, exigiria duas chaves estrangeiras anuláveis e
  mutuamente exclusivas — que o banco não consegue garantir —, uma coluna de subtotal
  obrigatória para metade das linhas e proibida para a outra, restrições de unicidade
  diferentes por tipo de linha, e um filtro em toda consulta de venda, sob pena de somar
  carrinhos ao faturamento. Sobretudo, impediria expressar a diferença que motiva as duas
  entidades: o carrinho acompanha o preço vigente do catálogo, o item de venda tem o preço
  congelado. Forma repetida não é duplicação — duplicação é guardar o mesmo fato duas
  vezes, e aqui os fatos são "este usuário quer isto" e "este pedido vendeu isto por este
  preço".

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
| **3 — Fila de espera** | Ver lista abaixo | Fase 2 concluída |

**Fase 3 — "se der tempo".** Nenhum item constitui promessa de entrega; cada um **pode ser
riscado** se representar retrabalho significativo:

- Preço promocional — coluna anulável em produto + selo "-15%" na vitrine.
- Rastreio automático de entrega — consulta do código junto à transportadora e avanço
  automático do pedido para `entregue`, substituindo a marcação manual do administrador
  (D-12).
- Recuperação de senha pelo próprio usuário — link de uso único por e-mail, substituindo
  a redefinição manual pelo administrador (D-14).
- Notificações de pedido por e-mail — confirmação do pagamento e envio com o código de
  rastreio (D-14).
- Autosserviço de cancelamento e devolução — o cliente solicita pela própria conta, com
  verificação de elegibilidade por prazo e situação, e estorno automatizado junto ao
  provedor (D-18).

**Alternativas descartadas:**

- *Preço por variante.* Permitiria GG mais caro e promoção por cor, ao custo de mover o
  preço para a variante e replicá-lo em todas — sem nenhum requisito que o exija.
- *Preço promocional no MVP.* Uma coluna anulável e uma regra de exibição; barato, mas
  não sustenta nenhum requisito e o MVP não precisa vender com desconto.

**Consequências:**

- O selo "-15%" sai do design agora (DS-01) e só volta se a fase 3 acontecer.
- Fica registrado que a fase 3 é descartável: não constitui promessa de entrega.

---

## D-11 — Recorte da página de produto e da consulta de pedidos
**Pendência:** RQ-01, RQ-02 · **Data:** 2026-08-17

**Contexto:** o catálogo (RF007) e a busca (RF005) levavam a uma página de produto que
nenhum requisito descrevia, e a regra de histórico inalterável pressupunha uma tela de
pedidos que também não existia como requisito.

**Decisão:**

- **Página do produto:** apresenta galeria de imagens da variante, descrição, composição,
  cuidados, política de envio e devolução, seleção de cor e tamanho, preço, quantidade
  disponível e produtos da mesma categoria. Variantes **sem estoque aparecem riscadas** —
  visíveis e não selecionáveis.
- **Meus pedidos:** o cliente vê a **lista dos seus pedidos com o respectivo estado**, sem
  tela de detalhe. O código de rastreio é exibido junto ao estado quando existir, pois é a
  informação que o cliente busca depois do envio.

**Alternativas descartadas:**

- *Esconder tamanhos sem estoque.* O cliente não descobre que a peça existe no tamanho
  dele, e a loja perde o sinal de demanda. Riscar informa sem frustrar a navegação.
- *"Avise-me quando chegar".* Exigiria entidade de notificação de reposição e disparo
  automático na entrada de estoque.
- *Tela de detalhe do pedido.* Com a lista trazendo estado e rastreio, o detalhe repetiria
  o que o cliente já viu no checkout.

**Consequências:**

- A disponibilidade por variante precisa ser calculada na página (estoque menos reservas).
- O código de rastreio precisa existir na venda (ver D-12).

---

## D-12 — Painel administrativo: cadastros completos e expedição por estados
**Pendência:** RQ-03, RQ-08 · **Data:** 2026-08-17

**Contexto:** a seção 1.4.3 e os relatórios pressupunham um administrador sem requisito
que o amparasse, e a regra de histórico inalterável proibia alteração "pelo cliente" sem
dizer nada sobre o administrador.

**Decisão:**

- O administrador **gerencia todos os cadastros**: marca, categoria, produto, variante,
  imagens, faixas de frete e usuários.
- A expedição é um **fluxo de estados**, não uma edição de pedido:
  `pago` → `separado` → `enviado` → `entregue`. O pedido entra em `pago` automaticamente
  na confirmação; as demais transições são ações do administrador, e a passagem para
  `enviado` exige o código de rastreio.
- **Resposta ao RQ-08:** em pedido finalizado, o administrador pode **avançar o estado e
  registrar dados logísticos** (código de rastreio, datas). Não pode alterar itens,
  quantidades, preços, frete ou valores. O histórico comercial é imutável para todos; o
  histórico logístico é acrescentado, nunca reescrito.

**Alternativas descartadas:**

- *Um único estado "enviado" após o pagamento.* Elimina a fila de trabalho: o
  administrador não distingue o que ainda precisa ser embalado do que já está pronto
  aguardando a transportadora.
- *Marcar a entrega automaticamente por consulta ao rastreio.* Depende da API descartada
  em D-07; sem ela, a entrega é registrada manualmente.
- *Permitir ao administrador editar valores do pedido.* Anularia a regra de histórico
  inalterável, que é premissa do requisito NF002.

**Consequências:**

- `venda` ganha `codigoRastreio` e `dataEnvio`.
- Se na operação real a separação e a postagem ocorrerem no mesmo momento, o estado
  `separado` pode ser eliminado sem afetar o restante.
- Não há log de auditoria das ações do administrador — apenas as datas das transições.

---

## D-13 — E-mails transacionais: confirmação, envio e recuperação de senha
**Pendência:** RQ-13, RQ-14 · **Data:** 2026-08-17
> ⚠️ **Revogada por D-14:** o MVP não envia e-mail algum. Os três envios previstos aqui
> foram adiados para a fase 3.

**Contexto:** o RF001 cobria cadastro e autenticação mas não a redefinição de senha — sem
ela, o cliente que esquece a senha fica permanentemente sem acesso, já que a senha é
armazenada com hash. E o pedido não gerava nenhum comprovante, num negócio cujo desafio
declarado (seção 1.2) é justamente conquistar credibilidade.

**Decisão:** o sistema envia e-mail em três eventos: **pedido confirmado** (na confirmação
do pagamento), **pedido enviado** (com o código de rastreio) e **redefinição de senha**
(link de uso único com prazo de validade).

**Alternativas descartadas:**

- *Notificar em todas as transições.* E-mail de "separado" não informa nada útil ao
  cliente e treina o destinatário a ignorar as mensagens da loja.
- *Nenhuma notificação.* Deixaria o cliente sem comprovante da compra e sem o código de
  rastreio, que é a informação mais buscada no pós-venda.

**Consequências:**

- O envio deve ocorrer fora do ciclo da requisição, para que uma falha de e-mail não
  derrube a confirmação do pagamento.
- O e-mail de envio depende do administrador ter informado o rastreio (D-12).

---

## D-14 — Nenhum envio de e-mail no MVP
**Pendência:** RQ-13, RQ-14 · **Data:** 2026-08-17 · **Revoga:** D-13

**Contexto:** D-13 previa três e-mails — pedido confirmado, pedido enviado e recuperação
de senha. Revisto o escopo, as notificações de pedido foram consideradas dispensáveis; e
como a recuperação de senha depende do mesmo mecanismo de envio, manter apenas ela não
eliminaria a dependência de um serviço de e-mail.

**Decisão:** o MVP **não envia e-mail algum**. Os RF014 e RF015 saem do documento e ambos
os recursos vão para a **fase 3** (D-10):

- notificações de pedido (confirmação e envio);
- recuperação de senha pelo próprio usuário.

**Alternativas descartadas:**

- *Manter apenas a recuperação de senha.* Ela é mecanismo de autenticação, não
  notificação, e por isso foi mantida na primeira versão desta decisão. Ainda assim
  exigiria configurar entrega de e-mail no ambiente hospedado — a mesma dependência que
  se queria evitar — para atender a um caso de uso pouco frequente.

**Consequências:**

- **O cliente que esquecer a senha não tem como recuperá-la sozinho no MVP.** A senha é
  armazenada com hash e não é recuperável. Isso precisa de um caminho de contorno pelo
  administrador (ver pendência RQ-19), no mesmo padrão adotado para a expedição em D-12:
  manual na fase 1, automatizado na fase 3.
- O cliente não recebe comprovante da compra; a confirmação existe na tela após o
  pagamento e na consulta de pedidos (RF011).
- O código de rastreio chega ao cliente **somente** pela tela de pedidos (RF011) — que já
  o exibe por decisão de D-11, portanto não há lacuna de informação.
- O requisito de envio assíncrono deixa de existir. Nenhum provedor de e-mail é
  necessário, nem na fase 1 nem na fase 2.

---

## D-15 — A fase 3 são os requisitos "Desejáveis"; o MVP aceita a perda de conta
**Pendência:** RQ-19 · **Data:** 2026-08-17

**Contexto:** D-14 tirou todo envio de e-mail do MVP, o que deixa sem recuperação o
usuário que esquece a senha — ela é armazenada com hash e não é reversível. Era preciso
decidir o contorno, e ficou evidente que a lista da "fase 3" precisava de lugar no
documento acadêmico.

**Decisão:**

1. **O MVP aceita a perda de conta.** Não há redefinição de senha pelo administrador nem
   outro contorno. Quem perde a senha perde o acesso, e isso consta como limitação
   declarada no próprio RF014.
2. **Os recursos da fase 3 são expressos no documento como requisitos de prioridade
   "Desejável"**, não como ausências. A seção 1.4 já define desejável como "requisito que
   não compromete as funcionalidades básicas [...] e pode ser implementado em versões
   posteriores" — exatamente o significado da fase 3. RF014 e RF015 voltam ao documento
   com essa prioridade.
3. Dentro da fase 3, **a recuperação de senha é o item de maior prioridade**.

**Alternativas descartadas:**

- *Administrador redefine a senha com senha temporária trocada no primeiro acesso.* É a
  solução tecnicamente correta — o administrador nunca conhece a senha definitiva — e
  custa um campo booleano e uma verificação no login. Descartada porque acrescenta
  requisito ao MVP para atender a um caso de uso que, no cenário de demonstração, tem
  contas controladas pela própria equipe.
- *Administrador define a senha diretamente.* Faria o administrador conhecer a senha de um
  cliente, contrariando o acesso "seguro e privado" prometido pelo RF001.
- *Manter RF014 e RF015 fora do documento.* Um documento de requisitos que omite
  requisitos conhecidos é menos completo que um que os classifica como desejáveis. A
  classificação também é mais honesta: o recurso foi reconhecido, priorizado e postergado.

**Consequências:**

- O documento tem 15 requisitos funcionais, dos quais 3 são desejáveis (RF006, RF014,
  RF015) e portanto fora do MVP.
- A limitação de perda de conta está escrita no RF014, não escondida.
- Novos itens da fase 3 entram no documento como requisitos desejáveis, não como
  anotações de projeto.

---

## D-16 — `status` é desmembrado em `papel` e `situacao`, e todo estado é tipado
**Pendência:** RQ-07 · **Data:** 2026-08-17

**Contexto:** `usuario.status VARCHAR(45)` era usado para duas coisas independentes: o
papel do usuário (Admin ou Comprador) e sua situação (ativo ou inativo). Um administrador
pode estar inativo, e um único campo não representa dois eixos. O mesmo tipo aberto
aparecia em `variante_produto.status` e `venda.status`, permitindo "Pago", "pago" e "PAGO"
como valores distintos.

**Decisão:**

- `usuario` passa a ter **dois** atributos: `papel` (`admin` | `comprador`) e `situacao`
  (`ativo` | `inativo`).
- Todo atributo de estado no sistema é **tipado com lista fechada de valores**, nunca texto
  livre, e passa a se chamar `situacao` por uniformidade:

| Entidade | Atributo | Valores |
|---|---|---|
| usuario | `papel` | `admin`, `comprador` |
| usuario | `situacao` | `ativo`, `inativo` |
| variante_produto | `situacao` | `ativo`, `inativo` |
| venda | `situacao` | `aguardando_pagamento`, `pago`, `separado`, `enviado`, `entregue`, `expirado`, `cancelado`, `devolvido` |
| pagamento | `situacao` | `iniciado`, `aguardando`, `aprovado`, `recusado`, `expirado`, `estornado` |

**Alternativas descartadas:**

- *Tabela de perfis com relacionamento N:N.* Permitiria papéis múltiplos e novos papéis sem
  alterar o esquema. Com exatamente dois papéis previstos e nenhum requisito de acumulação,
  acrescenta uma tabela e uma junção a cada verificação de acesso.
- *Terceiro valor `bloqueado` em `usuario.situacao`.* O efeito prático é idêntico a
  `inativo` — impedir o acesso — e nenhum requisito distingue "saiu por conta própria" de
  "foi impedido pelo administrador". Um estado só se justifica por comportamento próprio.

**Consequências:**

- A verificação de acesso passa a consultar `papel`, e a de acesso permitido, `situacao`.
- O anonimato exigido pela LGPD (pendência RQ-09) provavelmente precisará de um valor
  próprio ou de um marcador adicional; fica para aquela decisão.
- Em MySQL, listas fechadas podem ser `ENUM`; a alternativa é `VARCHAR` com restrição
  `CHECK`. A escolha entra na decisão de DDL.

---

## D-17 — Cinco relatórios, especificados
**Pendência:** RQ-16 · **Data:** 2026-08-17

**Contexto:** o documento trazia **três listas diferentes** de relatórios — uma na seção
1.4.3-C, outra no diagrama geral (2.1) e outra no sumário da seção 3.2 —, e a seção 4.4
exige a consulta SQL de cada um. Além disso, nomes como "Relatório de Vendas por Cliente"
não determinam o que a consulta agrupa nem o que exibe.

**Decisão:** a lista definitiva tem cinco relatórios, cada um com objetivo, agrupamento e
colunas declarados:

| Relatório | Agrupa por | Exibe | Serve para |
|---|---|---|---|
| **Vendas por Cliente** | usuário | nº de pedidos, valor total, ticket médio, data do último pedido | identificar quem mais compra |
| **Produtos Vendidos** | produto (detalhando variante) | quantidade vendida, receita | saber o que mais vende |
| **Vendas por Tamanho** | tamanho da variante | quantidade vendida, participação percentual | dimensionar a próxima produção |
| **Estoque de Produtos** | variante | quantidade em estoque, reservada e disponível | repor antes de esgotar |
| **Pagamentos Recebidos** | período, método e nº de parcelas | valor confirmado | acompanhar a entrada de caixa |

Duas regras valem para todos:

1. **Somente pedidos pagos entram nos relatórios de venda** — situações
   `aguardando_pagamento`, `expirado` e `cancelado` são excluídas. Carrinho não aparece,
   pois não é venda (D-09).
2. **Os valores vêm do congelamento, não do cadastro** — `venda_item.subTotal`, nunca
   `produto.valor` corrente. Um reajuste de preço não pode alterar o histórico.

**Alternativas descartadas:**

- *Manter apenas os quatro da seção 1.4.3-C.* Deixaria de fora "Vendas por Tamanho", que é
  o relatório mais acionável para uma loja de roupas: informa como distribuir a próxima
  produção entre P, M, G e GG. Custa um agrupamento e já constava do diagrama geral.
- *Adotar a lista do diagrama geral (três relatórios).* Perderia "Vendas por Cliente" e
  "Pagamentos Recebidos", ambos previstos no texto e com objetivo declarado.

**Consequências:**

- O diagrama geral (2.1) precisa ser redesenhado com os cinco (pendência MD-07).
- A seção 3.2 passa a ter cinco subseções, e a 4.4 cinco consultas.
- "Pagamentos Recebidos" só é possível por causa de `pagamento.dataConfirmacao` (D-02);
  antes dela, o relatório não tinha origem de dados.

---

## D-18 — Arrependimento e devolução: regra declarada, execução manual
**Pendência:** RQ-10 · **Data:** 2026-08-17

**Contexto:** o Código de Defesa do Consumidor (art. 49) dá ao consumidor sete dias para
desistir de compra feita fora do estabelecimento comercial. O documento citava a LGPD como
obrigação legal e omitia esta, embora sejam do mesmo naipe. Definido que fluxo
automatizado de cancelamento não é prioridade para o MVP, era preciso separar o que é
obrigação do que é funcionalidade.

**Decisão:**

1. **A regra é declarada** na seção 1.5: sete dias corridos a contar do recebimento. Ela
   vale independentemente de o sistema automatizá-la.
2. **A solicitação ocorre fora do sistema** (canal de atendimento) e é **registrada pelo
   administrador**, que altera a situação do pedido — mesmo padrão da expedição (D-12).
3. **O estorno é realizado junto ao provedor de pagamento**, por sua própria interface. O
   sistema registra a situação `estornado` do pagamento; não executa a devolução do valor.
4. **A reentrada do item devolvido ao estoque é decisão do administrador**, registrada como
   entrada de estoque — peça devolvida pode não estar em condição de revenda.
5. Transições permitidas:

| De | Para | Situação |
|---|---|---|
| `aguardando_pagamento` | `cancelado` | desistência antes do pagamento, ou fim do prazo de reserva (`expirado`) |
| `pago`, `separado` | `cancelado` | desistência antes do envio — exige estorno |
| `enviado`, `entregue` | `devolvido` | arrependimento ou devolução — exige estorno e decisão de reentrada em estoque |

**Alternativas descartadas:**

- *Autosserviço de cancelamento e estorno automático.* Avaliado caso a caso, o ganho não
  se sustenta: o caso trivial — pedido ainda não pago — já é resolvido pela expiração da
  reserva em 15 minutos (D-01), de modo que um botão economizaria minutos de espera a quem
  já desistiu de comprar; o caso intermediário — estorno antes do envio — depende do
  provedor real, que só existe na fase 2, e exige a mesma maquinaria assíncrona do
  pagamento para confirmar o estorno; e o caso mais frequente na prática — devolução após
  a entrega — tem a **inspeção física da peça** como gargalo, e nenhum software encurta
  esse ciclo. Adiado para a **fase 3** (D-10). Registrar a ocorrência manualmente custa
  dois botões na tela do RF013, que já existe.
- *Omitir a regra do documento por não haver funcionalidade correspondente.* A obrigação
  existe por lei; um documento de requisitos que a ignora descreve um sistema que a
  empresa não pode operar em conformidade. Declarar a política e executá-la manualmente é
  conformidade; não mencioná-la é omissão.
- *Estorno automático pelo sistema.* Movimentar dinheiro de volta por conta própria
  acrescenta risco desproporcional a um sistema que ainda não processa pagamento real.

**Consequências:**

- Nenhuma tela nova no MVP: as transições entram no RF013, junto com a expedição.
- Reforça a necessidade de registrar **entrada de estoque** (pendência MD-05), que agora
  tem dois motivos: reposição e devolução.
- A menção "Troca grátis em 30 dias" no design precisa ser ajustada aos sete dias legais
  (pendência DS-01).
- Cancelar pedido pago sem estorno correspondente é inconsistência a evitar; no MVP, os
  dois passos são manuais e a responsabilidade é do operador.

---

## D-19 — A garantia de recebimento sai das regras de negócio
**Pendência:** RQ-11 · **Data:** 2026-08-17

**Contexto:** a seção 1.5 trazia "o recebimento do produto é garantido pela Wonner". Regra
de negócio precisa ser verificável, e essa não diz prazo, nem o que ocorre se a entrega
falhar — não é implementável nem testável.

**Decisão:** a regra é **removida** da seção 1.5. O compromisso permanece como **política
comercial**, redigida no conteúdo do site (campo `envioDevolucao` do produto, D-08), e não
como requisito de sistema.

**Alternativas descartadas:**

- *Reescrever como prazo e política ("entrega em até X dias, senão reenvio ou reembolso").*
  Tornaria a regra verificável ao preço de criar obrigação nova: monitorar o prazo de cada
  pedido enviado, detectar atraso e disparar providência. Trabalho que nenhum requisito
  pedia.

**Consequências:**

- Nada deixa de funcionar: a regra não governava comportamento algum.
- A parte que era de fato verificável já existe: o prazo estimado por faixa de CEP
  (`faixa_frete.prazoDias`, RF009), exibido no checkout.
- Regras de negócio passam a conter apenas enunciados verificáveis; promessas comerciais
  ficam no conteúdo.

---

## D-20 — LGPD: consentimento registrado e anonimização a pedido legal
**Pendência:** RQ-09 · **Data:** 2026-08-17

**Contexto:** duas lacunas. A regra de LGPD exigia um checkbox de consentimento que não era
registrado em lugar nenhum do modelo — sem data, hora e versão dos termos, não há como
provar que o consentimento existiu, o que é justamente a obrigação da lei. E o direito de
eliminação (art. 18) contradiz frontalmente a regra de histórico inalterável: as duas não
podem valer ao mesmo tempo na forma em que estavam escritas.

**Decisão:**

1. **O consentimento é registrado**: `usuario` ganha `consentimentoEm` (data e hora) e
   `versaoTermos`, preenchidos no aceite. Duas colunas.
2. **Atendido pedido legal de eliminação, o cadastro é anonimizado, não excluído.** Os
   dados pessoais — nome, CPF, e-mail, telefone e endereço — são substituídos por valores
   sem identificação; os pedidos, valores e datas permanecem intactos.
   **Ressalva:** ficam retidos, pelo prazo legal aplicável, os dados cuja conservação seja
   exigida por obrigação legal ou necessária ao exercício de direitos em processo judicial,
   administrativo ou arbitral. A anonimização alcança o que estiver fora dessas hipóteses.
3. `usuario.situacao` ganha o valor **`anonimizado`**, distinto de `inativo`: uma conta
   anonimizada **não pode ser reativada**, pois os dados não são recuperáveis. O valor se
   justifica por ter comportamento próprio, critério fixado em D-16.
4. **A execução é manual**, pelo administrador, via cadastro de usuários (RF012) — mesmo
   padrão da expedição (D-12) e das devoluções (D-18).

**Alternativas descartadas:**

- *Excluir o registro do usuário.* A exclusão levaria consigo os pedidos, por integridade
  referencial, apagando registros de operações comerciais que a empresa precisa conservar.
  A anonimização atende ao direito do titular — o dado pessoal deixa de existir — sem
  destruir o registro fiscal.
- *Manter a regra de histórico inalterável sem ressalva.* Deixaria o sistema em
  descumprimento do art. 18 sempre que houvesse pedido de eliminação.
- *Anonimizar integralmente e de imediato, sem ressalva de retenção legal.* Foi a primeira
  redação desta decisão. O direito de eliminação, porém, não é absoluto: alcança
  primariamente dados tratados sob consentimento, e o art. 16 excetua o que precisa ser
  conservado por obrigação legal. Registros de operação comercial têm prazo de guarda
  próprio, e a defesa em processo judicial é base legal autônoma de tratamento. Anonimizar
  de imediato seria mais restritivo que a lei exige e privaria a empresa de identificar a
  contraparte de uma transação questionada judicialmente.
- *Rotina automatizada de anonimização, acionada pelo próprio titular.* Exigiria fluxo de
  verificação de identidade do solicitante — sem o qual qualquer um poderia destruir a
  conta de outro — para um evento raro.

**Consequências:**

- **Cuidado de implementação:** `email` e `cpf` são únicos (pendência TP-06). Os valores de
  substituição precisam ser únicos por registro, do tipo
  `anonimizado-{id}@invalido.local`, ou a anonimização do segundo usuário falha.
- **A anonimização alcança os pedidos.** Por D-22, o endereço de entrega e o nome do
  destinatário são copiados para cada venda, e são dados pessoais. A rotina precisa
  limpá-los em todas as vendas do titular, não apenas no cadastro do usuário.
- Os relatórios de Vendas por Cliente passam a poder exibir clientes anonimizados; os
  valores continuam corretos, apenas sem identificação.
- A ressalva de anonimização é acrescentada à regra de histórico inalterável, tornando
  explícita a convivência entre as duas obrigações.

---

## D-21 — Categoria e modelagem são dois eixos, não uma hierarquia
**Pendência:** MD-01 · **Data:** 2026-08-17

**Contexto:** o RF007 exige categorias "pré-definidas", e o que existia era
`produto.modelo VARCHAR(45)`, texto livre. Ao discutir, notou-se que "oversized" pareceria
naturalmente uma subcategoria de "Camisetas", o que sugeria hierarquia.

**Decisão:** duas dimensões independentes, ambas planas:

- **`categoria`** — entidade própria, relação **1:N** com produto (um produto pertence a
  uma categoria; uma categoria tem vários produtos). Sem hierarquia. Valores iniciais:
  Camisetas, Moletons, Acessórios.
- **`produto.modelagem`** — atributo do produto, com lista fechada de valores
  (`regular`, `oversized`, `cropped`, …), anulável para itens em que não se aplica
  (acessórios). É o antigo `modelo`, renomeado e tipado.

**Alternativas descartadas:**

- *Hierarquia de categorias (Camisetas → Oversized).* Produziria nós distintos com o mesmo
  significado — "Camisetas → Oversized" e "Moletons → Oversized" —, sintoma de ter
  modelado um atributo como nível hierárquico. Além disso exigiria tabela auto-relacionada
  e consulta recursiva. Com dois eixos independentes, "camisetas oversized" é a
  interseção de dois filtros, sem duplicação e sem recursão.
- *Eliminar `modelo`.* Foi a recomendação inicial, por parecer redundante com a categoria.
  A observação sobre oversized mostrou que descreve o corte da peça, ortogonal ao tipo, e
  portanto tem função própria.
- *Relação N:N entre produto e categoria.* A navegação da loja separa tipos de peça
  mutuamente exclusivos — um moletom não é uma camiseta —, o que dispensa a associativa.
- *`modelagem` em tabela de domínio própria.* Permitiria ao administrador acrescentar
  cortes sem alterar o esquema, ao custo de mais uma tabela e mais uma tela. Com lista
  fechada no esquema, acrescentar um valor exige migração — aceitável pela raridade.

**Consequências:**

- O RF005 (busca por "nome, cor, modelo, descrição") passa a buscar por categoria e
  modelagem.
- Os filtros do catálogo combinam os dois eixos livremente.
- `produto` ganha `categoria_id` e perde `modelo`, substituído por `modelagem`.

---

## D-22 — O endereço de entrega é copiado para o pedido
**Pendência:** MD-03 · **Data:** 2026-08-17

**Contexto:** o endereço existia apenas em `usuario`. Um cliente que muda de casa faria
todos os pedidos passados apontarem para o endereço novo — o mesmo problema que
`venda_item.subTotal` já resolvia para preço, e que seguia aberto para entrega.

**Decisão:** no fechamento do pedido, os dados de entrega são **copiados para `venda`**:
`destinatario`, `cep`, `endereco`, `numero`, `complemento`, `cidade` e `uf`. Alterações
posteriores no cadastro do usuário não afetam pedidos existentes.

O `destinatario` é registrado separadamente porque quem recebe pode não ser o titular da
conta.

**Alternativas descartadas:**

- *Tabela `endereco_entrega` referenciada pela venda.* Normalizaria um conjunto de valores
  que, por definição, nunca muda, e cobraria uma junção em toda consulta de pedido e na
  impressão de etiqueta.
- *Manter apenas a referência ao endereço do usuário.* É o defeito que a decisão corrige.

**Consequências:**

- **O endereço copiado é dado pessoal.** A anonimização prevista em D-20 precisa limpar
  também esses campos em cada venda do titular, não apenas o cadastro do usuário.
- Denormalização deliberada, com a mesma justificativa do `subTotal`: imutabilidade do
  registro histórico.

---

## D-23 — Entrada de estoque é um evento com quantidade, não uma linha por unidade
**Pendência:** MD-05 · **Data:** 2026-08-17

**Contexto:** "Entrada" constava como movimentação na seção 1.4.3-B e não existia em
nenhum diagrama. Sem ela, `qtdEstoque` é um número sem procedência, contrariando a
premissa de histórico íntegro do NF002. Havia dúvida sobre registrar uma linha por unidade
física ou uma linha por evento com quantidade.

**Decisão:** uma tabela `entrada_estoque` com **um registro por (variante, evento)**,
contendo a quantidade: `id`, `variante_id`, `qtde`, `motivo`
(`compra` | `devolucao` | `ajuste`), `observacao`, `created_at`.

`qtdEstoque` permanece como contador operacional, atualizado pelas entradas e pelas
baixas de venda, em vez de ser recalculado por soma a cada consulta.

A tela permite informar, em uma única submissão, a quantidade de **várias variantes do
mesmo produto** (P, M, G, GG), gerando um registro por variante com quantidade diferente
de zero.

**Alternativas descartadas:**

- *Uma linha por unidade física.* Só se justificaria se cada unidade tivesse identidade
  própria — número de série, custo individual —, o que D-08 descartou ao remover a
  numeração por peça. Seriam N linhas idênticas distintas apenas pelo identificador, e
  toda contagem passaria a `COUNT(*)` em vez de `SUM(qtde)`. É o mesmo critério já aceito
  em `venda_item`, onde três unidades vendidas são uma linha com quantidade três.
- *Cabeçalho e itens (`entrada` + `entrada_item`), espelhando venda.* Agruparia um
  recebimento físico inteiro como um único documento. Acrescenta uma tabela para um ganho
  que não é exigido por nenhum requisito, e atrapalha o caso de devolução, que é sempre de
  uma variante só.
- *Não registrar entradas, editando `qtdEstoque` diretamente.* Custo zero e histórico
  nenhum: impossível saber se um aumento de estoque foi compra, devolução ou correção de
  erro.

**Consequências:**

- Dois motivos sustentam a entidade: reposição de fornecedor e reentrada de devolução
  (D-18).
- `qtdEstoque` e a soma das movimentações podem divergir por falha de aplicação; a entrada
  é o registro de auditoria, o contador é o valor de trabalho.
- Novo requisito funcional para a tela de entrada.

---

## D-24 — A entidade Marca é removida
**Pendência:** MD-06 · **Data:** 2026-08-17

**Contexto:** o modelo trazia `marca` com relação `1 ── 1..*` para produto — multiplicidade
que, além de impedir cadastrar uma marca antes do primeiro produto, chamou atenção para a
entidade em si.

**Decisão:** `marca` é **removida** do modelo. A Wonner vende exclusivamente produtos
próprios, e D-07 descartou multi-vendedor; a tabela teria uma única linha permanentemente.

**Alternativas descartadas:**

- *Manter `marca` corrigindo a multiplicidade para `0..*`.* Preservaria a possibilidade de
  revender outras marcas no futuro, ao custo de uma tabela, uma chave estrangeira, uma
  tela de cadastro e uma junção nas consultas de produto — para um valor constante.

**Consequências:**

- `produto` perde `marca_idmarca`; a lista de cadastros da seção 1.4.3-A perde um item e o
  RF012 deixa de mencionar marca.
- Caso a revenda de terceiros entre em pauta, a entidade volta — junto com as demais
  mudanças que multi-marca exigiria.
- A multiplicidade `produto 1 ── 1..* variante` permanece: produto sem variante não é
  vendável.

---

## D-25 — Dinheiro em `DECIMAL(10,2)`
**Pendência:** TP-01 · **Data:** 2026-08-17

**Contexto:** `produto.valor`, `venda_item.subTotal` e `venda.valorFrete` estavam em
`DOUBLE`, e o diagrama de classes ainda trazia `subTotal` como `int`.

**Decisão:** todo valor monetário é `DECIMAL(10,2)`: `produto.valor`,
`venda_item.subtotal`, `venda.valor_frete`, `pagamento.valor` e `faixa_frete.valor`.
O limite de `DECIMAL(10,2)` — 99.999.999,99 — é folgado para o negócio.

**Alternativas descartadas:**

- *`DOUBLE`.* É ponto flutuante binário conforme IEEE 754: valores como 0,10 não têm
  representação exata, e somas de centavos acumulam erro. Um pedido pode fechar em
  1.299,9999998, e comparações de igualdade passam a falhar sem motivo aparente.
- *Inteiro em centavos.* Elimina o erro de arredondamento e é usado por sistemas
  financeiros, mas obriga a converter na entrada e na saída de todo valor, e torna
  ilegível qualquer consulta feita direto no banco.

**Consequências:**

- Fecha a divergência de tipo de `subTotal` entre os diagramas (pendência DV-02).
- Cálculos de parcela (`valor / qtdeParcelas`, D-03) exigem tratamento explícito de
  arredondamento na exibição — R$ 100,00 em 3× são 33,33 + 33,33 + 33,34.

---

## D-26 — Nomenclatura do banco: `snake_case` singular, `id` e `<entidade>_id`
**Pendência:** TP-03 · **Data:** 2026-08-17

**Contexto:** o modelo relacional trazia `variante Produto`, `idvariante Produto` e
`venda_has_variante Produto` — nomes com espaço, que exigem delimitador em toda consulta e
quebram a inferência automática do ORM.

**Decisão:**

- Tabelas em `snake_case`, **no singular**: `usuario`, `categoria`, `produto`,
  `variante_produto`, `imagem_variante`, `faixa_frete`, `carrinho_item`, `venda`,
  `venda_item`, `pagamento`, `entrada_estoque`.
- `venda_has_variante Produto` passa a chamar-se **`venda_item`**.
- Chave primária sempre `id`; chaves estrangeiras no formato `<entidade>_id`
  (`usuario_id`, `variante_produto_id`).

**Alternativas descartadas:**

- *Padrão do MySQL Workbench (`idusuario`, `usuario_idusuario`).* É o que estava no
  diagrama, mas obriga cada model do Eloquent a declarar a chave primária e cada
  relacionamento a informar explicitamente as chaves — configuração repetida sem ganho.
- *Tabelas no plural, convenção padrão do Eloquent.* Dispensaria declarar o nome da tabela
  nos models, mas afastaria o banco dos diagramas do documento, que estão no singular.
  Manter o singular custa uma linha por model — onze no total — e mantém banco e documento
  falando a mesma língua.

**Consequências:**

- Cada model declara `protected $table` com o nome singular.
- O DDL da seção 4.2 usa esses nomes; os diagramas 2.3 e 2.4 precisam ser refeitos com eles
  (pendência MD-07).

---

## D-27 — Tamanho em lista fechada
**Pendência:** TP-02 · **Data:** 2026-08-17

**Contexto:** `variante_produto.tamanho` estava declarado como `CHAR`, que sem comprimento
equivale a `CHAR(1)`: caberiam "P", "M" e "G", mas não "GG" nem "XG".

**Decisão:** lista fechada com os valores `PP`, `P`, `M`, `G`, `GG`, `XG` e `U` — este
último para peça de tamanho único, caso dos acessórios.

**Alternativas descartadas:**

- *`VARCHAR(5)` livre.* Resolveria o comprimento e deixaria o conteúdo à mercê da
  digitação: "G" e "g" seriam grupos distintos, e o relatório de Vendas por Tamanho (D-17),
  que agrupa exatamente por esse campo, apresentaria resultado errado. Mesmo critério
  fixado em D-16.
- *Tabela de domínio `tamanho`.* Permitiria acrescentar valores sem alterar o esquema, ao
  custo de mais uma tabela, mais uma tela e uma junção nas consultas de variante.

**Consequências:**

- Vender peça de numeração distinta — calça 38, 40 — exigiria migração. Não ocorre no
  catálogo atual, composto de moletons, camisetas e acessórios.
- O relatório de Vendas por Tamanho tem agrupamento estável e ordenável.

---

## D-28 — Datas, unicidade e auditoria
**Pendência:** TP-05, TP-06, TP-07, DV-02 · **Data:** 2026-08-17

**Contexto:** `dataVenda` era `DATE`, perdendo a hora da compra; nenhuma tabela tinha
registro de criação ou alteração, embora o NF002 prometesse histórico íntegro; e `cpf` e
`email` não tinham restrição de unicidade, apesar de serem, respectivamente, chave de
negócio e credencial de acesso.

**Decisão:**

1. **Toda tabela tem `created_at` e `updated_at`** (`DATETIME`).
2. **`dataVenda` é removida.** A data da compra é o `created_at` da venda. `data_envio`
   permanece como coluna própria, por ser outro evento.
3. **Restrições de unicidade:**

| Tabela | Coluna(s) | Por quê |
|---|---|---|
| usuario | `cpf` | chave de negócio — a regra exige CPF para comprar |
| usuario | `email` | credencial de acesso |
| variante_produto | `sku` | código operacional precisa identificar uma variante só |
| pagamento | `id_externo` | **garante a idempotência da confirmação** (D-02) |
| carrinho_item | (`usuario_id`, `variante_produto_id`) | impede duplicar a variante no carrinho (D-09) |
| venda_item | (`venda_id`, `variante_produto_id`) | impede a mesma variante duas vezes no pedido |

Nas duas últimas, a unicidade é uma **restrição**, não a chave primária: por D-26, toda
tabela tem `id` próprio, inclusive as associativas — o que também é coerente com a seção
1.4.3-B, que atribui `código` ao Item de Venda.

**Alternativas descartadas:**

- *Manter `dataVenda` ao lado de `created_at`.* Duas colunas com a mesma informação, que
  divergem no primeiro ajuste manual de dados.
- *Dispensar timestamps.* Sem registro de criação, não há como reconstituir quando um
  estado mudou, e a premissa de histórico íntegro do NF002 fica sem base.

**Consequências:**

- A unicidade de `pagamento.id_externo` é o mecanismo que impede a notificação reenviada do
  provedor de dar baixa em estoque duas vezes. Não é conveniência.
- A unicidade de `cpf` e `email` impõe cuidado à anonimização (D-20): os valores de
  substituição precisam ser distintos por registro.
- Fecha a divergência de tipo de `dataVenda` entre os diagramas (pendência DV-02).

---

## D-29 — Item de venda explícito e remoção de atributos redundantes
**Pendência:** DV-01, DV-03 · **Data:** 2026-08-17

**Contexto:** a seção 1.4.3-B colocava `codVarianteProd` dentro de Venda, contrariando os
próprios diagramas, que já traziam a associativa. Havia ainda atributos presentes em um
artefato e ausentes no outro.

**Decisão:**

1. **Item de Venda** passa a constar como movimentação própria: `código`, `codVenda`,
   `codVarianteProd`, `qtdeVendida`, `subTotal` (calculado). `venda` perde
   `codVarianteProd`.
2. **`venda.qntProduto` é removida.** É a soma das quantidades dos itens.
3. **`valorTotal` permanece calculado, não persistido** — é a soma dos subtotais mais o
   frete, ambos já congelados.
4. **`numero` e `complemento` entram** no cadastro de Usuário; existiam no modelo
   relacional e faltavam na lista de atributos.
5. **`dataNasc` é removida.**

**Alternativas descartadas:**

- *Manter `dataNasc`.* Nenhum requisito a utiliza. A LGPD adota o princípio da
  **necessidade** — o tratamento deve limitar-se ao mínimo indispensável à finalidade —,
  de modo que coletar data de nascimento sem uso declarado contraria a lei além de não
  servir a nada.
- *Persistir `valorTotal` na venda.* Seria um terceiro lugar guardando o que os subtotais e
  o frete já determinam, com risco de divergir deles.
- *Manter `qntProduto` como totalizador.* Mesmo defeito: um total que pode discordar das
  parcelas que o compõem.

**Consequências:**

- A seção 1.4.3-B passa a ter quatro movimentações: Venda, Item de Venda, Pagamento e
  Entrada de Estoque.
- O cadastro de usuário coleta menos dados pessoais, alinhado ao princípio da necessidade.

---

## D-30 — PHP sem framework, em lugar do Laravel
**Pendência:** — (decisão de projeto) · **Data:** 2026-08-17

**Contexto:** o repositório havia sido iniciado a partir do starter kit do Laravel
(Livewire, Flux, Fortify). Avaliou-se, porém, que o framework concentra abstrações cujo
funcionamento interno é desconhecido por quem desenvolve, o que produziria dependência de
assistência externa para diagnosticar qualquer comportamento inesperado — e, num trabalho
acadêmico, compromete tanto o aprendizado quanto a capacidade de sustentar o próprio código
em banca. Acrescente-se o prazo: aprender o framework e construir o sistema simultaneamente
não caberia no cronograma.

**Decisão:** o sistema é implementado em **PHP sem framework**, preservando integralmente a
arquitetura definida neste documento. Composer é utilizado apenas para carregamento
automático de classes (PSR-4), sem framework de aplicação. O acesso a dados é feito com PDO
e consultas preparadas.

**Alternativas descartadas:**

- *Laravel com o starter kit já configurado.* Entregaria autenticação, migrações, ORM,
  filas e agendador prontos. Nada disso, porém, é exigido pelo escopo em sua forma
  completa: não há e-mail no MVP (D-14), não se usa 2FA nem passkeys, e o DDL precisa ser
  escrito de todo modo por constar da seção 4.2. O ganho de tempo pressupõe domínio do
  framework, que não existe.
- *Micro-framework (roteador e injeção de dependência de terceiros).* Reduziria o
  encanamento a escrever, ao custo de reintroduzir comportamento não transparente
  exatamente na camada que se deseja compreender.

**Consequências:**

- Cinco proteções que o framework oferecia passam a ser responsabilidade explícita da
  aplicação: consultas preparadas contra injeção de SQL, `password_hash`/`password_verify`
  para senhas, escape de saída contra XSS, token anti-CSRF nos formulários e regeneração do
  identificador de sessão no login.
- Estrutura de diretórios, roteamento, camada de dados, validação e tratamento de erro
  passam a ser decisões explícitas do projeto, e não convenções herdadas.
- O DDL escrito à mão é o mesmo artefato exigido pela seção 4.2 do documento — código e
  documentação deixam de ser trabalhos separados.
- Nenhuma das decisões D-01 a D-29 é afetada: todas tratam de modelo de dados, regras de
  negócio e escopo, independentes de tecnologia.
- O requisito NF001 permanece válido: Tailwind CSS, DaisyUI e Alpine.js são tecnologias de
  interface, sem vínculo com o framework de aplicação.
- O código Blade produzido anteriormente (branch `design/homepage-1b`) permanece como
  referência de marcação e estilo, portável para template PHP sem alteração de CSS.

---

## D-31 — Um pedido pendente por cliente
**Pendência:** RQ-20 · **Data:** 2026-08-17

**Contexto:** como o carrinho permanece intacto durante o checkout (D-09), nada impedia o
cliente de iniciar um checkout, não pagar, e iniciar outro com os mesmos itens. Duas vendas
em `aguardando_pagamento` reservariam a mesma peça, travando o estoque em dobro até as duas
expirarem. Numa coleção de 24 peças, poucos clientes repetindo isso esgotariam o catálogo
sem nenhuma venda ter ocorrido.

**Decisão:** um cliente pode ter no máximo **um** pedido em `aguardando_pagamento`. Ao
iniciar um novo checkout, o pedido pendente anterior é levado a `cancelado` e sua reserva
liberada, na mesma transação em que o novo pedido é criado e reserva.

**Alternativas descartadas:**

- *Reaproveitar o pedido pendente, devolvendo o cliente ao checkout em andamento.* Nunca
  haveria duas reservas, mas o pedido pendente é uma cópia do carrinho de antes: se o
  cliente voltou justamente para trocar um tamanho, veria o pedido antigo, sem a alteração.
  Quebraria a regra de que o pedido é a cópia do carrinho **no momento do checkout**.
- *Permitir múltiplos pedidos pendentes.* É o defeito que a decisão corrige.

**Consequências:**

- A regra "o pedido é uma cópia do carrinho naquele momento" passa a valer sem exceção.
- O cancelamento e a criação precisam ocorrer na mesma transação, para que o estoque não
  fique momentaneamente reservado em dobro nem liberado indevidamente.
- Pedidos cancelados por esse motivo permanecem no histórico, como qualquer cancelamento.
- Convém um índice por `venda (usuario_id, situacao)`, para localizar o pendente do cliente
  ao iniciar o checkout.

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
| Recuperação de carrinho abandonado | Exige tarefa agendada e e-mail de marketing. Não confundir com carrinho persistente, que é requisito. (D-07, D-09) |
| Multi-vendedor / marketplace | A Wonner é uma marca única; multi-vendedor mudaria o modelo na raiz. (D-07) |
| Internacionalização (PT / EN) | Estava no design, nunca foi requisito. (D-07) |
| Emissão de NF-e | Exige certificado digital e integração fiscal. O CPF é coletado para identificação e responsabilização, não para emissão pelo sistema. (D-07) |
| Numeração individual das peças | Transformaria estoque de contador em item individual, alterando reserva, baixa e devolução. (D-08) |
| Retirada no atelier | Modalidade de entrega paralela, sem CEP e sem frete, sem requisito que a ampare. (D-08) |
| Compra sem conta (visitante) | Conta é obrigatória: RF001 e a regra do CPF pressupõem cadastro. (D-08) |
| Newsletter | Captação de e-mail é marketing, não venda. (D-08) |
| "Avise-me quando chegar" em variante esgotada | Exigiria entidade de notificação de reposição e disparo na entrada de estoque. (D-11) |
| Tela de detalhe do pedido para o cliente | A lista com estado e código de rastreio cobre a necessidade. (D-11) |
| Log de auditoria das ações do administrador | Apenas as datas das transições de estado são registradas. (D-12) |

_Não confundir com a **fase 3** (D-10, D-15): itens desta tabela não serão feitos; os da
fase 3 são requisitos de prioridade "Desejável", reconhecidos e postergados._
