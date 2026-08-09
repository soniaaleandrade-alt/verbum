# Sprint 04 — Workspace da Obra e Workflow Editorial

## Objetivo

Criar o ambiente interno de cada obra, preservando a sidebar global e adicionando contexto permanente da obra, indicadores, workflow horizontal e navegação persistente por URL.

## Workflow oficial da obra

1. Identificação
2. Projeto da Obra
3. Planejamento
4. Desenvolvimento
5. Revisão Geral
6. Controle de Versões
7. Auditoria
8. Mesa Editorial
9. Diagramação
10. Trâmites Legais
11. Publicação

## Implementado neste Sprint

- ação **Abrir obra** no Banco de Obras;
- endpoint REST privado do workspace da obra;
- cabeçalho com capa, título, subtítulo, etapa atual e indicadores;
- IMO e RME preparados sem dados inventados;
- workflow horizontal com estados `completed`, `in_progress` e `locked`;
- bloqueio de etapas futuras;
- consulta de etapas anteriores liberadas;
- breadcrumb da obra;
- infraestrutura de estado de salvamento e proteção contra alterações não salvas;
- rodapé com Etapa anterior, Salvar e Salvar e continuar;
- URLs persistentes por `verbum_work` e `verbum_stage` para sobreviver à atualização da página;
- sidebar global alinhada ao fluxo de referência do Verbum Studio;
- estados de loading, erro e obra não encontrada;
- responsividade desktop/tablet/mobile;
- runtime estático de produção atualizado;
- CI ampliada com TypeScript e validação de sintaxe do runtime.

## Fora do escopo

O conteúdo funcional de cada etapa permanece para os Sprints específicos. Este Sprint implementa o container, o mecanismo de workflow e a navegação.

## Próximo Sprint

Sprint 05 — Identificação da Obra.
