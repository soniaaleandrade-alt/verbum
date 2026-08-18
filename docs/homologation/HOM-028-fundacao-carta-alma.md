# HOM-028 — Fundação 1 de 4: Carta e Alma

## Escopo

Esta entrega substitui visualmente a rota técnica `project` pela primeira subetapa da nova Fundação da Obra, sem concluir nem reformular as subetapas 2, 3 e 4.

Subetapas reservadas:

1. Carta e Alma — implementada;
2. Intenção — reservada;
3. Leitor e Resultado — reservada;
4. Verdade Central — reservada.

## Persistência

Não há migração SQL. O WordPress `post_meta` já utilizado pelo Verbum recebe apenas metadados aditivos:

- `_verbum_foundation_letter_html` — Carta aos Leitores com formatação sanitizada;
- `_verbum_foundation_soul` — Alma da Obra;
- `_verbum_foundation_letter_soul_revision` — revisão otimista para evitar sobrescrita concorrente;
- `_verbum_foundation_letter_soul_updated_at` — confirmação temporal do servidor;
- `_verbum_foundation_letter_soul_history` — até 20 snapshots anteriores;
- `_verbum_foundation_substeps` — conclusão das subetapas internas;
- `_verbum_foundation_letter_soul_completed_at` — conclusão da subetapa 1.

## Dados antigos preservados

Nenhum texto legado é transformado ou copiado automaticamente. A API somente expõe como referências preservadas:

- Motivação pessoal → `_verbum_work_project_motivation`;
- Mensagem central anterior → `_verbum_work_project_central_message`;
- Propósito → `_verbum_work_project_purpose`;
- Tema central → `_verbum_work_project_theme`.

Carta aos Leitores e Alma da Obra recebem campos próprios por não haver equivalência semântica segura nos dados anteriores.

## Salvamento

- autosave com debounce;
- `Salvar rascunho` aceita campos vazios;
- rascunho local temporário via `localStorage` permanece em falhas;
- revisão otimista impede resposta antiga ou outra sessão de sobrescrever versão mais recente;
- saída da página com alterações pendentes tenta salvar e avisa em caso de falha;
- `Salvar e avançar` exige Carta aos Leitores e Alma da Obra e marca somente `Carta e Alma` como concluída;
- a etapa técnica `project` não é concluída nesta entrega.

## Inteligência artificial

A Fundação reutiliza a mesma configuração segura já usada pelo Assistente de Escrita (`openai_api_key` / `VERBUM_OPENAI_API_KEY`).

Endpoints:

- `POST /books/{id}/foundation/carta-alma/analyze`;
- `POST /books/{id}/foundation/carta-alma/suggest`.

A análise considera exclusivamente a Carta aos Leitores e não altera o texto. A sugestão para Alma da Obra sempre é apresentada antes de qualquer aplicação. Quando a Alma já possui conteúdo, a interface exige escolha explícita entre inserir abaixo, substituir, copiar ou cancelar.

Se a chave não estiver configurada, a interface permanece funcional e informa a indisponibilidade da IA sem bloquear o salvamento.

## Compatibilidade

- rota externa permanece `verbum_stage=project`;
- Fundação 2 usa somente a reserva `verbum_foundation=2`, sem conteúdo definitivo;
- Fundação antiga, Estrutura, Capítulos e demais etapas não têm seus dados removidos;
- a jornada oficial continua com 8 etapas e Fundação selecionada na sidebar desta tela.

## Homologação visual

Após merge/deploy, validar em aproximadamente 1536×1024 e depois em tablet/celular:

- sidebar de 244 px;
- cabeçalho `Etapa 2 de 8`;
- navegação interna 1 de 4;
- orientação lilás;
- Carta aos Leitores à esquerda e Alma da Obra à direita;
- editor rico e contagem de palavras;
- faixa `Fundação 1 de 4 / Rascunho salvo`;
- barra inferior com Voltar, Salvar rascunho e Salvar e avançar;
- modal de análise/sugestão da IA;
- ausência de rolagem horizontal.
