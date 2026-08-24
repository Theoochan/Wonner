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

## Aguardando (modelo de dados)

### MD-07 🟡 Redesenhar os diagramas 2.1, 2.3 e 2.4
Os diagramas de classes e relacional ainda não contêm:
`pagamento` (D-02) · reserva de estoque com expiração de 15 min (D-01, D-08) ·
faixas de frete (D-04) · `carrinho_item` (D-09) · imagens por variante (D-08) ·
`telefone` em usuário e `sku` em variante (D-08) · campos descritivos do produto (D-08) ·
`categoria` e `modelagem` (D-21) · endereço de entrega na venda (D-22) ·
`entrada_estoque` (D-23) · remoção de `marca` (D-24).
E o diagrama geral (2.1) precisa refletir os cadastros e movimentações novos e os cinco
relatórios definidos em D-17.
**Fazer:** redesenhar 2.1, 2.3 e 2.4.

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
- trocar "Troca grátis em 30 dias" por 7 dias, conforme o CDC (D-18)
- remover a ambiguidade "já tenho conta": conta é obrigatória (D-08)
- manter o cronômetro "reservadas por 14:32 min", fixando a janela em 15 min (D-01, D-08)

Os arquivos vivem em `docs/design/`, versionados junto com as decisões.
