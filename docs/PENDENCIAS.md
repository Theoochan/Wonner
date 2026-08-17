# Pendências

Arquivo de trabalho. **O objetivo é sempre zerar.**

Cada pendência sai daqui de uma de duas formas:

1. **Decidida** → vira uma entrada em [DECISOES.md](DECISOES.md) e é apagada daqui.
2. **Descartada** → vira uma linha na seção "Fora de escopo" de [DECISOES.md](DECISOES.md) e é apagada daqui.

Nada é resolvido "silenciosamente": se saiu daqui, está registrado lá. É o registro
de decisões que sustenta o capítulo de justificativas do TCC.

Prefixos: `RQ` requisitos e regras de negócio · `MD` modelo de dados ·
`TP` tipos e DDL · `DV` divergência entre artefatos do documento ·
`DS` correção nos arquivos de design.

Prioridade: 🔴 bloqueia · 🟡 importante · ⚪ pode esperar

---

## Em discussão agora: requisitos e regras de negócio

### RQ-01 🔴 Não existe requisito para a página de detalhe do produto
O RF007 cobre o catálogo (cards) e o RF005 a busca, mas nenhum RF descreve a tela
que apresenta um produto: fotos, descrição, seleção de tamanho/cor, disponibilidade
e botão de compra. A tela existe no design (`PDP.dc.html`), não no documento.
**Decidir:** criar RF novo (essencial).

### RQ-02 🔴 Não existe requisito para consultar os próprios pedidos
A RN de "histórico inalterável" pressupõe que o cliente vê o histórico, mas nenhum
RF descreve essa tela nem o acompanhamento do status pós-pagamento.
**Decidir:** criar RF novo (essencial).

### RQ-03 🔴 Não existe requisito para o painel administrativo
Toda a seção 1.4.3 (CADASTRAR/ALTERAR/EXCLUIR/PESQUISAR) e os quatro relatórios
pressupõem um admin, e a RN de níveis de acesso cita "Admin". Nenhum RF descreve
o painel. É aproximadamente metade do sistema, sem requisito que o ampare.
**Decidir:** criar RF novo (essencial).

### RQ-07 🟡 `usuario.status` acumula dois conceitos
O campo é usado tanto para papel (Admin/Comprador) quanto para situação
(ativo/inativo/bloqueado). São dois eixos independentes: um Admin pode estar inativo.
**Decidir:** separar em `papel` e `situacao`.

### RQ-08 🟡 "Histórico inalterável" não diz nada sobre o admin
A RN proíbe alteração "pelo cliente". Se o admin pode editar pedido finalizado, o
histórico não é inalterável e a premissa de integridade (NF002) cai.
**Decidir:** o que o admin pode alterar em pedido finalizado, e se fica registro.

### RQ-09 🟡 LGPD conflita com histórico inalterável
A LGPD (art. 18) dá ao titular o direito de eliminação dos dados; a RN de histórico
inalterável exige preservar o pedido. As duas não podem valer ao mesmo tempo na
forma escrita. Além disso, o consentimento por checkbox não é registrado em lugar
nenhum do modelo — sem data/hora/versão dos termos, não há como provar que houve.
**Decidir:** anonimização em vez de exclusão + campos de registro do consentimento.

### RQ-10 🟡 Falta o direito de arrependimento (CDC art. 49)
Compra online dá ao consumidor 7 dias para desistir. É obrigação legal, do mesmo
naipe da LGPD que já está no documento. Não precisa de fluxo automatizado, mas
precisa existir como regra e como estado do pedido.
**Decidir:** RN nova + estado de cancelamento/devolução.

### RQ-11 🟡 "O recebimento do produto é garantido pela Wonner" não é verificável
Regra de negócio precisa ser testável. "Garantido" não diz prazo, nem o que
acontece se não chegar.
**Decidir:** reescrever como prazo + política de reenvio/reembolso, ou remover.

### RQ-13 ⚪ Recuperação de senha não está no RF001
O RF001 cobre cadastro e autenticação, não "esqueci minha senha". Sem isso, cliente
que esquece a senha está permanentemente fora (a senha está com hash).
**Decidir:** incluir no RF001. O starter kit do Laravel já entrega — custo ~zero.

### RQ-14 ⚪ Nenhum requisito de confirmação do pedido por e-mail
Pedido sem comprovante gera desconfiança — e credibilidade é o desafio declarado
na seção 1.2 do próprio documento.
**Decidir:** RF (importante) ou fora de escopo.

### RQ-16 ⚪ Três listas diferentes de relatórios
Seção 1.4.3-C: Vendas por Cliente, Produtos Vendidos, Estoque, Pagamentos.
Diagrama geral: Estoque por Produto, Vendas por produto, Vendas por Tamanho.
Seção 3.2: Produtos Vendidos, Vendas por Cliente, Estoque, Pagamentos.
A seção 4.4 pede o SQL de cada um — precisa de **uma** lista fechada.
**Decidir:** a lista final.

---

## Aguardando (modelo de dados)

### MD-01 🔴 Categoria não existe como entidade
RF007 exige categorias "pré-definidas"; o que existe é `produto.modelo VARCHAR(45)`,
texto livre. Texto livre não é pré-definido e não sustenta o filtro do RF005.

