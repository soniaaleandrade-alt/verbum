# HOM-012 — Persistência das Pendências da Revisão

**Versão:** 2.5.7
**Etapa:** Desenvolvimento → Revisão do Capítulo
**Prioridade:** Alta
**Tipo:** Reidratação / renderização no frontend

## Problema observado

Pendências registradas na Revisão apareciam corretamente na sessão atual, porém após salvar e recarregar a página o painel podia voltar sem as pendências, embora elas continuassem armazenadas.

## Diagnóstico confirmado

A investigação no DevTools confirmou o fluxo completo:

1. o `POST /revision/issues` retornou a pendência recém-criada com status `pending`;
2. o `PATCH /revision` executado pelo salvamento continuou retornando a mesma pendência;
3. após `F5`, o `GET /revision` com cache-busting também retornou a pendência corretamente.

Portanto, a gravação, a persistência no WordPress e a recuperação pelo backend estão corretas. O defeito estava na reidratação/renderização do painel de pendências no frontend após o carregamento da Revisão.

## Correção 2.5.7

- mantém a política `no-store / no-cache` da versão 2.5.6;
- captura o estado mais recente da Revisão devolvido pelas rotas REST;
- reconcilia o painel **Pendências da Revisão** com o array `issues` recebido da API após carregamentos e rerenders;
- atualiza o contador de pendências abertas;
- preserva a distinção entre pendências `pending` e `resolved`;
- reidrata as ações **Resolver**, **Reabrir** e **Excluir** quando o painel precisa ser reconstruído;
- sincroniza o progresso e os itens automáticos do checklist após alterações nas pendências;
- restringe a correção à tela de Revisão do Capítulo.

## Reteste

1. partir de uma Revisão com `0 pendentes`;
2. criar três pendências de tipos diferentes;
3. clicar em **Salvar agora**;
4. pressionar `F5`;
5. confirmar que as três pendências continuam visíveis e que o contador mostra `3 pendentes`;
6. resolver uma pendência;
7. pressionar `F5` e confirmar que o estado resolvido permanece;
8. excluir uma das pendências ainda abertas;
9. pressionar `F5` e confirmar que somente a pendência excluída desapareceu;
10. confirmar que checklist e progresso permanecem sincronizados.

## Critério de aprovação

Nenhuma pendência pode desaparecer após salvamento ou recarregamento sem ação explícita de **Resolver** ou **Excluir**, e o painel deve refletir exatamente o estado retornado pela API.
