# Sprint 12 — Revisão do Capítulo

**Versão:** 1.8.0
**Etapa interna:** 4 de 4
**Fluxo:** Preparação ✓ → Pesquisa ✓ → Redação ✓ → Revisão

## Objetivo

Fechar o ciclo interno de produção de cada capítulo, permitindo revisar conteúdo, estrutura, clareza, linguagem, fontes, citações e pendências antes de marcar o capítulo como concluído.

## Entregas

- workspace de Revisão em três áreas: navegação/checklist, texto completo e painel de apoio;
- modos de revisão: Conteúdo, Estrutural, Clareza e Estilo e Linguística;
- edição direta da Introdução, Desenvolvimento e Conclusão com autosave;
- comparação Direção original e Estrutura planejada × Estrutura escrita;
- conferência obrigatória das fontes efetivamente utilizadas;
- tratamento de fontes selecionadas mas não utilizadas com opção de dispensar nesta versão;
- pendências tipadas: Conteúdo, Estrutura, Clareza, Gramática, Repetição, Fonte, Citação, Coerência, Estilo, Doutrinal e Outro;
- notas e comentários da Redação com ações Resolver, Manter e Converter em pendência;
- Assistente de Revisão contextual sem alteração automática do texto e sem invenção de fontes;
- checklist de 10 itens e regra de conclusão;
- snapshots de segurança pré-conclusão e conclusão da Revisão;
- possibilidade de reabrir capítulo concluído, preservando histórico e sinalizando alterações posteriores;
- capítulo passa a 100% quando Revisão é concluída;
- Desenvolvimento é recalculado e só pode ser concluído quando todos os capítulos estiverem concluídos;
- endpoints REST próprios e isolamento por conta, obra e capítulo.

## Regra de conclusão

A Revisão só pode ser concluída quando:

1. a Redação estiver concluída;
2. objetivo, pergunta central, tese, estrutura, clareza e linguagem estiverem conferidos;
3. todas as fontes efetivamente utilizadas estiverem verificadas;
4. citações estiverem confirmadas pelo autor;
5. não houver pendências abertas;
6. o autor marcar o capítulo como pronto para conclusão.

## Segurança

A conclusão cria uma versão de pré-conclusão e uma versão final revisada. Se o capítulo for editado depois, ele permanece concluído e recebe o estado `alteredAfterCompletion`, preservando a versão concluída no histórico.

## Fora deste Sprint

Revisão Geral da Obra, revisão profissional colaborativa, convite de revisores externos, controle avançado de alterações, Auditoria, Diagramação e Publicação.
