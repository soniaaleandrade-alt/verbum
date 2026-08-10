# Hotfix 1.3.1 — Planejamento da Obra

## Problema
A etapa Planejamento estava funcional, porém o stylesheet específico `planning-stage.css` não era enfileirado diretamente pelo WordPress. O runtime de produção carregava, mas a página herdava estilos genéricos e aparecia como um formulário corrido, diferente da referência aprovada do Bolt.

## Correção
- registra `planning-stage.css` na cadeia oficial de estilos do `FrontendAssets`;
- inclui `planning-stage-runtime.js` no controle de versão/cache dos assets;
- reforça a composição visual em duas colunas no desktop;
- mantém os blocos Pergunta Central, Metodologia, Estrutura da Obra, Organização Editorial e Geração dos Capítulos em cards;
- mantém o checklist de Progresso da Etapa na coluna lateral e sticky no desktop;
- normaliza campos, bordas, tipografia, espaçamento e estados de foco;
- preserva responsividade para tablet e celular;
- adiciona teste para impedir regressão no carregamento do stylesheet.

## Preservado
Nenhuma regra funcional do Sprint 07 foi alterada. Permanecem intactos backend, endpoints REST, dados salvos, checklist, geração/sincronização de capítulos e conclusão da etapa.
