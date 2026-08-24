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

## Aguardando (execução)

### DG-01 🟡 Substituir as três imagens de diagrama
As especificações estão escritas no `TCC.md` (2.1, 2.3.1 e 2.4.1) e conferem com as
30 decisões. Falta reproduzi-las nas ferramentas e trocar os PNG em `docs/diagramas/`:

- **2.1 Diagrama geral** — árvore de módulos (6 cadastros, 4 movimentações, 5 relatórios)
- **2.3 Diagrama de classes** — 11 classes, operações próprias e multiplicidades (Astah)
- **2.4 Modelo relacional** — 11 relações, chaves e restrições (MySQL Workbench)

O diagrama Mermaid em 2.4.1 serve para conferir o modelo antes de redesenhar.

### DG-02 ⚪ Diagrama de casos de uso ausente
A seção 2.2 tem apenas o parágrafo teórico, e a 1.4.1 afirma que cada requisito "inclui
o caso de uso associado" — nenhum RF cita. Com os 16 RF definidos, é montável.
**Fazer:** desenhar o diagrama e associar cada RF ao seu caso de uso.

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
