# HOM-014 — Hierarquia de navegação da Obra e do Capítulo

**Versão:** 2.5.9
**Área:** Obra → Desenvolvimento → Capítulo
**Tipo:** UX / navegação / hierarquia visual

## Objetivo

Reduzir o excesso de navegação empilhada no topo quando a pessoa estiver trabalhando dentro de um capítulo, preservando o contexto da obra sem competir com o fluxo do capítulo.

## Implementação

- cabeçalho da obra assume formato compacto dentro do capítulo;
- o retorno passa a indicar **Minhas Obras**;
- capa, título e métricas essenciais ficam em uma única composição compacta;
- o workflow geral da obra deixa de ocupar uma faixa permanente;
- é exibido o indicador **Etapa da obra — Desenvolvimento — 4 de 11**;
- o botão **Ver etapas da obra** abre um painel sob demanda com o workflow geral;
- o workflow do capítulo **Preparação → Pesquisa → Redação → Revisão** mantém prioridade visual;
- o cabeçalho do capítulo recebe espaçamento e tipografia mais compactos;
- o cabeçalho grande da obra deixa de permanecer sticky dentro do capítulo;
- o comportamento é aplicado às telas internas de Preparação, Pesquisa, Redação e Revisão.

## Refinamento conjunto do HOM-013

O pacote também refina a Revisão:

- coluna esquerda mais larga;
- painel direito ligeiramente mais compacto;
- manuscrito preservado como área dominante;
- resumo das modalidades com aparência de progresso, não de segundo menu principal;
- **Revisão geral do capítulo** renomeada para **Progresso desta Revisão**;
- itens automáticos deixam de aparentar checkboxes clicáveis;
- singular e plural de pendências são normalizados.

## Critério de aceite

1. o topo ocupa menos espaço dentro de um capítulo;
2. o workflow geral da obra não compete visualmente com o workflow do capítulo;
3. todas as etapas da obra continuam acessíveis pelo botão de expansão;
4. Preparação, Pesquisa, Redação e Revisão permanecem visíveis como fluxo principal;
5. o manuscrito continua sendo a principal área visual na Revisão;
6. nenhuma persistência ou regra funcional previamente homologada sofre regressão;
7. a apresentação permanece correta após F5.

## Reteste

1. abrir um capítulo em Revisão;
2. confirmar o cabeçalho compacto;
3. abrir e fechar **Ver etapas da obra**;
4. alternar Conteúdo, Estrutural, Clareza e Estilo, Linguística e Validação Final;
5. conferir larguras, progressos e itens automáticos;
6. pressionar F5 e confirmar que a estrutura visual e os dados permanecem corretos.
