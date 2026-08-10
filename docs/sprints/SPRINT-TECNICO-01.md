# SPRINT TÉCNICO 01 — Ajustes após teste real

**Projeto:** Verbum Studio — Sistema Operacional para Escritores  
**Tipo:** Sprint técnico de estabilização  
**Prioridade:** Alta  
**Base:** Sprints 01 a 06 integrados  
**Origem:** Teste funcional realizado no WordPress real em 09/08/2026

## Objetivo

Corrigir inconsistências encontradas durante o primeiro teste de ponta a ponta do Verbum Studio no servidor WordPress antes do início do Sprint 07.

## Evidências validadas no teste real

Foram confirmados em ambiente real:

- Dashboard e autenticação WordPress;
- Banco de Obras;
- criação e persistência de Projeto;
- criação e persistência de Obra;
- Workspace da Obra;
- workflow editorial;
- Identificação;
- upload e persistência da capa;
- conclusão da Identificação;
- liberação do Projeto da Obra;
- salvamento parcial do Projeto da Obra;
- checklist e progresso automáticos.

## Ajustes incluídos

### 1. Experiência em tela inteira

O container `[verbum_app]` passa a ocupar o viewport como aplicativo independente, cobrindo cabeçalho, conteúdo e rodapé do tema WordPress. A barra administrativa do WordPress permanece acessível quando o usuário está autenticado.

### 2. Cache busting dos assets

CSS e JavaScript passam a utilizar versão baseada na modificação real dos arquivos. O loader do build propaga sua query de versão para os runtimes carregados dinamicamente. Isso evita que uma atualização continue exibindo bundles antigos do Verbum Studio.

### 3. Versão técnica

A versão do plugin passa de `1.0.0` para `1.0.1`, permitindo distinguir o pacote corrigido durante a atualização no WordPress.

### 4. Criação rápida de obra

O modal `Criar nova obra` fica restrito a:

- Projeto;
- Título;
- Subtítulo.

Os demais dados editoriais pertencem à etapa Identificação dentro do Workspace, evitando duplicação de campos e divergência entre telas.

### 5. Situação editorial versus etapa atual

Os cards do Banco de Obras passam a distinguir visualmente:

- **Situação:** Planejamento, Em andamento ou Em pausa;
- **Etapa atual:** Identificação, Projeto da Obra, Planejamento etc.

A etapa exibida passa a acompanhar o campo real `stage` da obra.

### 6. Primeira etapa sem ação anterior

Na Identificação, a ação `Etapa anterior` não deve ser apresentada. A primeira etapa não possui predecessora no workflow.

### 7. Última edição no horário local

O runtime passa a interpretar corretamente timestamps sem offset como UTC antes de apresentá-los na data local do navegador, evitando exibir o dia seguinte durante a noite no Brasil.

### 8. Ajustes visuais do teste real

Incluídos pequenos ajustes para:

- altura da sidebar dentro do viewport do aplicativo;
- alinhamento do rodapé da primeira etapa;
- texto auxiliar do modal de criação rápida;
- comportamento responsivo do modo tela inteira.

## Observação sobre duplicidade do plugin

A duplicidade visual encontrada no WordPress é um problema de implantação provocado por instalações em pastas diferentes (`verbum-main` e `verbum-studio`). Não deve ser tratada apagando dados da aplicação. A versão `1.0.1` e o pacote final com a pasta padronizada `verbum-studio` serão utilizados para normalizar a instalação depois do merge.

## Fora do escopo

Este Sprint não implementa funcionalidades do Sprint 07 nem altera regras editoriais dos Sprints 01 a 06.

Não inclui:

- Planejamento da Obra;
- capítulos;
- Pesquisa;
- Redação;
- Revisão;
- IA;
- novos indicadores IMO/RME.

## Critérios de aceite

O Sprint será aprovado quando:

- o aplicativo ocupar a tela útil sem mostrar o layout do tema WordPress;
- os assets atualizados carregarem sem depender de limpeza manual de cache;
- o WordPress identificar a versão `1.0.1`;
- Criar nova obra mostrar somente Projeto, Título e Subtítulo;
- cards diferenciarem Situação e Etapa atual;
- Identificação não mostrar Etapa anterior;
- Última edição respeitar a data local do navegador;
- os Sprints 01 a 06 continuarem funcionais;
- TypeScript, testes PHP, verificações frontend, build, PHP lint e whitespace permanecerem verdes.

## Resultado esperado

Depois deste Sprint técnico, o ambiente real estará estabilizado para iniciar o Sprint 07 — Planejamento da Obra sem carregar inconsistências descobertas no primeiro teste de ponta a ponta.
