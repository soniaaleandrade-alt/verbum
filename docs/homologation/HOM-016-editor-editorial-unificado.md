# HOM-016 — Editor Editorial Unificado: Redação + Revisão

**Versão:** 2.6.0
**Área:** Desenvolvimento → Capítulo → Redação / Revisão
**Tipo:** UX estrutural / editor editorial / produtividade

## Objetivo

Padronizar Redação e Revisão como duas modalidades do mesmo ambiente editorial, tomando como referência o modelo visual aprovado na homologação: navegação contextual compacta, estrutura do capítulo à esquerda, manuscrito como área principal e ferramentas contextuais à direita.

## Arquitetura comum

### Topo

- caminho compacto: **Livro → Capítulo → etapa atual**;
- estado de salvamento automático;
- palavras, tempo/meta ou pendências/progresso;
- ação principal **Concluir Redação** ou **Concluir Revisão**;
- menu secundário com salvamento manual e retorno ao Desenvolvimento.

### Coluna esquerda — Estrutura do capítulo

- Introdução;
- tópicos da estrutura escrita;
- Conclusão;
- navegação por clique até o trecho correspondente;
- na Redação, ação **Adicionar seção**;
- na Revisão, checklist e progresso permanecem disponíveis abaixo da estrutura.

### Centro — Manuscrito

- editor editorial limpo e dominante;
- barra de ferramentas fixa;
- formatação de parágrafo, fonte e tamanho;
- recursos já existentes de negrito, itálico, sublinhado, listas, citação, imagem, tabela, notas e comentários continuam preservados quando disponíveis;
- texto editável diretamente nas duas etapas.

### Direita — Painel contextual com abas

#### Redação

- Pesquisa;
- IA;
- Referências;
- Notas;
- Estatísticas.

#### Revisão

- Análise;
- IA;
- Referências;
- Pendências;
- Notas.

Os dados continuam vindo das rotinas e APIs existentes. O HOM-016 reorganiza a apresentação sem substituir a persistência homologada anteriormente.

### Barra inferior

- sessão atual;
- palavras;
- meta quando aplicável;
- progresso;
- modo foco;
- tema claro/escuro;
- controle de zoom.

## Revisão guiada

Na Revisão permanece o fluxo:

**Conteúdo → Estrutural → Clareza e Estilo → Linguística → Validação Final**

Esse fluxo aparece como uma faixa compacta acima do editor, enquanto o checklist detalhado continua sincronizado com o HOM-013.

## Compatibilidade funcional

O HOM-016 não altera as APIs de:

- Redação;
- Revisão;
- pesquisa;
- fontes;
- ideias;
- notas e comentários;
- pendências;
- versões;
- conclusão das etapas.

Também preserva os hotfixes HOM-010, HOM-011, HOM-012, HOM-013, HOM-014 e HOM-015.

## Critérios de aceite

1. Redação e Revisão apresentam a mesma arquitetura visual de três colunas.
2. O topo mostra apenas o contexto necessário para o trabalho editorial.
3. A estrutura do capítulo aparece à esquerda e navega até os trechos do manuscrito.
4. O manuscrito permanece como área visual dominante.
5. As ferramentas contextuais são organizadas por abas na lateral direita.
6. A Revisão mantém as cinco modalidades do HOM-013.
7. Salvar agora e salvamento automático continuam funcionando.
8. Concluir Redação e Concluir Revisão continuam obedecendo às regras já homologadas.
9. Pendências, fontes, notas, imagens e conteúdo persistem após F5.
10. Modo foco e zoom não alteram o conteúdo persistido.
11. O layout se adapta a telas menores sem bloquear o manuscrito.
12. Não há regressão nos fluxos anteriores do capítulo.

## Reteste sugerido

### Redação

1. abrir um capítulo em Redação;
2. conferir topo, estrutura, manuscrito e painel direito;
3. alternar Pesquisa, IA, Referências, Notas e Estatísticas;
4. editar um trecho e salvar;
5. inserir imagem já suportada pelo editor;
6. pressionar F5 e conferir persistência;
7. testar modo foco e zoom.

### Revisão

1. abrir o mesmo capítulo em Revisão;
2. conferir a mesma arquitetura visual;
3. alternar as cinco modalidades de revisão;
4. alternar Análise, IA, Referências, Pendências e Notas;
5. criar/resolver uma pendência;
6. editar o manuscrito e salvar;
7. pressionar F5 e conferir persistência;
8. validar o progresso e a conclusão conforme os critérios já homologados.