### MD-03 🔴 A venda não guarda o endereço de entrega
O endereço mora só em `usuario`. Cliente muda de casa e todos os pedidos passados
passam a apontar para o endereço novo. É o mesmo problema que `subTotal` já resolveu
para preço, e que continua aberto para entrega.

### MD-05 🟡 Entrada de estoque não existe no modelo
Listada como movimentação na 1.4.3-B, ausente nos dois diagramas. Sem ela,
`qtdEstoque` é a única verdade e não há histórico de reposição.

### MD-06 🟡 Multiplicidade `Marca 1 ── 1..* produto`
`1..*` obriga toda marca a ter ao menos um produto, impedindo cadastrar a marca
antes do primeiro produto. Deveria ser `0..*`.
(Já `produto 1 ── 1..* variante` faz sentido: produto sem variante não é vendável.)

### MD-07 🟡 Modelar as entidades criadas pelas decisões D-01 a D-09
Os diagramas de classes e relacional ainda não contêm:
`pagamento` (D-02) · reserva de estoque com expiração de 15 min (D-01, D-08) ·
faixas de frete (D-04) · `carrinho_item` (D-09) · imagens por variante (D-08) ·
`telefone` em usuário e `sku` em variante (D-08) · campos descritivos do produto (D-08).
**Fazer:** redesenhar 2.3 e 2.4 com essas entidades.

---

## Aguardando (tipos e DDL — seção 4.2)

### TP-01 🔴 Dinheiro em `DOUBLE`
`produto.valor`, `venda_item.subTotal`, `venda.valorFrete`. `DOUBLE` é IEEE 754
binário: `0,1` não tem representação exata e somas de centavos acumulam erro.
→ `DECIMAL(10,2)`.

### TP-02 🔴 `tamanho CHAR` cabe 1 caractere
`CHAR` sem tamanho é `CHAR(1)`. Cabe "P", "M", "G"; **não cabe "GG"**, "XG" nem "38".
→ `VARCHAR(5)` ou tabela de tamanhos.

### TP-03 🔴 Nomes de tabela/coluna com espaço
`variante Produto`, `idvariante Produto`, `venda_has_variante Produto`.
Exigem backtick em toda query e quebram as convenções do Eloquent.
→ `variante_produto`, `venda_item`.

### TP-05 🟡 `dataVenda DATE` perde a hora
Loja precisa saber que vendeu às 22h47, e relatório por horário fica impossível.
→ `DATETIME`.

### TP-06 🟡 `cpf` e `email` sem UNIQUE
CPF é chave de negócio (RN exige CPF para comprar) e e-mail é o login.
Sem UNIQUE, entram duplicados.

### TP-07 ⚪ Sem timestamps de auditoria
Nenhuma tabela tem `created_at`/`updated_at`. A premissa de histórico íntegro
(NF002) fica sem base, e é convenção do Eloquent.

---

## Aguardando (divergências entre os artefatos do documento)

### DV-01 🟡 Texto e diagramas discordam sobre o item de venda
A seção 1.4.3-B coloca `codVarianteProd` dentro de Venda; os dois diagramas usam
corretamente a associativa. O texto precisa ser corrigido para refletir os diagramas.

### DV-02 🟡 Tipos divergem entre diagrama de classes e relacional
| Atributo | Classes | Relacional |
|---|---|---|
| `subTotal` | `int` | `DOUBLE` |
| `dataVenda` | `char` | `DATE` |

(`formaRecebimento` e `qtdeParcelas` saíram de `venda` por D-02 e D-03 —
a divergência deixou de existir.)

### DV-03 ⚪ Atributos presentes em um artefato e ausentes no outro
`complemento` e `numeroResidencia` existem só no relacional; `dataNasc` não está
na lista da 1.4.3-A; `valorTotal` está na 1.4.3-B e em nenhum dos diagramas.

---

## Aguardando (correções no design)

### DS-01 🟡 Aplicar nas telas as correções decorrentes de D-05, D-07 e D-08
Os arquivos `.dc.html` ainda prometem recursos cortados. Correções por arquivo:

**Todas as telas**
- remover o seletor "PT / EN" (D-07)
- remover "Envio grátis · pedidos +R$ 349" da barra de anúncio (D-05)
- remover a contagem regressiva e o "Drop 03 / № 003" (D-05)
- remover o bloco de newsletter do rodapé (D-08)

**Homepage**
- remover o selo "-15%" do card de produto — preço promocional foi adiado (D-10)

**PDP**
- remover "Favoritar ♡" (D-07)
- remover "Nº 007/024" e "a sua é a nº 007 de 024"; manter a menção ao acabamento
  manual como texto (D-08)
- manter a seção "Combine no time", renomeada para produtos da mesma categoria (D-07)

**Checkout**
- remover o campo de cupom e a linha "Desconto (WELCOME10)" (D-07)
- remover "Mover p/ favoritos" (D-07)
- remover "Retirar no atelier" das modalidades de entrega (D-08)
- remover "Faltam R$ 21 para frete grátis" e o "PAC — grátis" (D-05)
- adicionar campo de CPF (D-08)
- trocar "10× de R$ 122,01" por 6× (D-03, D-08)
- ajustar "Troca grátis em 30 dias" ao prazo de RQ-10
- remover a ambiguidade "já tenho conta": conta é obrigatória (D-08)
- manter o cronômetro "reservadas por 14:32 min", fixando a janela em 15 min (D-01, D-08)

Os arquivos vivem em `docs/design/`, versionados junto com as decisões.
