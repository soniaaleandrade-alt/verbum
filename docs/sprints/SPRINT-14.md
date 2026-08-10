# Sprint 14 — Controle de Versões

**Versão:** 2.0.0
**Etapa da obra:** 6 de 11
**Fluxo:** Revisão Geral ✓ → Controle de Versões → Auditoria

## Objetivo

Criar uma camada formal de versionamento da obra para preservar marcos editoriais completos, visualizar versões históricas, comparar alterações, restaurar com segurança e selecionar a versão que seguirá para Auditoria.

## Entregas

- criação automática de `v1.0 — Final da Revisão Geral`, protegida e baseada no snapshot final da etapa anterior;
- criação manual de versões com nome, tipo, notas, proteção e incremento principal ou secundário;
- tipos de versão: Marco Editorial, Backup Manual, Antes/Depois de Alteração, Revisão, Auditoria, Diagramação, Publicação e outros;
- timeline editorial com busca e filtros;
- visualização histórica somente leitura com sumário e leitura contínua;
- comparação entre versões por estrutura, capítulos, renomeação, ordem, palavras, front matter e parágrafos adicionados/removidos;
- indicador de alterações não versionadas desde o último marco;
- hash SHA-256 dos snapshots para detectar alterações e validar integridade;
- restauração segura que cria automaticamente um backup protegido antes de modificar a obra atual;
- duplicação de versão histórica sem destruir a versão atual;
- proteção e exclusão segura de snapshots;
- seleção explícita de uma `Versão para Auditoria`, automaticamente protegida;
- checklist do Controle de Versões;
- conclusão da etapa liberando Auditoria;
- endpoints REST próprios, isolamento por conta e runtime de produção.

## Snapshot imutável

Cada versão formal preserva uma cópia dos dados essenciais no momento da criação:

- título e subtítulo;
- estrutura planejada;
- Prefácio, Apresentação, Nota do Autor, Introdução e Conclusão gerais;
- capítulos, títulos, ordem, conteúdo, vínculo estrutural e contagem de palavras;
- hash do conjunto;
- número, tipo, notas, autor e data da versão.

Alterações feitas depois na obra não modificam versões históricas já registradas.

## Numeração

Versões secundárias seguem `v1.0 → v1.1 → v1.2`. Ao marcar **Nova versão principal**, a sequência avança para `v2.0`, e assim sucessivamente.

## Restauração

Antes de qualquer restauração o sistema cria automaticamente uma versão protegida do estado atual chamada **Backup antes da restauração**. A restauração não apaga o histórico. Como o estado da obra muda, a seleção para Auditoria é invalidada e o Controle de Versões precisa ser validado novamente.

## Regra de conclusão

O Controle de Versões só pode ser concluído quando:

1. existe a versão inicial originada da Revisão Geral;
2. não existem erros de integridade nos snapshots;
3. existe ao menos uma versão protegida;
4. uma comparação entre versões foi realizada;
5. o checklist manual foi confirmado;
6. uma versão do estado atual foi marcada como **Versão para Auditoria**.

Ao concluir, a etapa `versions` é marcada como concluída e a obra avança para `audit`.

## Fora deste Sprint

Branches editoriais, merge de versões, Git real para texto, revisão colaborativa, aprovação externa, Auditoria, Mesa Editorial, Diagramação, Trâmites Legais e Publicação.
