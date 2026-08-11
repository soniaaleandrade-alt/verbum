# HOM-013 — Reorganização do fluxo e checklist da Revisão

**Versão:** 2.5.8
**Etapa:** Desenvolvimento → Revisão do Capítulo
**Prioridade:** Alta
**Tipo:** UX / fluxo editorial / checklist

## Problema observado

A tela de Revisão possuía quatro abas — Conteúdo, Estrutural, Clareza e Estilo e Linguística — porém o checklist lateral permanecia praticamente igual em todas elas. Isso dificultava entender quais critérios pertenciam a cada modalidade de revisão e misturava itens manuais, automáticos e de validação final.

## Correção implementada

- cada modalidade passa a exibir seu próprio checklist;
- foi criada a quinta etapa **Validação Final**;
- o painel lateral mostra o progresso geral da Revisão e o progresso da modalidade atual;
- Conteúdo reúne objetivo, pergunta central, tese, argumentação, lacunas e alinhamento;
- Estrutural reúne introdução, sequência lógica, transições, equilíbrio e conclusão;
- Clareza e Estilo reúne repetições, redundâncias, vocabulário, tom e fluidez;
- Linguística reúne ortografia, gramática, concordância, pontuação e digitação;
- Validação Final reúne fontes, citações, pendências, prontidão e conclusão;
- itens automáticos são apresentados visualmente como automáticos, sem parecer caixas comuns que dependem do usuário;
- critérios detalhados adicionais são persistidos no capítulo e permanecem após F5;
- a conclusão de Estrutural, Clareza e Estilo e Linguística só é liberada depois dos critérios detalhados da própria modalidade.

## Reteste

1. abrir a Revisão do capítulo;
2. alternar entre as cinco modalidades e confirmar que o checklist muda conforme a aba;
3. marcar critérios em Conteúdo, salvar e pressionar F5;
4. confirmar persistência e progresso;
5. repetir em Estrutural, Clareza e Estilo e Linguística;
6. abrir Validação Final e confirmar que Fontes, Pendências e Revisão concluída aparecem como automáticos;
7. resolver qualquer pendência restante e confirmar atualização automática;
8. marcar Citações conferidas e Capítulo pronto para conclusão;
9. concluir a Revisão.

## Critério de aprovação

O fluxo deve deixar claro o que revisar em cada modalidade, preservar os critérios marcados após recarregamento e manter a Validação Final sincronizada com fontes, pendências e conclusão.
