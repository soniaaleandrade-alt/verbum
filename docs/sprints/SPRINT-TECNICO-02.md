# SPRINT TÉCNICO 02 — Dashboard Oficial do Verbum Studio

**Projeto:** Verbum Studio — Sistema Operacional para Escritores
**Tipo:** Sprint técnico de alinhamento visual e funcional
**Prioridade:** Alta
**Base:** Sprints 01 a 06 + Sprint Técnico 01
**Referência:** Dashboard funcional aprovado no protótipo Bolt

## Objetivo

Substituir o Dashboard provisório criado no Sprint 02 pela estrutura oficial do Verbum Studio, preservando os dados reais já persistidos no WordPress e usando somente métricas que possam ser calculadas com segurança no estágio atual do produto.

## Princípios

- O Dashboard deve ser uma visão geral do estúdio editorial, não uma tela de edição de capítulo.
- A etapa exibida deve sempre vir do campo real `stage` da obra.
- Métricas ainda não implementadas, como IMO e RME, não devem receber números fictícios.
- Cards futuros podem ser apresentados em estado preparado ou informativo, sem antecipar funcionalidades dos próximos sprints.
- O Dashboard deve continuar funcionando no runtime estático usado atualmente em produção e também na implementação React.

## Estrutura oficial

### 1. Banner editorial

Banner superior com:

- Verbum Studio;
- subtítulo `Sistema Operacional para Escritores`;
- frase `Do primeiro pensamento à publicação.`;
- identidade editorial própria do produto.

O Dashboard deixa de usar o cabeçalho provisório `Área atual / Início` para seguir a composição visual oficial.

### 2. Saudação e ações

Exibir:

- `Olá, [nome]`;
- `Visão geral do seu estúdio editorial`;
- ação `Comparar`, habilitada apenas quando houver pelo menos duas obras ativas;
- ação `+ Nova Obra`, direcionando ao Banco de Obras.

### 3. Acessos principais

Cards superiores:

- Biblioteca — funcional;
- Relatórios — preparado para sprint futuro;
- Calendário Editorial — preparado para sprint futuro.

### 4. Estatísticas

Exibir dados reais disponíveis:

- número de obras ativas;
- número de obras publicadas, quando identificável pelo estado atual;
- IMO médio em estado `—` enquanto o cálculo oficial do indicador não existir.

Não criar valores artificiais de IMO.

### 5. Últimas Obras

Listar até duas obras ativas ordenadas por atualização, mostrando:

- título;
- etapa atual real;
- progresso estrutural do workflow;
- acesso direto à obra.

### 6. Próxima Ação

Para a obra mais recentemente atualizada, mostrar:

- livro;
- etapa atual;
- próxima ação derivada do workflow;
- botão `Continuar` abrindo diretamente a obra.

Mapeamento inicial:

- Identificação → Concluir a Identificação da Obra;
- Projeto da Obra → Concluir o Projeto da Obra;
- Planejamento → Estruturar o Planejamento da Obra;
- Desenvolvimento → Continuar o desenvolvimento dos capítulos;
- demais etapas → ação coerente com o workflow.

### 7. Índice de Maturidade da Obra

O card visual é criado, porém o valor permanece `—` até a implementação formal do IMO.

Enquanto isso, pode mostrar:

- etapa atual;
- progresso estrutural do workflow;
- última atualização;
- próximo passo.

### 8. Radar de Maturidade

Criar a estrutura visual do radar sem inventar dados. O card informa que será alimentado pelos indicadores editoriais quando IMO/RME forem implementados.

### 9. Progresso Geral

Apresentar progresso estrutural do workflow, separado conceitualmente de IMO/RME.

Macrodimensões:

- Preparação — Identificação, Projeto da Obra e Planejamento;
- Produção — Desenvolvimento;
- Validação — Revisão Geral, Controle de Versões e Auditoria;
- Editorial — Mesa Editorial, Diagramação e Trâmites Legais;
- Publicação — Publicação.

Exibir também:

- capítulos previstos;
- obras ativas.

### 10. Acesso Rápido

Preparar cards para:

- Calendário Editorial;
- Cronograma;
- Metas de Escrita;
- Relatórios;
- Backup;
- Exportação.

Os módulos ainda não implementados permanecem desabilitados.

### 11. Tendência

Enquanto não existir série temporal de produtividade, mostrar:

- progresso estrutural médio das obras ativas;
- capítulos previstos;
- obras ativas.

Não apresentar tendência temporal fictícia.

## Correção incorporada

O Dashboard provisório exibia sempre `01 — Identificação`, mesmo quando a obra estava em `Projeto da Obra`.

O Dashboard oficial passa a consultar o campo real `stage` e exibe corretamente a etapa atual em todos os cards relacionados ao fluxo.

## Implementação técnica

### React

- substituir `Dashboard.tsx` pela estrutura oficial;
- permitir abertura direta de uma obra a partir do Dashboard;
- ocultar o cabeçalho provisório no Dashboard;
- manter cabeçalhos normais no Banco de Obras e Workspace.

### Runtime de produção

Adicionar `dashboard-official-runtime.js` para compatibilidade com o build estático atual, reaproveitando o estado real já carregado por `static-runtime.js`.

### Estilos

Adicionar `dashboard-official.css` com:

- banner;
- cards e grid responsivo;
- atalhos;
- progresso;
- radar em estado preparado;
- layout para desktop, tablet e mobile.

### Versão

Atualizar o plugin para `1.0.2` para facilitar a validação da nova instalação no WordPress.

## Preservado

- autenticação WordPress;
- Banco de Obras;
- projetos e obras existentes;
- Workspace;
- Identificação;
- capa da obra;
- Projeto da Obra;
- progresso e workflow dos Sprints anteriores;
- modo aplicativo em tela inteira do Sprint Técnico 01.

## Fora do escopo

Este sprint não implementa:

- cálculo oficial de IMO;
- cálculo oficial de RME;
- gráficos analíticos com histórico real;
- Calendário Editorial;
- Cronograma;
- Metas de Escrita;
- Relatórios;
- Backup;
- Exportação;
- Planejamento da Obra.

## Critérios de aceite

O Sprint Técnico 02 será aprovado quando:

- o Dashboard seguir a composição da referência oficial;
- o cabeçalho provisório não aparecer no Painel;
- Biblioteca abrir corretamente;
- Últimas Obras utilizar dados reais;
- Próxima Ação utilizar a etapa real;
- uma obra em `Projeto da Obra` não aparecer como `Identificação`;
- IMO/RME não exibirem valores inventados;
- módulos futuros estiverem claramente preparados ou desabilitados;
- o layout permanecer responsivo;
- o runtime de produção carregar o novo Dashboard;
- TypeScript, sintaxe JS, testes PHP, verificações frontend, build, PHP lint e whitespace estiverem verdes.

## Resultado esperado

O Verbum Studio passa a ter o Dashboard oficial como visão inicial do sistema, alinhado à referência funcional original e preparado para receber os indicadores e módulos que serão implementados nos próximos sprints.
