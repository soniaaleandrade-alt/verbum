# HOM-036 — Capítulos e ciclo editorial

## Entrega

A Central de Capítulos passa a consumir o Índice Provisório concluído e mantém o ciclo Preparação, Pesquisa, Redação e Revisão já persistido por capítulo.

## Sincronização segura

- a comparação é obrigatória antes da aplicação;
- capítulos vinculados são reconhecidos pelo identificador estável do Índice Provisório;
- novos itens criam capítulos vazios;
- capítulos fora do índice são preservados, nunca removidos;
- títulos existentes só mudam após seleção explícita;
- texto, pesquisa, comentários, versões e progresso não são sobrescritos;
- a ordem só é atualizada dentro da mesma obra;
- cada execução gera histórico com criações, renomeações, reordenações e itens preservados.

## Rotas

- `GET /books/{id}/development-stage`
- `GET /books/{id}/development-stage/structure-preview`
- `POST /books/{id}/development-stage/structure-sync`
- `PATCH /books/{id}/development-stage/order`
- rotas existentes de preparação, pesquisa, redação e revisão permanecem compatíveis.

## Validações

O servidor confirma propriedade da obra, rejeita vínculos inválidos, exige confirmação da comparação e impede que a ordenação inclua capítulos de outra obra.
