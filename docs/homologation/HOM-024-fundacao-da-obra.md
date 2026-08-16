# HOM-024 — Fundação da Obra

## Objetivo
Transformar a etapa legada `project` em **Fundação da Obra**, consolidando decisões fundamentais que estavam distribuídas entre Projeto da Obra, Planejamento e Identificação, sem remover dados ou rotas existentes.

## Compatibilidade
- A rota técnica continua sendo `verbum_stage=project`.
- O endpoint continua sendo `/books/{id}/project-stage`.
- A barra oficial exibe **Fundação**.
- O botão **Continuar para Estrutura** aponta temporariamente para `planning`.
- A página Planejamento permanece disponível e mantém estrutura, organização editorial, metas e geração de capítulos.
- Nenhuma tabela, coluna, post type, capítulo, item estrutural ou rota foi excluído.

## Mapeamento de dados
| Novo campo | Fonte preservada |
|---|---|
| Tema central | novo post meta `_verbum_work_project_theme` |
| Propósito da obra | `_verbum_work_project_purpose` |
| Pergunta essencial | `_verbum_planning_central_question` |
| Tese principal | `_verbum_planning_main_thesis` |
| Síntese da Fundação | `_verbum_planning_overview` |
| Objetivo geral | `_verbum_work_project_general_objective` |
| Objetivos específicos | `_verbum_work_project_specific_objectives` |
| Público-alvo principal | `_verbum_audience` |
| Descrição do público | `_verbum_work_project_audience` |
| Público secundário | novo post meta `_verbum_work_project_secondary_audience` |
| Necessidade do leitor | `_verbum_reader_problem` |
| Transformação esperada | `_verbum_work_project_transformation` |
| Benefícios anteriores | `_verbum_work_project_benefits`, preservado como conteúdo auxiliar |
| Diferencial da obra | `_verbum_work_project_differentials` |
| Proposta de valor anterior | `_verbum_work_project_value_proposition`, preservada como conteúdo auxiliar |
| Metodologia | `_verbum_planning_methodology` |
| Forma narrativa | `_verbum_planning_presentation_form` |
| Abordagem | `_verbum_planning_approach` |
| Palavras-chave | `_verbum_keywords`, com leitura compatível de `_verbum_keyword` e `_verbum_work_project_keyword` |
| Limites da obra | novo post meta `_verbum_work_project_limits` |
| Mensagem central anterior | `_verbum_work_project_central_message`, preservada para classificação manual |
| Motivação pessoal | `_verbum_work_project_motivation` |
| Versículo inspirador | `_verbum_work_project_verse` |
| Frase norteadora | `_verbum_work_project_guiding_phrase` |

## Fonte única durante a transição
Pergunta essencial, Tese principal, Síntese da Fundação, Metodologia, Forma narrativa e Abordagem usam diretamente os mesmos post metas da página Planejamento. Assim, a Fundação e o Planejamento não mantêm cópias divergentes desses campos.

## Progresso
Somente seis elementos determinam a prontidão:
1. Tema central
2. Propósito da obra
3. Pergunta essencial
4. Tese principal
5. Objetivo geral
6. Público-alvo principal

Os campos recomendados e opcionais são contabilizados separadamente e não bloqueiam o avanço.

## Consolidação segura
Benefícios Esperados e Proposta de Valor permanecem armazenados e aparecem como conteúdo anterior para revisão. O usuário pode incorporar manualmente o texto no campo principal e marcar o conteúdo como revisado. Mensagem Central permanece intacta e pode ser copiada manualmente para Pergunta essencial ou Tese principal.

## Banco de dados
Não foi criada migração SQL. O WordPress armazena os novos campos opcionais em `post_meta`, preservando a arquitetura atual e evitando migração destrutiva.

## Dependência futura
A etapa `planning` ainda concentra Estrutura Geral, índice, partes, capítulos, observações editoriais, estratégia de escrita, cronograma, metas e geração de capítulos. Esses dados só serão reorganizados quando a nova **Estrutura da Obra** for implementada em tarefa própria.
