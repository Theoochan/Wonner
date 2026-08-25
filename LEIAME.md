# Wonner

Plataforma de e-commerce. Projeto Integrador — IFPR Umuarama.
Igor M. Delmonaco · Felipe T. Rodrigues

PHP sem framework, MySQL, Tailwind CSS. Estrutura MVC simples.

O Tailwind entra pelo CDN, que compila no navegador — nada para instalar.
Na entrega E9 isso vira um CSS gerado pelo Tailwind CLI (um executável
avulso, sem Node), e as classes do HTML não mudam.

---

## Como rodar

**1. Criar o arquivo de configuração**

```bash
cp incluir/config-exemplo.php incluir/config.php
```

Abra o `incluir/config.php` e ajuste usuário e senha do MySQL.

**2. Criar o banco**

```bash
mysql -u root -p < docs/ddl.sql
```

**3. Subir o site**

```bash
php -S localhost:8000 -t publico server.php
```

Abra <http://localhost:8000>.

Não precisa de Apache em desenvolvimento — o `php -S` já atende. O
Apache entra só na hospedagem, e é quando o `publico/.htaccess`
passa a valer.

---

## Estrutura

```
publico/          a única pasta que o servidor web expõe
├─ index.php        recebe toda requisição e decide a página
├─ .htaccess        manda tudo para o index.php (Apache)
├─ paginas/         uma por tela
└─ uploads/         imagens de produto

incluir/          código compartilhado, fora do alcance de URL
├─ config.php       senha do banco e constantes · não versionado
├─ conexao.php      função conexao(), devolve o PDO
├─ funcoes.php      escapar(), dinheiro(), redirecionar(), ...
├─ modelos.php      carrega os modelos
├─ modelos/         um por tabela, com todo o SQL
├─ topo.php         começo do HTML
├─ rodape.php       fim do HTML
├─ protegido.php    exige login
└─ protegido-admin.php

server.php        roteador do servidor embutido (desenvolvimento)
docs/             documento do TCC, decisões, DDL, diagramas
```

## Onde fica o que

| | Responsabilidade |
|---|---|
| `publico/index.php` | ligar endereço a arquivo, verificar acesso |
| `publico/paginas/*` | ler o formulário, chamar **uma** operação, montar o HTML |
| `incluir/modelos/*` | todo o SQL e **todas** as regras de negócio |
| `incluir/funcoes.php` | o que serve a várias páginas e não é de nenhuma tabela |

Nenhum SQL na página. Nenhum HTML no modelo.

**Toda** saída de dado passa por `escapar()`. **Toda** consulta com valor
usa `prepare()` e `execute()`, nunca concatenação.

## Documentação

| Arquivo | Conteúdo |
|---|---|
| [docs/TCC.md](docs/TCC.md) | o documento, versão corrente |
| [docs/DECISOES.md](docs/DECISOES.md) | 31 decisões, com as alternativas descartadas |
| [docs/PENDENCIAS.md](docs/PENDENCIAS.md) | o que falta decidir |
| [docs/ENTREGAS.md](docs/ENTREGAS.md) | as onze entregas até o MVP |
| [docs/ddl.sql](docs/ddl.sql) | criação do banco |
| [docs/diagramas/modelo-dados.html](docs/diagramas/modelo-dados.html) | referência visual do modelo |
