# HOM-026 — Central de Capítulos e Preparação enxuta

## Objetivo
Eliminar a antiga ideia visual de “Desenvolvimento da Obra” como etapa adicional e consolidar a etapa oficial **Capítulos** como central de trabalho dos capítulos já definidos na Estrutura.

Fluxo interno preservado por capítulo:

**Preparação → Pesquisa → Redação → Revisão → Concluído**

## Central de Capítulos
- título visual alterado para **Capítulos**;
- resumo compacto substitui os sete cards de estatísticas;
- destaque **Continuar trabalhando** abre o capítulo incompleto editado mais recentemente;
- ao abrir um capítulo, o sistema mantém sua etapa atual e carrega Preparação, Pesquisa, Redação ou Revisão correspondente;
- filtros por etapa, pesquisa e ordenação permanecem;
- cards exibem Parte quando o vínculo já existe na Estrutura;
- a central não cria, exclui, reorganiza ou reclassifica capítulos;
- estado vazio direciona para **Estrutura da Obra**;
- removido o rótulo “Concluir Desenvolvimento”; quando todos os capítulos estiverem concluídos, aparece **Continuar para Revisão da Obra** usando a mesma transição técnica existente.

## Preparação do Capítulo
A preparação foi reduzida para cinco requisitos essenciais:
1. Objetivo do capítulo;
2. Pergunta central;
3. Tese do capítulo;
4. Estrutura do capítulo;
5. Pesquisa necessária (ao menos uma categoria de fonte).

Quando os cinco requisitos estão preenchidos, o progresso é 100% e a Pesquisa pode ser liberada.

Campos anteriores não foram apagados. Permanecem disponíveis de forma opcional em blocos recolhíveis:
- **Intenção espiritual**: intenção, virtude e oração;
- **Aprofundar preparação**: subtítulo, finalidade, mensagem principal, frase norteadora, palavras-chave e observações.

## Compatibilidade e dados
- rota técnica `development` preservada;
- endpoint técnico `development-stage/complete` preservado como mecanismo de transição para a Revisão da Obra;
- metadados existentes de Preparação não foram removidos nem renomeados;
- nenhum conteúdo de Pesquisa, Redação ou Revisão foi alterado nesta entrega;
- nenhum capítulo é criado, excluído, renomeado ou reordenado pela Central de Capítulos.

## Homologação manual
1. Abrir Capítulos e confirmar o resumo compacto e o card Continuar trabalhando.
2. Confirmar que os quatro capítulos existentes permanecem na mesma ordem.
3. Confirmar que “Continuar” abre diretamente a etapa atual do capítulo.
4. Na Preparação, preencher os cinco essenciais e verificar 100%.
5. Salvar, recarregar e confirmar persistência.
6. Expandir os blocos opcionais e confirmar que dados antigos permanecem disponíveis.
7. Concluir Preparação e confirmar abertura/liberação da Pesquisa.
