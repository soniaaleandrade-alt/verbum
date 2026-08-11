import { useEffect, useMemo, useState } from 'react';
import {
  assistEditorialDesk,
  completeEditorialDesk,
  createEditorialAdjustment,
  deleteEditorialAdjustment,
  getEditorialDesk,
  saveEditorialDesk,
  updateEditorialAdjustment,
} from '../services/editorial-desk-service';
import type { WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import type { EditorialAssessmentStatus, EditorialDeskStage as EditorialDeskData, EditorialFields, EditorialPriority } from '../types/editorial-desk';
import { WorkspaceFooter } from './WorkspaceFooter';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onPersisted: () => void | Promise<void>;
};

type SectionKey = keyof EditorialFields;

export function EditorialDeskStage({ workspace, onWorkspaceChange, onStageChange, onPersisted }: Props) {
  const [data, setData] = useState<EditorialDeskData | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [assistant, setAssistant] = useState('');
  const [adjustmentDescription, setAdjustmentDescription] = useState('');
  const [adjustmentType, setAdjustmentType] = useState<'editorial' | 'content'>('editorial');
  const [adjustmentPriority, setAdjustmentPriority] = useState<EditorialPriority>('medium');

  useEffect(() => {
    let alive = true;
    getEditorialDesk(workspace.book.id).then((result) => alive && setData(result)).catch((cause) => alive && setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Mesa Editorial.'));
    return () => { alive = false; };
  }, [workspace.book.id]);

  async function mutate(action: () => Promise<{ editorialDesk: EditorialDeskData; workspace: WorkWorkspaceData }>) {
    if (busy) return;
    setBusy(true); setError('');
    try {
      const result = await action();
      setData(result.editorialDesk);
      onWorkspaceChange(result.workspace);
      await onPersisted();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a operação.');
    } finally { setBusy(false); }
  }

  function updateSection(section: SectionKey, key: string, value: unknown) {
    if (!data) return;
    const current = data.fields[section];
    if (Array.isArray(current)) return;
    setData({ ...data, fields: { ...data.fields, [section]: { ...current, [key]: value } } as EditorialFields });
  }

  function updateElement(index: number, include: boolean) {
    if (!data) return;
    const elements = data.fields.elements.map((item, itemIndex) => itemIndex === index ? { ...item, include } : item);
    const order = data.fields.elementOrder.filter((key) => elements.some((item) => item.key === key && item.include));
    elements.forEach((item) => { if (item.include && !order.includes(item.key)) order.push(item.key); });
    setData({ ...data, fields: { ...data.fields, elements, elementOrder: order } });
  }

  async function persist(nextConfirmation = data?.finalConfirmation ?? false) {
    if (!data) return;
    await mutate(() => saveEditorialDesk(workspace.book.id, data.fields, data.flags, data.assessments, nextConfirmation));
  }

  async function toggleFlag(key: string, checked: boolean) {
    if (!data) return;
    const flags = { ...data.flags, [key]: checked };
    const next = { ...data, flags };
    setData(next);
    await mutate(() => saveEditorialDesk(workspace.book.id, next.fields, flags, next.assessments, next.finalConfirmation));
  }

  const approvedAssessments = useMemo(() => data ? Object.values(data.assessments).filter((item) => item.status === 'approved').length : 0, [data]);
  if (!data) return <section className="verbum-stage-content"><div className={`verbum-editorial-state${error ? ' is-error' : ''}`}>{error || 'Carregando Mesa Editorial...'}</div></section>;

  const identity = data.fields.identity;
  const positioning = data.fields.positioning;
  const backCover = data.fields.backCover;
  const author = data.fields.authorProfile;
  const edition = data.fields.edition;
  const layout = data.fields.layoutBrief;
  const cover = data.fields.coverBrief;
  const opinion = data.fields.opinion;
  const religious = data.fields.religious;

  return <>
    <section className="verbum-stage-content verbum-editorial-stage">
      <header className="verbum-editorial-heading">
        <div><span className="verbum-eyebrow">Etapa 8 de 11</span><h2>Mesa Editorial</h2><p>Consolide as decisões finais da obra antes da produção gráfica.</p></div>
        <div className="verbum-editorial-version"><small>Versão editorial em análise</small><strong>{data.version.number} — {data.version.name}</strong><span>Auditoria aprovada ✓ · {data.version.chapterCount} capítulos · {data.version.wordCount.toLocaleString('pt-BR')} palavras</span><code>{data.version.hash.slice(0, 18)}…</code></div>
      </header>

      {data.requiresNewAudit && <div className="verbum-editorial-warning is-critical"><strong>Existe ajuste de conteúdo nesta rodada.</strong><p>Alterações no corpo auditado exigem nova versão e nova Auditoria antes da aprovação editorial.</p><button type="button" onClick={() => onStageChange('versions')}>Voltar ao Controle de Versões</button></div>}
      {!data.auditStillValid && <div className="verbum-editorial-warning is-critical"><strong>A baseline aprovada pela Auditoria não está mais válida.</strong><p>Retorne à Auditoria antes de continuar.</p><button type="button" onClick={() => onStageChange('audit')}>Abrir Auditoria</button></div>}

      <div className="verbum-editorial-stats"><article><strong>{approvedAssessments}/{data.assessmentCriteria.length}</strong><span>Critérios aprovados</span></article><article><strong>{data.adjustments.length}</strong><span>Ajustes solicitados</span></article><article><strong>{data.openBlockingCount}</strong><span>Bloqueantes</span></article><article><strong>{data.progress}%</strong><span>Progresso</span></article><article><strong>{data.statusLabel}</strong><span>Status</span></article></div>

      <div className="verbum-editorial-layout">
        <main>
          <section className="verbum-editorial-card">
            <div className="verbum-editorial-card-head"><div><h3>Ficha Editorial da Obra</h3><p>Defina a identidade editorial que seguirá para a produção.</p></div><button type="button" disabled={busy || data.completed} onClick={() => void persist()}>Salvar decisões</button></div>
            <div className="verbum-editorial-grid two"><label>Título final<input value={identity.titleFinal} onChange={(event) => updateSection('identity', 'titleFinal', event.target.value)} /></label><label>Subtítulo final<input value={identity.subtitleFinal} onChange={(event) => updateSection('identity', 'subtitleFinal', event.target.value)} /></label><label>Nome editorial do autor<input value={identity.authorDisplay} onChange={(event) => updateSection('identity', 'authorDisplay', event.target.value)} /></label><label>Gênero<input value={identity.genre} onChange={(event) => updateSection('identity', 'genre', event.target.value)} /></label><label>Subgênero<input value={identity.subgenre} onChange={(event) => updateSection('identity', 'subgenre', event.target.value)} /></label><label>Categoria<input value={identity.category} onChange={(event) => updateSection('identity', 'category', event.target.value)} /></label><label>Idioma<input value={identity.language} onChange={(event) => updateSection('identity', 'language', event.target.value)} /></label><label>Público-alvo<input value={identity.audience} onChange={(event) => updateSection('identity', 'audience', event.target.value)} /></label></div>
            <label>Descrição curta<textarea value={identity.shortDescription} onChange={(event) => updateSection('identity', 'shortDescription', event.target.value)} /></label><label>Sinopse curta<textarea value={identity.synopsisShort} onChange={(event) => updateSection('identity', 'synopsisShort', event.target.value)} /></label><label>Sinopse editorial completa<textarea value={identity.synopsisFull} onChange={(event) => updateSection('identity', 'synopsisFull', event.target.value)} /></label>
          </section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Posicionamento Editorial</h3><p>Confronte proposta, público e percepção desejada.</p></div></div><div className="verbum-editorial-grid two"><label>Necessidade atendida<textarea value={positioning.need} onChange={(event) => updateSection('positioning', 'need', event.target.value)} /></label><label>Proposta principal<textarea value={positioning.proposal} onChange={(event) => updateSection('positioning', 'proposal', event.target.value)} /></label><label>Para quem foi escrita<textarea value={positioning.audience} onChange={(event) => updateSection('positioning', 'audience', event.target.value)} /></label><label>Diferencial<textarea value={positioning.differential} onChange={(event) => updateSection('positioning', 'differential', event.target.value)} /></label><label>Percepção desejada<textarea value={positioning.perception} onChange={(event) => updateSection('positioning', 'perception', event.target.value)} /></label><label>Referências de posicionamento<textarea value={positioning.references} onChange={(event) => updateSection('positioning', 'references', event.target.value)} /></label></div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Avaliação Editorial</h3><p>Registre a decisão para cada critério.</p></div></div><div className="verbum-editorial-assessments">{Object.values(data.assessments).map((item) => <article key={item.key}><strong>{item.label}</strong><select value={item.status} disabled={data.completed} onChange={(event) => setData({ ...data, assessments: { ...data.assessments, [item.key]: { ...item, status: event.target.value as EditorialAssessmentStatus } } })}><option value="pending">Pendente</option><option value="approved">Aprovado</option><option value="adjust">Ajustar</option></select><textarea value={item.note} disabled={data.completed} onChange={(event) => setData({ ...data, assessments: { ...data.assessments, [item.key]: { ...item, note: event.target.value } } })} placeholder="Observação editorial..." /></article>)}</div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Quarta capa e Autor</h3><p>Prepare os textos que seguirão para a capa e materiais editoriais.</p></div></div><div className="verbum-editorial-grid two"><label>Chamada principal<input value={backCover.headline} onChange={(event) => updateSection('backCover', 'headline', event.target.value)} /></label><label>Frase de destaque<input value={backCover.highlight} onChange={(event) => updateSection('backCover', 'highlight', event.target.value)} /></label></div><label>Texto da quarta capa<textarea value={backCover.text} onChange={(event) => updateSection('backCover', 'text', event.target.value)} /></label><label>Breve apresentação do autor<textarea value={backCover.authorShort} onChange={(event) => updateSection('backCover', 'authorShort', event.target.value)} /></label><div className="verbum-editorial-grid two"><label>Nome no livro<input value={author.displayName} onChange={(event) => updateSection('authorProfile', 'displayName', event.target.value)} /></label><label>Nome completo<input value={author.fullName} onChange={(event) => updateSection('authorProfile', 'fullName', event.target.value)} /></label><label>Mini biografia<textarea value={author.shortBio} onChange={(event) => updateSection('authorProfile', 'shortBio', event.target.value)} /></label><label>Biografia longa<textarea value={author.longBio} onChange={(event) => updateSection('authorProfile', 'longBio', event.target.value)} /></label><label>Site<input value={author.site} onChange={(event) => updateSection('authorProfile', 'site', event.target.value)} /></label><label>Redes sociais<input value={author.social} onChange={(event) => updateSection('authorProfile', 'social', event.target.value)} /></label></div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Elementos e Ordem Editorial</h3><p>Defina o que fará parte desta edição e a sequência enviada à Diagramação.</p></div></div><div className="verbum-editorial-elements">{data.fields.elements.map((item, index) => <label key={item.key} className={item.include ? 'is-included' : ''}><input type="checkbox" checked={item.include} disabled={data.completed} onChange={(event) => updateElement(index, event.target.checked)} /><span>{item.label}</span><em>{item.include ? 'Incluir' : 'Não incluir'}</em></label>)}</div><div className="verbum-editorial-order"><strong>Ordem final</strong><p>{data.fields.elementOrder.map((key, index) => `${index + 1}. ${data.fields.elements.find((item) => item.key === key)?.label || key}`).join(' → ') || 'Nenhum elemento selecionado.'}</p></div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Edição e Produção</h3><p>Registre as decisões que serão passadas à etapa de Diagramação.</p></div></div><div className="verbum-editorial-grid three"><label>Edição<input value={edition.edition} onChange={(event) => updateSection('edition', 'edition', event.target.value)} /></label><label>Ano<input value={edition.year} onChange={(event) => updateSection('edition', 'year', event.target.value)} /></label><label>Local<input value={edition.place} onChange={(event) => updateSection('edition', 'place', event.target.value)} /></label><label>Publicação<select value={edition.publisherType} onChange={(event) => updateSection('edition', 'publisherType', event.target.value)}><option value="independent">Independente</option><option value="publisher">Editora</option></select></label><label>Editora / selo<input value={edition.publisherName} onChange={(event) => updateSection('edition', 'publisherName', event.target.value)} /></label><label>Formato físico<input value={edition.trimSize} onChange={(event) => updateSection('edition', 'trimSize', event.target.value)} /></label></div><div className="verbum-editorial-formats">{['printed','digital'].map((format) => <label key={format}><input type="checkbox" checked={edition.formats.includes(format)} onChange={(event) => updateSection('edition', 'formats', event.target.checked ? [...edition.formats, format] : edition.formats.filter((item) => item !== format))} /> {format === 'printed' ? 'Impresso' : 'Digital'}</label>)}</div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Briefing de Diagramação</h3><p>Diretrizes editoriais; a execução técnica acontece no Sprint seguinte.</p></div></div><div className="verbum-editorial-grid two"><label>Estilo<input value={layout.style} onChange={(event) => updateSection('layoutBrief', 'style', event.target.value)} placeholder="Clássico, contemporâneo, minimalista..." /></label><label>Abertura de capítulos<input value={layout.chapterOpening} onChange={(event) => updateSection('layoutBrief', 'chapterOpening', event.target.value)} /></label><label>Elementos especiais<textarea value={layout.specialElements} onChange={(event) => updateSection('layoutBrief', 'specialElements', event.target.value)} /></label><label>Observações<textarea value={layout.notes} onChange={(event) => updateSection('layoutBrief', 'notes', event.target.value)} /></label></div><div className="verbum-editorial-switches">{[['dropCaps','Capitulares'],['quoteHighlights','Destaques de citações'],['footnotes','Notas de rodapé'],['images','Imagens'],['boxes','Quadros']].map(([key,label]) => <label key={key}><input type="checkbox" checked={Boolean(layout[key as keyof typeof layout])} onChange={(event) => updateSection('layoutBrief', key, event.target.checked)} /> {label}</label>)}</div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Briefing de Capa</h3><p>Defina a direção conceitual antes da produção visual.</p></div></div><div className="verbum-editorial-grid two"><label>Conceito<textarea value={cover.concept} onChange={(event) => updateSection('coverBrief', 'concept', event.target.value)} /></label><label>Sensação desejada<textarea value={cover.feeling} onChange={(event) => updateSection('coverBrief', 'feeling', event.target.value)} /></label><label>Elementos que devem aparecer<textarea value={cover.includeElements} onChange={(event) => updateSection('coverBrief', 'includeElements', event.target.value)} /></label><label>Elementos que não devem aparecer<textarea value={cover.avoidElements} onChange={(event) => updateSection('coverBrief', 'avoidElements', event.target.value)} /></label><label>Paleta desejada<input value={cover.palette} onChange={(event) => updateSection('coverBrief', 'palette', event.target.value)} /></label><label>Referências visuais<input value={cover.visualReferences} onChange={(event) => updateSection('coverBrief', 'visualReferences', event.target.value)} /></label></div><label>Frase de capa<input value={cover.coverPhrase} onChange={(event) => updateSection('coverBrief', 'coverPhrase', event.target.value)} /></label></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Parecer Editorial</h3><p>Registre a análise e a recomendação final da rodada.</p></div></div><div className="verbum-editorial-grid two"><label>Resumo da avaliação<textarea value={opinion.summary} onChange={(event) => updateSection('opinion', 'summary', event.target.value)} /></label><label>Pontos fortes<textarea value={opinion.strengths} onChange={(event) => updateSection('opinion', 'strengths', event.target.value)} /></label><label>Pontos de atenção<textarea value={opinion.attention} onChange={(event) => updateSection('opinion', 'attention', event.target.value)} /></label><label>Recomendações<textarea value={opinion.recommendations} onChange={(event) => updateSection('opinion', 'recommendations', event.target.value)} /></label><label>Riscos editoriais<textarea value={opinion.risks} onChange={(event) => updateSection('opinion', 'risks', event.target.value)} /></label><label>Conclusão<textarea value={opinion.conclusion} onChange={(event) => updateSection('opinion', 'conclusion', event.target.value)} /></label></div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Perfil editorial religioso</h3><p>Campos opcionais para obras religiosas.</p></div></div><label>Natureza da obra<select value={religious.nature} onChange={(event) => updateSection('religious', 'nature', event.target.value)}><option value="">Não definido</option>{['Devocional','Formativa','Catequética','Teológica','Testemunhal','Espiritualidade','Pastoral'].map((item) => <option key={item} value={item}>{item}</option>)}</select></label><div className="verbum-editorial-switches">{[['bible','Possui citações bíblicas'],['catechism','Referências ao Catecismo'],['magisterium','Documentos do Magistério'],['specializedReview','Requer revisão eclesial especializada']].map(([key,label]) => <label key={key}><input type="checkbox" checked={Boolean(religious[key as keyof typeof religious])} onChange={(event) => updateSection('religious', key, event.target.checked)} /> {label}</label>)}</div></section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><div><h3>Ajustes solicitados</h3><p>Alterações de conteúdo exigem nova versão e nova Auditoria.</p></div></div><div className="verbum-editorial-adjustment-new"><select value={adjustmentType} onChange={(event) => setAdjustmentType(event.target.value as 'editorial' | 'content')}><option value="editorial">Editorial não estrutural</option><option value="content">Conteúdo — requer nova Auditoria</option></select><select value={adjustmentPriority} onChange={(event) => setAdjustmentPriority(event.target.value as EditorialPriority)}>{data.priorities.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><textarea value={adjustmentDescription} onChange={(event) => setAdjustmentDescription(event.target.value)} placeholder="Descreva o ajuste solicitado..." /><button type="button" disabled={!adjustmentDescription.trim() || busy || data.completed} onClick={() => void mutate(() => createEditorialAdjustment(workspace.book.id, { type: adjustmentType, priority: adjustmentPriority, description: adjustmentDescription })).then(() => setAdjustmentDescription(''))}>Solicitar ajuste</button></div><div className="verbum-editorial-adjustments">{data.adjustments.length === 0 ? <p>Nenhum ajuste registrado.</p> : data.adjustments.map((item) => <article key={item.id} className={`type-${item.type} priority-${item.priority}`}><header><div><strong>{item.typeLabel}</strong><span>{item.priorityLabel}</span></div><em>{item.statusLabel}</em></header><p>{item.description}</p>{item.chapterTitle && <small>Capítulo: {item.chapterTitle}</small>}{item.justification && <blockquote>{item.justification}</blockquote>}<footer>{item.status !== 'resolved' && <button type="button" disabled={busy || data.completed} onClick={() => void mutate(() => updateEditorialAdjustment(workspace.book.id, item.id, { status: 'resolved' }))}>Resolver</button>}{item.status !== 'in_progress' && <button type="button" disabled={busy || data.completed} onClick={() => void mutate(() => updateEditorialAdjustment(workspace.book.id, item.id, { status: 'in_progress' }))}>Em andamento</button>}<button type="button" className="is-danger" disabled={busy || data.completed} onClick={() => window.confirm('Excluir este ajuste?') && void mutate(() => deleteEditorialAdjustment(workspace.book.id, item.id))}>Excluir</button></footer></article>)}</div></section>
        </main>

        <aside>
          <section className="verbum-editorial-card verbum-editorial-progress"><div className="verbum-editorial-card-head"><h3>Checklist da Mesa Editorial</h3><strong>{data.progress}%</strong></div><div className="verbum-editorial-progressbar"><span style={{ width: `${data.progress}%` }} /></div>{data.checklist.map((item) => { const manual = !item.automatic && item.key !== 'completed'; const checked = manual ? Boolean(data.flags[item.key]) : item.completed; return <label key={item.key} className={checked ? 'is-complete' : ''}><input type="checkbox" checked={checked} disabled={!manual || busy || data.completed} onChange={(event) => manual && void toggleFlag(item.key, event.target.checked)} /><span>{item.label}</span></label>; })}<label className={data.finalConfirmation ? 'is-confirmation is-complete' : 'is-confirmation'}><input type="checkbox" checked={data.finalConfirmation} disabled={busy || data.completed} onChange={(event) => { const value = event.target.checked; setData({ ...data, finalConfirmation: value }); void persist(value); }} /><span>Confirmo esta decisão editorial e a versão destinada à Diagramação.</span></label><button type="button" className="verbum-primary-button" disabled={!data.ready || busy || data.completed} onClick={() => void mutate(() => completeEditorialDesk(workspace.book.id)).then(() => onStageChange('layout'))}>{data.completed ? 'Aprovada para Diagramação ✓' : 'Aprovar para Diagramação'}</button></section>

          <section className="verbum-editorial-card verbum-editorial-assistant"><h3>Assistente Editorial</h3><p>Sugere alternativas; nenhuma decisão é aplicada automaticamente.</p><div>{[['positioning','Posicionamento'],['title','Título e subtítulo'],['synopsis','Sinopse'],['back_cover','Quarta capa'],['audience','Adequação ao público'],['cover_brief','Briefing de capa'],['layout_brief','Briefing de diagramação'],['opinion','Parecer editorial']].map(([key,label]) => <button key={key} type="button" disabled={busy || data.completed} onClick={() => { setBusy(true); setError(''); assistEditorialDesk(workspace.book.id, key).then((result) => setAssistant(result.suggestion)).catch((cause) => setError(cause instanceof Error ? cause.message : 'Não foi possível usar o Assistente Editorial.')).finally(() => setBusy(false)); }}>{label}</button>)}</div>{assistant && <div className="verbum-editorial-assistant-result"><textarea value={assistant} onChange={(event) => setAssistant(event.target.value)} /><p>Copie ou adapte a sugestão nos campos correspondentes.</p><button type="button" onClick={() => setAssistant('')}>Descartar</button></div>}</section>

          <section className="verbum-editorial-card"><div className="verbum-editorial-card-head"><h3>Histórico de rodadas</h3></div>{data.rounds.map((round) => <article key={round.id} className="verbum-editorial-round"><strong>Mesa Editorial #{round.number} · {round.versionNumber}</strong><span>{round.status === 'approved_for_layout' ? 'Aprovada para Diagramação ✓' : round.status === 'adjustments_requested' ? 'Ajustes solicitados' : 'Em avaliação'}</span><small>{round.startedAt ? new Date(round.startedAt).toLocaleString('pt-BR') : ''}</small></article>)}</section>
        </aside>
      </div>
      {error && <p className="verbum-editorial-message is-error">{error}</p>}
    </section>
    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('audit')} onBackToLibrary={() => onStageChange('audit')} />
  </>;
}
