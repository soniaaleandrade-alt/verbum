# HOM-025A — Refinamento da Estrutura da Obra

## Objetivo
Refinar a etapa Estrutura após a homologação em produção, reduzindo a densidade visual e separando semanticamente o novo Fio Condutor do conteúdo histórico de Estrutura Geral.

## Alterações
- novo campo persistente `Fio condutor e movimento da obra`, armazenado em `_verbum_structure_thread`;
- o antigo `_verbum_planning_general_structure` não é sobrescrito e passa a ficar disponível em `Conteúdos anteriores preservados` para consulta;
- autosave independente do Fio Condutor, com estados `Salvando...`, `Alterações salvas` e `Erro ao salvar`;
- Elementos iniciais e Elementos finais iniciam recolhidos; Corpo da obra permanece aberto;
- itens estruturais ficam mais compactos;
- capítulos vinculados a uma Parte ganham recuo e indicação visual do pai;
- Subcapítulos ganham segundo nível de recuo;
- ações secundárias `Duplicar` e `Remover` passam para menu de reticências, mantendo Subir/Descer e Abrir capítulo acessíveis;
- não há alteração automática de vínculo, ordem, título, conteúdo ou status de capítulos existentes.

## Compatibilidade
A rota técnica continua `planning`. A API existente continua sendo usada. O campo novo é anexado à resposta de `planning-stage` por camada compatível, sem migração SQL e sem apagar campos históricos.

## Homologação esperada
1. Abrir Estrutura.
2. Confirmar que o antigo texto extenso não aparece mais como Fio Condutor.
3. Confirmar que `Fio condutor` inicia vazio (quando ainda não preenchido) e salva automaticamente.
4. Recarregar a página e confirmar persistência.
5. Abrir `Conteúdos anteriores preservados` e confirmar a presença de `Estrutura Geral anterior`.
6. Confirmar Elementos iniciais/finais recolhidos e Corpo aberto.
7. Confirmar menu de reticências para Duplicar/Remover.
8. Associar manualmente um capítulo a uma Parte e confirmar a indicação visual sem alterar o conteúdo do capítulo.
