# HOM-025 — Estrutura da Obra

## Objetivo

Transformar a antiga etapa técnica `planning` em **Estrutura da Obra**, mantendo a rota e os metadados existentes para compatibilidade e preservando integralmente capítulos e conteúdos já criados.

## Organização da tela

A etapa apresenta quatro blocos funcionais:

1. **Fio Condutor** — reaproveita `general_structure` como “Fio condutor e movimento da obra” e `editorial_notes` como “Observações estruturais”. Diretrizes estruturais reúnem metodologia, forma narrativa, abordagem, palavras-chave e limites.
2. **Organização da Estrutura** — divide os itens em Elementos iniciais, Corpo da obra e Elementos finais, com tipo editorial, título, elemento pai, ordenação, duplicação explícita e retirada segura da estrutura.
3. **Estimativas da Obra** — mantém meta estimada de capítulos, meta de palavras e estimativa de páginas como opcionais; a quantidade real de capítulos vem da estrutura.
4. **Geração e Sincronização dos Capítulos** — exige pré-visualização antes de qualquer criação/vínculo e gera somente itens do tipo `chapter`.

Estratégia de Escrita e Cronograma Inicial continuam armazenados e ficam acessíveis em **Conteúdos anteriores preservados**.

## Tipos editoriais

- Parte
- Capítulo
- Subcapítulo
- Prefácio
- Apresentação
- Introdução
- Dedicatória
- Agradecimentos
- Epígrafe
- Carta ao leitor
- Prólogo
- Conclusão
- Epílogo
- Posfácio
- Apêndice
- Anexo
- Glossário
- Bibliografia ou Referências
- Outro elemento

## Reclassificação segura

Itens existentes são reclassificados automaticamente somente quando o título possui correspondência inequívoca, como “Prefácio”, “Apresentação”, “Introdução”, “Dedicatória” e “Carta ao leitor”.

O tipo anterior é preservado em `legacyType`. Reclassificações são registradas no meta `_verbum_planning_structure_reclassification_log`. Títulos ambíguos conservam o tipo anterior.

## Hierarquia

Não foi necessária coluna SQL nova. A estrutura já possuía `parentId` no array armazenado em `_verbum_planning_structure_items`.

Regras aplicadas:

- Parte pode conter Capítulos;
- Capítulo pode estar no primeiro nível ou pertencer a uma Parte;
- Subcapítulo deve pertencer a um Capítulo;
- elementos editoriais iniciais/finais não recebem pai;
- ciclos, autoparentes e pais de tipo incompatível são bloqueados.

Itens legados que já eram subcapítulos sem pai não são reatribuídos por suposição; aparecem como pendência informativa para revisão manual.

## Modelo de dados e reversibilidade

Não existe migração SQL nesta entrega. A evolução usa os mesmos metadados WordPress, ampliando cada item estrutural com campos compatíveis:

- `type`
- `legacyType`
- `parentId`
- `group`
- `linkedChapterId`
- `syncState`
- `order`

A reversibilidade é preservada porque os identificadores existentes continuam os mesmos e o tipo anterior é mantido em `legacyType` quando ocorre reclassificação segura. Nenhuma coluna/tabela antiga é apagada.

## Mapeamento dos campos antigos

| Planejamento antigo | Estrutura da Obra |
| --- | --- |
| Estrutura Geral | Fio condutor e movimento da obra |
| Observações Gerais | Observações estruturais |
| Metodologia | Diretrizes estruturais / Metodologia |
| Forma de Apresentação | Diretrizes estruturais / Forma narrativa |
| Abordagem | Diretrizes estruturais / Abordagem |
| Meta de capítulos | Meta estimada de capítulos |
| Meta de palavras | Meta de palavras |
| Meta de páginas | Estimativa de páginas |
| Estratégia de Escrita | Conteúdos anteriores preservados |
| Cronograma Inicial | Conteúdos anteriores preservados |

Pergunta central, tese principal e visão geral continuam preservadas nos metadados compartilhados, mas não são exibidas novamente na Estrutura, pois pertencem conceitualmente à Fundação.

## Proteção dos capítulos existentes

Capítulos permanecem posts do tipo `verbum_chapter`, associados à obra por `_verbum_book_id` e à Estrutura por `_verbum_planning_item_id`.

A sincronização segue esta prioridade:

1. identificador estrutural já vinculado ao capítulo;
2. capítulo ainda não vinculado com título normalizado exatamente igual e correspondência única;
3. criação de novo capítulo somente quando não existe vínculo ou correspondência segura.

Correspondências ambíguas são bloqueadas como conflito. Capítulos existentes sem correspondência permanecem intactos e não são excluídos.

A alteração de título estrutural não renomeia automaticamente o capítulo. A prévia oferece escolha explícita. Quando aceita, o título anterior é guardado em `_verbum_planning_title_history`. A ordem dos capítulos existentes só é sincronizada se o usuário marcar essa opção na confirmação.

Nenhuma sincronização altera preparação, pesquisa, redação, revisão, status, progresso, referências ou histórico interno dos capítulos.

## Pré-visualização da sincronização

Novo endpoint compatível:

`GET /books/{id}/planning-stage/chapter-sync-preview`

A prévia separa:

- capítulos a criar;
- capítulos existentes a vincular;
- títulos divergentes;
- capítulos já sincronizados e inalterados;
- itens editoriais que não geram capítulo;
- capítulos existentes sem correspondência;
- conflitos que exigem revisão manual.

O POST de geração/sincronização exige `confirmed: true` e é recusado se houver conflitos.

## Progresso

A Estrutura possui somente dois requisitos essenciais:

1. pelo menos um item estrutural válido;
2. pelo menos um item do tipo Capítulo.

Fio condutor, observações, estimativas, diretrizes, estratégia e cronograma não bloqueiam a etapa.

## Navegação

A rota técnica `planning` foi mantida para compatibilidade. Visualmente a etapa é **Estrutura**. O botão anterior volta para Fundação (`project`) e o botão principal **Continuar para Capítulos** usa a rota atual `development`.

## Histórico local da estrutura

Sem criar um novo sistema de auditoria, a infraestrutura de post meta registra:

- criação e retirada de item;
- alteração de título;
- alteração de tipo;
- mudança de pai;
- reordenação;
- reclassificação segura;
- vínculo e sincronização de capítulos.

Metas usadas: `_verbum_planning_structure_log`, `_verbum_planning_structure_reclassification_log` e `_verbum_planning_sync_log`.

## Limitações deliberadas

- Elementos editoriais como Prefácio e Apêndice ainda não recebem editor próprio nesta entrega.
- Capítulos existentes sem vínculo e sem correspondência exata não são associados automaticamente.
- Subcapítulos permanecem elementos da Estrutura e não criam posts independentes de capítulo.
- A migração de Estratégia de Escrita para um futuro módulo de metas e do Cronograma para o Calendário Editorial não é executada nesta etapa.
