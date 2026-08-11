# HOM-009 — Checklist da Pesquisa sobrepondo conteúdo

**Versão alvo:** 2.5.3
**Etapa:** Desenvolvimento > Pesquisa do Capítulo
**Classificação:** Hotfix visual/funcional de UX

## Encontrado
Na tela de Pesquisa do capítulo, o painel lateral de progresso/checklist ocupava espaço sobre a área principal em larguras intermediárias do workspace. Alguns controles da Central de Pesquisa ficavam parcialmente cobertos, impedindo o clique.

## Correção
- garante `min-width: 0` na coluna principal e nos cards da Pesquisa para evitar overflow de conteúdo;
- reduz a largura lateral do painel em telas amplas;
- em workspaces menores, o painel deixa de ser sticky e passa para uma área própria abaixo do conteúdo principal;
- organiza checklist e categorias em grade responsiva;
- em telas menores, a estrutura volta para uma única coluna;
- preserva todos os campos, filtros, fontes, ideias e ações existentes.

## Critérios de reteste
1. Abrir Pesquisa de um capítulo em desktop.
2. Confirmar que nenhum item da Central de Pesquisa fica coberto pelo checklist.
3. Clicar em filtros, categorias e `+ Adicionar fonte` sem interferência do painel de progresso.
4. Confirmar que o checklist continua legível e acessível.
5. Reduzir a largura da janela e confirmar que o painel passa para uma área própria, sem sobreposição.
