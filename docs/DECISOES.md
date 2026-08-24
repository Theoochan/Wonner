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
