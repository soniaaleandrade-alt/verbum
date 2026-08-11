# HOM-012 — Persistência das Pendências da Revisão

**Versão:** 2.5.6  
**Etapa:** Desenvolvimento → Revisão do Capítulo  
**Prioridade:** Alta  
**Tipo:** Persistência / cache

## Problema observado

Pendências registradas na Revisão apareciam corretamente na sessão atual, porém após salvar e recarregar a página o estado podia voltar sem as pendências, fazendo o checklist indicar novamente que não havia pendências abertas.

## Correção

- as respostas REST da Revisão recebem política explícita `no-store / no-cache`;
- após mutações da Revisão, o cache do post e do post meta do capítulo é limpo;
- a leitura principal da Revisão no navegador é feita com `cache: no-store` e parâmetro de cache-busting;
- o guard de cache é carregado antes do runtime funcional da Revisão;
- a correção fica restrita à etapa Desenvolvimento/Revisão e não amplia o carregamento inicial do Painel ou de Minhas Obras.

## Reteste

1. criar três pendências de tipos diferentes;
2. clicar em **Salvar agora**;
3. pressionar `F5`;
4. confirmar que as três pendências continuam presentes e que o contador permanece correto;
5. resolver uma pendência e atualizar a página;
6. confirmar a persistência do status resolvido;
7. excluir uma das pendências restantes e atualizar a página;
8. confirmar que somente a pendência excluída desapareceu.

## Critério de aprovação

Nenhuma pendência pode desaparecer após salvamento ou recarregamento sem ação explícita de **Resolver** ou **Excluir**.
