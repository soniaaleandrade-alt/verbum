# HOM-015 — Compactação e reorganização do topo da página da obra

**Versão:** 2.5.10  
**Área:** Obra → Desenvolvimento → Capítulo  
**Tipo:** UX / navegação / hierarquia visual

## Problema observado

Após o HOM-014, o topo das páginas internas do capítulo ainda apresentava informações de contexto em camadas separadas. A etapa **Desenvolvimento** aparecia de forma redundante e a capa ainda ocupava mais espaço do que o necessário para uma área operacional.

## Correção implementada

- a capa da obra passa a funcionar como miniatura discreta de aproximadamente 40 × 50 px no desktop;
- o cabeçalho da obra recebe menos altura e tipografia mais compacta;
- o contexto da obra deixa de repetir a etapa atual na linha de métricas;
- a faixa branca de contexto passa a reunir o caminho de navegação:
  **Minhas Obras › Obra › Desenvolvimento › Capítulo › Etapa interna**;
- o contador da etapa macro permanece junto ao contexto, por exemplo **4 de 11**;
- **Ver etapas da obra** permanece disponível à direita e continua abrindo o workflow macro sob demanda;
- o retorno isolado **‹ Desenvolvimento** deixa de aparecer visualmente dentro do capítulo, pois o contexto já está no breadcrumb;
- o workflow do capítulo **Preparação → Pesquisa → Redação → Revisão** continua sendo o principal fluxo operacional;
- margens, altura e espaçamentos do cabeçalho do capítulo foram reduzidos;
- em telas menores, partes secundárias do breadcrumb são ocultadas progressivamente para preservar legibilidade.

## Segurança funcional

Este hotfix não altera:

- APIs;
- regras de conclusão;
- persistência de pendências;
- manuscrito;
- fontes;
- notas;
- histórico de versões;
- lógica dos workflows.

## Critérios de aceite

1. o topo ocupa menos altura que na versão 2.5.9;
2. a capa aparece como miniatura e não domina o cabeçalho;
3. a faixa branca mostra claramente o caminho da obra e do capítulo;
4. **Desenvolvimento** não aparece de forma solta e redundante;
5. **Ver etapas da obra** continua funcionando;
6. o workflow do capítulo permanece em destaque;
7. a área útil do manuscrito começa mais acima na tela;
8. a estrutura permanece estável após F5;
9. não há regressão nos recursos homologados anteriormente.

## Reteste visual

1. abrir um capítulo em Revisão;
2. conferir miniatura, título e métricas compactas;
3. conferir o breadcrumb na faixa branca;
4. abrir e fechar **Ver etapas da obra**;
5. conferir Preparação, Pesquisa, Redação e Revisão;
6. pressionar F5;
7. confirmar que topo, contexto e dados permanecem corretos.
