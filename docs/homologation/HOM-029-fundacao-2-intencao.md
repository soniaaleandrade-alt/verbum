# HOM-029 — Fundação 2 de 4: Intenção

## Escopo

Implementa exclusivamente a segunda tela interna da Fundação da Obra, preservando a Fundação 1 e mantendo Fundação 3 e 4 como subetapas reservadas.

## Interface

- Carta e Alma aparece concluída e Intenção ativa.
- Card de orientação com o objetivo da subetapa.
- Grade com Problema ou necessidade, Propósito da obra, Objetivo geral e Objetivos específicos.
- Objetivos podem ser criados, editados, ordenados por arrastar ou por controles de teclado e removidos mediante confirmação.
- Assistência de coerência compara Carta, Alma e Intenção sem alterar texto automaticamente.
- Faixa de salvamento real e barra inferior com Voltar, Salvar rascunho e Salvar e avançar.
- Layout responsivo para computador, tablet e celular.

## Persistência e compatibilidade

Não há migração SQL nem remoção de dados. A tela reutiliza os metadados existentes:

- Problema ou necessidade: `_verbum_reader_problem`;
- Propósito da obra: `_verbum_work_project_purpose`;
- Objetivo geral: `_verbum_work_project_general_objective`;
- Objetivos específicos: `_verbum_work_project_specific_objectives`.

Os objetivos mantêm identificador e ordem. Itens vazios, compostos somente por pontuação e duplicados não são persistidos. Metadados aditivos controlam somente revisão, atualização e conclusão da subetapa.

## Inteligência artificial

A Assistência de coerência reutiliza `VERBUM_OPENAI_API_KEY` no servidor. A resposta separa pontos coerentes, pontos de atenção e sugestões. Cada aplicação exige visualização do texto atual, texto sugerido e confirmação explícita. A ausência da chave não bloqueia salvamento nem avanço.

## Validação

- `node --check frontend/app/src/foundation-intention-runtime.js`;
- `node --check frontend/app/src/project-stage-runtime.js`;
- `node frontend/app/scripts/test.mjs`;
- `git diff --check`.

O ambiente local de execução não possui PHP nem navegador autenticado. A validação PHP, a captura em 1536 × 1024 e a comparação final com a referência devem ser feitas no ambiente de homologação após a publicação da branch.

## Critérios de homologação

1. Abrir uma obra com Carta e Alma concluída e acessar `verbum_foundation=2`.
2. Confirmar o carregamento dos valores antigos nos quatro campos.
3. Salvar rascunho parcial, recarregar e conferir persistência.
4. Adicionar, editar, ordenar e remover objetivos, cancelando e confirmando a remoção.
5. Confirmar que Salvar e avançar bloqueia campos essenciais ausentes.
6. Confirmar que a Assistência de coerência não aplica mudanças sem confirmação.
7. Voltar para Fundação 1 e avançar para a subetapa reservada Fundação 3.
8. Validar desktop 1536 × 1024 e celular, sem rolagem horizontal nem erros no console.
