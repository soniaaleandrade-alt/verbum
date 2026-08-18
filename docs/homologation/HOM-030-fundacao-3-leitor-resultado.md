# HOM-030 — Fundação 3 de 4: Leitor e Resultado

## Escopo

Implementa exclusivamente a terceira subetapa da Fundação da Obra. Carta e Alma e Intenção permanecem intactas; Verdade Central continua reservada, sem implementação funcional neste item.

## Experiência

- Público principal somente leitura, herdado da Identificação Inicial, com atalho para edição na origem.
- Campos essenciais: Necessidades do leitor, Transformação esperada e Diferencial da obra.
- Limites opcionais separados entre conteúdos incluídos e excluídos.
- Salvamento automático com debounce, salvamento manual, revisão otimista e cópia local de recuperação.
- Conclusão bloqueada quando a Intenção não foi concluída ou quando público e campos essenciais estão vazios.
- Assistência de coerência compara Identificação, Carta e Alma, Intenção e Leitor e Resultado. Sugestões só alteram um campo após confirmação explícita.

## Persistência

Não há migração SQL nem exclusão de dados. O mapeamento é:

| Campo | Metadado |
| --- | --- |
| Público principal | `_verbum_audience` |
| Necessidades do leitor | `_verbum_foundation_reader_needs` |
| Transformação esperada | `_verbum_work_project_transformation` |
| Diferencial da obra | `_verbum_work_project_differentials` |
| Esta obra abordará | `_verbum_foundation_scope_included` |
| Esta obra não abordará | `_verbum_foundation_scope_excluded` |

Valores legados de necessidade, benefícios, proposta de valor e limites são preservados separadamente e podem ser consultados na interface.

## Verificação

- `node --check frontend/app/src/foundation-reader-result-runtime.js`
- `node frontend/app/scripts/build.mjs`
- `node frontend/app/scripts/test.mjs`
- `git diff --check`

A validação PHP e a captura visual final devem ser executadas no ambiente de homologação, pois o contêiner local não inclui PHP nem navegador autenticado do WordPress.
