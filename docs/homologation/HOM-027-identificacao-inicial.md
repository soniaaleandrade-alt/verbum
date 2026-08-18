# HOM-027 — Identificação Inicial

## Objetivo

Reformular exclusivamente a etapa de Identificação da obra conforme a nova referência visual, preservando a rota técnica `identification`, os registros existentes e as páginas posteriores.

## Jornada visual oficial

1. Identificação — `identification`
2. Fundação — `project`
3. Estrutura — `planning`
4. Capítulos — `development`
5. Revisão Geral — `general_review`
6. Validação — `versions` + `audit`
7. Preparação Editorial — `editorial_desk` + `layout` + `legal`
8. Publicação — `publication`

A etapa Validação foi criada apenas como agrupamento visual/compatibilidade. Nenhuma página interna de Validação foi reformulada nesta entrega.

## Mapeamento e preservação dos dados

| Campo da Identificação Inicial | Armazenamento utilizado |
| --- | --- |
| Título provisório da obra | `post_title` da obra |
| Subtítulo provisório | `_verbum_subtitle` |
| Nome da autoria | `_verbum_author_name` |
| Tema central | `_verbum_work_project_theme` (mesmo dado já utilizado pela Fundação) |
| Gênero da obra | `_verbum_genre` |
| Abordagem | `_verbum_planning_approach` (mesmo dado já utilizado pela Estrutura) |
| Público principal | `_verbum_audience` |
| Linguagem e tom | `_verbum_language_tone` |
| Palavras-chave | `_verbum_keywords` |
| Formato pretendido | `_verbum_intended_format` |
| Extensão estimada | `_verbum_estimated_extent` |
| Status da obra | `_verbum_workflow_status` |
| Capa provisória | `_verbum_cover_id` + `_verbum_cover_url` existentes |
| Posição horizontal da capa | `_verbum_cover_position_x` |
| Posição vertical da capa | `_verbum_cover_position_y` |

Não houve criação de tabela, alteração de identificador da obra ou migração SQL. Os novos metadados são aditivos e reversíveis. Campos antigos não foram apagados.

## Persistência

Foi criado o endpoint complementar `identification-initial` para permitir `Salvar rascunho` mesmo com campos obrigatórios incompletos sem regredir automaticamente uma obra já avançada. O endpoint `complete` valida os dez campos obrigatórios antes de delegar a transição para a Fundação.

A interface continua compatível com as URLs legadas da Identificação por meio de uma ponte de runtime restrita à etapa `identification`.

## Capa provisória

O upload continua utilizando o mecanismo WordPress já existente e os formatos JPG, JPEG, PNG e WebP. A nova interface adiciona estado vazio/preenchido, troca, remoção confirmada e ajuste de enquadramento horizontal/vertical. O upload não é repetido nos salvamentos textuais.

## Segurança de navegação

- não há autosave nesta tela;
- `Salvar rascunho` não avança;
- `Salvar e continuar` valida, salva e avança para `project`;
- alterações não salvas acionam proteção de saída;
- erros não limpam o formulário;
- a antiga barra de métricas/capa e o workflow horizontal são ocultados somente durante a Identificação Inicial;
- ao sair da Identificação, o shell original volta a ser utilizado pelas demais páginas.

## Responsividade e acessibilidade

A implementação inclui sidebar de 244 px no desktop, menu lateral acionável em telas menores, grids adaptativos, rodapé de ações responsivo, foco visível, `aria-invalid`, mensagens próximas aos campos, textos alternativos da capa e respeito a `prefers-reduced-motion`.

## Testes automatizados incluídos

- sintaxe dos novos runtimes JavaScript;
- presença dos três cards e textos principais;
- presença dos dois botões de ação;
- jornada visual com oito etapas e Validação separada;
- loader do prelude antes do runtime de Identificação;
- build estático comprometido;
- sintaxe PHP do novo controller;
- testes existentes de TypeScript, PHP e frontend mantidos.

## Limites da homologação

O ambiente de GitHub/CI desta entrega não executa um navegador conectado ao site de produção. A comparação visual final em 1536 × 1024 e a captura da tela devem ser feitas após merge/deploy, usando a página real do Verbum Studio. Nenhuma página posterior foi reformulada nesta entrega.
