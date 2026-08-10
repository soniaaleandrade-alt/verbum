import { useEffect, useMemo, useRef, useState } from 'react';
import {
  assistGeneralReview,
  completeGeneralReview,
  createGeneralReviewIssue,
  deleteGeneralReviewIssue,
  getGeneralReview,
  getGeneralReviewReading,
  saveGeneralReview,
  updateGeneralReviewIssue,
} from '../services/library-service';
import type {
  GeneralReviewEvaluationStatus,
  GeneralReviewFlags,
  GeneralReviewFrontMatter,
  GeneralReviewInput,
  GeneralReviewIssueType,
  GeneralReviewPriority,
  GeneralReviewProgress,
  GeneralReviewReading,
  GeneralReviewTerm,
  GeneralReviewTransition,
} from '../types/general-review';
import type { WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import { WorkspaceFooter } from './WorkspaceFooter';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onPersisted: () => void | Promise<void>;
};

type FormState = {
  flags: GeneralReviewFlags;
  evaluations: GeneralReviewProgress['evaluations'];
  transitions: GeneralReviewTransition[];
  terms: GeneralReviewTerm[];
  frontMatter: GeneralReviewFrontMatter;
  finalConfirmation: boolean;
};

type ReviewMode = 'coherence' | 'continuity' | 'repetitions' | 'gaps' | 'structure' | 'language' | 'references' | 'editorial';

const modes: Array<{ key: ReviewMode; label: string; help: string }> = [
  { key: 'coherence', label: 'Coerência', help: 'Confira a linha lógica, objetivo, pergunta central e tese da obra.' },
  { key: 'continuity', label: 'Continuidade', help: 'Avalie a ligação e as transições entre capítulos.' },
  { key: 'repetitions', label: 'Repetições', help: 'Registre conteúdos repetidos que precisam ser mantidos, reduzidos ou movidos.' },
  { key: 'gaps', label: 'Lacunas', help: 'Identifique assuntos ausentes ou insuficientemente desenvolvidos.' },
  { key: 'structure', label: 'Estrutura', help: 'Analise a ordem dos capítulos, partes e progressão argumentativa.' },
  { key: 'language', label: 'Linguagem', help: 'Confira uniformidade de estilo, tom e terminologia.' },
  { key: 'references', label: 'Referências', help: 'Faça a conferência global das fontes e da consistência das referências.' },
  { key: 'editorial', label: 'Editorial', help: 'Revise introdução, conclusão e demais textos editoriais antes da próxima etapa.' },
];

const evaluationLabels: Array<{ value: GeneralReviewEvaluationStatus; label: string }> = [
  { value: '', label: 'Avaliar...' }, { value: 'yes', label: 'Sim' }, { value: 'partial', label: 'Parcialmente' }, { value: 'adjust', label: 'Precisa de ajuste' },
];

function formFromData(data: GeneralReviewProgress): FormState {
  return {
    flags: { ...data.flags },
    evaluations: { ...data.evaluations },
    transitions: data.transitions.map((item) => ({ ...item })),
    terms: data.terms.map((item) => ({ ...item })),
    frontMatter: { ...data.frontMatter },
    finalConfirmation: data.finalConfirmation,
  };
}

function inputFromForm(form: FormState, mode: 'autosave' | 'manual'): GeneralReviewInput {
  return {
    flags: form.flags,
    evaluations: form.evaluations,
    transitions: form.transitions,
    terms: form.terms,
    front_matter: form.frontMatter,
    final_confirmation: form.finalConfirmation,
    save_mode: mode,
  };
}

export function GeneralReviewStage({ workspace, onWorkspaceChange, onStageChange, onPersisted }: Props) {
  const [data, setData] = useState<GeneralReviewProgress | null>(null);
  const [form, setForm] = useState<FormState | null>(null);
  const [mode, setMode] = useState<ReviewMode>('coherence');
  const [status, setStatus] = useState<'saved' | 'saving' | 'error'>('saved');
  const [error, setError] = useState('');
  const [reading, setReading] = useState<GeneralReviewReading | null>(null);
  const [readingOpen, setReadingOpen] = useState(false);
  const [readingBusy, setReadingBusy] = useState(false);
  const [issueType, setIssueType] = useState<GeneralReviewIssueType>('coherence');
  const [issuePriority, setIssuePriority] = useState<GeneralReviewPriority>('medium');
  const [issueChapterId, setIssueChapterId] = useState('');
  const [issueDescription, setIssueDescription] = useState('');
  const [assistantBusy, setAssistantBusy] = useState(false);
  const [assistantSuggestion, setAssistantSuggestion] = useState('');
  const formRef = useRef<FormState | null>(null);
  const saveTimerRef = useRef<number | null>(null);
  const saveQueueRef = useRef<Promise<void>>(Promise.resolve());

  useEffect(() => {
    let active = true;
    setError('');
    getGeneralReview(workspace.book.id).then((result) => {
      if (!active) return;
      setData(result);
      const next = formFromData(result);
      setForm(next); formRef.current = next;
    }).catch((cause) => setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Revisão Geral.'));
    return () => { active = false; if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current); };
  }, [workspace.book.id]);

  function enqueueSave(saveMode: 'autosave' | 'manual') {
    const current = formRef.current;
    if (!current) return Promise.resolve();
    if (saveTimerRef.current) { window.clearTimeout(saveTimerRef.current); saveTimerRef.current = null; }
    saveQueueRef.current = saveQueueRef.current.then(async () => {
      const payload = inputFromForm(formRef.current ?? current, saveMode);
      setStatus('saving');
      try {
        const result = await saveGeneralReview(workspace.book.id, payload);
        setData(result.generalReview); onWorkspaceChange(result.workspace); setStatus('saved'); setError('');
      } catch (cause) {
        setStatus('error'); setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a Revisão Geral.');
      }
    });
    return saveQueueRef.current;
  }

  function schedule(next: FormState) {
    setForm(next); formRef.current = next; setStatus('saving');
    if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current);
    saveTimerRef.current = window.setTimeout(() => void enqueueSave('autosave'), 1400);
  }

  function patchForm(updater: (current: FormState) => FormState) {
    const current = formRef.current; if (!current) return;
    schedule(updater(current));
  }

  async function openReading() {
    setReadingOpen(true);
    if (reading) return;
    setReadingBusy(true);
    try { setReading(await getGeneralReviewReading(workspace.book.id)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível montar a leitura contínua.'); }
    finally { setReadingBusy(false); }
  }

  function openChapter(chapterId: string) {
    const url = new URL(window.location.href);
    url.searchParams.set('verbum_stage', 'development');
    url.searchParams.set('verbum_chapter', chapterId);
    url.searchParams.set('verbum_chapter_stage', 'revision');
    window.location.href = url.toString();
  }

  async function addIssue(description = issueDescription, type = issueType) {
    if (!description.trim()) return;
    try {
      const result = await createGeneralReviewIssue(workspace.book.id, { type, priority: issuePriority, chapter_id: issueChapterId, description: description.trim() });
      setData(result.generalReview); onWorkspaceChange(result.workspace); setIssueDescription('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível registrar a pendência.'); }
  }

  async function changeIssue(id: string, statusValue: 'pending' | 'resolved') {
    try {
      const result = await updateGeneralReviewIssue(workspace.book.id, id, { status: statusValue });
      setData(result.generalReview); onWorkspaceChange(result.workspace);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível atualizar a pendência.'); }
  }

  async function removeIssue(id: string) {
    if (!window.confirm('Excluir esta pendência da Revisão Geral?')) return;
    try {
      const result = await deleteGeneralReviewIssue(workspace.book.id, id);
      setData(result.generalReview); onWorkspaceChange(result.workspace);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível excluir a pendência.'); }
  }

  async function runAssistant(action: string) {
    setAssistantBusy(true); setAssistantSuggestion(''); setError('');
    try { setAssistantSuggestion((await assistGeneralReview(workspace.book.id, action)).suggestion); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível usar o Assistente de Revisão Geral.'); }
    finally { setAssistantBusy(false); }
  }

  async function acceptAssistant() {
    if (!assistantSuggestion.trim()) return;
    await addIssue(assistantSuggestion, 'editorial');
    setAssistantSuggestion('');
  }

  async function finish() {
    await enqueueSave('manual');
    try {
      const result = await completeGeneralReview(workspace.book.id);
      setData(result.generalReview); onWorkspaceChange(result.workspace); await onPersisted(); onStageChange('versions');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a Revisão Geral.'); }
  }

  const issueCounts = useMemo(() => {
    const base = { repetition: 0, gap: 0, critical: 0 };
    if (!data) return base;
    for (const issue of data.issues) if (issue.status === 'pending') {
      if (issue.type === 'repetition') base.repetition++;
      if (issue.type === 'gap') base.gap++;
      if (issue.priority === 'critical') base.critical++;
    }
    return base;
  }, [data]);

  if (!data || !form) return <section className="verbum-stage-content verbum-general-review-state">{error || 'Carregando Revisão Geral da Obra...'}</section>;

  const directionFields: Array<[string, string, keyof GeneralReviewProgress['evaluations']]> = [
    ['Objetivo Geral', data.direction.generalObjective, 'objective'],
    ['Mensagem Central', data.direction.centralMessage, 'central_message'],
    ['Público-alvo', data.direction.audience, 'audience'],
    ['Transformação desejada', data.direction.transformation, 'transformation'],
    ['Pergunta Central', data.direction.centralQuestion, 'central_question'],
    ['Tese Principal', data.direction.mainThesis, 'main_thesis'],
  ];

  return <>
    <section className="verbum-stage-content verbum-general-review-stage">
      <header className="verbum-general-review-heading">
        <div><span className="verbum-eyebrow">Etapa 5 de 11</span><h2>Revisão Geral da Obra</h2><p>Avalie a coerência, continuidade e unidade editorial de toda a obra.</p></div>
        <div className="verbum-general-review-actions"><span className={`is-${status}`}>{status === 'saving' ? 'Salvando...' : status === 'error' ? 'Erro ao salvar' : 'Salvo'}</span><button type="button" onClick={() => void enqueueSave('manual')}>Salvar agora</button><button type="button" className="verbum-primary-button" onClick={() => void openReading()}>Ler obra completa</button></div>
      </header>

      <div className="verbum-general-review-stats">
        <article><strong>{data.summary.chapters}</strong><span>Capítulos</span></article>
        <article><strong>{data.summary.words.toLocaleString('pt-BR')}</strong><span>Palavras</span></article>
        <article><strong>{data.summary.completedChapters}/{data.summary.chapters}</strong><span>Capítulos revisados</span></article>
        <article><strong>{data.summary.pendingIssues}</strong><span>Pendências gerais</span></article>
        <article><strong>{data.progress}%</strong><span>Revisão Geral</span></article>
      </div>

      <nav className="verbum-general-review-modes">{modes.map((item) => <button type="button" key={item.key} className={mode === item.key ? 'is-active' : ''} onClick={() => setMode(item.key)}>{item.label}</button>)}</nav>
      <p className="verbum-general-review-mode-help">{modes.find((item) => item.key === mode)?.help}</p>
      {data.alteredAfterCompletion && <div className="verbum-general-review-warning">A obra foi alterada depois da Revisão Geral concluída. O snapshot concluído permanece preservado.</div>}
      {error && <p className="verbum-general-review-message is-error">{error}</p>}

      <div className="verbum-general-review-layout">
        <aside className="verbum-general-review-left">
          <section className="verbum-general-review-card"><div className="verbum-general-review-card-head"><h3>Capítulos da obra</h3><span>{data.summary.completedChapters}/{data.summary.chapters}</span></div><div className="verbum-general-review-chapters">{data.chapters.map((chapter) => <button type="button" key={chapter.id} onClick={() => openChapter(chapter.id)}><span>Capítulo {chapter.number}</span><strong>{chapter.title}</strong><small>{chapter.wordCount.toLocaleString('pt-BR')} palavras · {chapter.completed ? '100%' : 'Pendente'}</small>{(chapter.alteredAfterRevision || chapter.changedDuringGeneralReview) && <em>Alterado após revisão</em>}</button>)}</div></section>
          <section className="verbum-general-review-card verbum-general-review-checklist"><div className="verbum-general-review-card-head"><h3>Checklist</h3><strong>{data.progress}%</strong></div><div className="verbum-general-review-progress"><span style={{ width: `${data.progress}%` }} /></div>{data.checklist.map((item) => {
            const manual = !item.automatic && item.key !== 'completed';
            const checked = manual ? Boolean(form.flags[item.key as keyof GeneralReviewFlags]) : item.completed;
            return <label key={item.key} className={checked ? 'is-complete' : ''}><input type="checkbox" checked={checked} disabled={!manual} onChange={(event) => manual && patchForm((current) => ({ ...current, flags: { ...current.flags, [item.key]: event.target.checked } }))} /><span>{item.label}</span></label>;
          })}<label className={form.finalConfirmation ? 'is-complete is-confirmation' : 'is-confirmation'}><input type="checkbox" checked={form.finalConfirmation} onChange={(event) => patchForm((current) => ({ ...current, finalConfirmation: event.target.checked }))} /><span>Confirmo que esta versão geral da obra está pronta.</span></label>{data.summary.pendingCriticalIssues > 0 && <p className="is-critical">Resolva {data.summary.pendingCriticalIssues} pendência(s) crítica(s) para concluir.</p>}</section>
        </aside>

        <main className="verbum-general-review-main">
          <section className="verbum-general-review-card"><div className="verbum-general-review-card-head"><div><h3>Direção Original da Obra</h3><p>Compare o que foi planejado com o resultado final.</p></div></div><div className="verbum-general-review-direction">{directionFields.map(([label, text, key]) => <article key={key}><div><small>{label}</small><p>{text || '—'}</p></div><select value={form.evaluations[key]} onChange={(event) => patchForm((current) => ({ ...current, evaluations: { ...current.evaluations, [key]: event.target.value as GeneralReviewEvaluationStatus } }))}>{evaluationLabels.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}</select></article>)}</div></section>

          <section className="verbum-general-review-card"><div className="verbum-general-review-card-head"><div><h3>Transições entre capítulos</h3><p>Verifique se o leitor é conduzido de um capítulo ao próximo.</p></div></div><div className="verbum-general-review-transitions">{form.transitions.length === 0 ? <p>Não há transições para avaliar.</p> : form.transitions.map((transition) => <article key={transition.key}><strong>{transition.fromTitle} <span>→</span> {transition.toTitle}</strong><select value={transition.status} onChange={(event) => patchForm((current) => ({ ...current, transitions: current.transitions.map((item) => item.key === transition.key ? { ...item, status: event.target.value as GeneralReviewTransition['status'] } : item) }))}><option value="unreviewed">Avaliar...</option><option value="good">Boa</option><option value="needs_work">Precisa melhorar</option><option value="missing">Sem transição</option></select><textarea value={transition.note} onChange={(event) => patchForm((current) => ({ ...current, transitions: current.transitions.map((item) => item.key === transition.key ? { ...item, note: event.target.value } : item) }))} placeholder="Observação sobre a transição..." /></article>)}</div></section>

          <section className="verbum-general-review-card"><div className="verbum-general-review-card-head"><div><h3>Terminologia da obra</h3><p>Registre termos que precisam permanecer consistentes ao longo do livro.</p></div><button type="button" onClick={() => patchForm((current) => ({ ...current, terms: [...current.terms, { id: `new-${Date.now()}`, term: '', note: '' }] }))}>+ Adicionar termo</button></div><div className="verbum-general-review-terms">{form.terms.length === 0 ? <p>Nenhum termo cadastrado.</p> : form.terms.map((term) => <div key={term.id}><input value={term.term} onChange={(event) => patchForm((current) => ({ ...current, terms: current.terms.map((item) => item.id === term.id ? { ...item, term: event.target.value } : item) }))} placeholder="Termo" /><input value={term.note} onChange={(event) => patchForm((current) => ({ ...current, terms: current.terms.map((item) => item.id === term.id ? { ...item, note: event.target.value } : item) }))} placeholder="Observação de uso" /><button type="button" onClick={() => patchForm((current) => ({ ...current, terms: current.terms.filter((item) => item.id !== term.id) }))}>Excluir</button></div>)}</div></section>

          <section className="verbum-general-review-card"><div className="verbum-general-review-card-head"><div><h3>Textos editoriais da obra</h3><p>Crie ou revise os textos que enquadram o conjunto da obra.</p></div></div><div className="verbum-general-review-front-matter">{([['Prefácio', 'preface'], ['Apresentação', 'presentation'], ['Nota do Autor', 'authorNote'], ['Introdução da Obra', 'introduction'], ['Conclusão da Obra', 'conclusion']] as Array<[string, keyof GeneralReviewFrontMatter]>).map(([label, key]) => <label key={key}><span>{label}</span><textarea value={form.frontMatter[key]} onChange={(event) => patchForm((current) => ({ ...current, frontMatter: { ...current.frontMatter, [key]: event.target.value } }))} placeholder={`Escreva ou revise ${label.toLowerCase()}...`} /></label>)}</div></section>
        </main>

        <aside className="verbum-general-review-right">
          <section className="verbum-general-review-card"><div className="verbum-general-review-card-head"><h3>Pendências da Revisão Geral</h3><span>{data.summary.pendingIssues} abertas</span></div><div className="verbum-general-review-quick"><button type="button" onClick={() => setIssueType('repetition')}>Repetições {issueCounts.repetition}</button><button type="button" onClick={() => setIssueType('gap')}>Lacunas {issueCounts.gap}</button><button type="button" className={issueCounts.critical ? 'is-critical' : ''} onClick={() => setIssuePriority('critical')}>Críticas {issueCounts.critical}</button></div><div className="verbum-general-review-new-issue"><select value={issueType} onChange={(event) => setIssueType(event.target.value as GeneralReviewIssueType)}>{data.issueTypes.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><select value={issuePriority} onChange={(event) => setIssuePriority(event.target.value as GeneralReviewPriority)}>{data.priorities.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><select value={issueChapterId} onChange={(event) => setIssueChapterId(event.target.value)}><option value="">Toda a obra</option>{data.chapters.map((chapter) => <option key={chapter.id} value={chapter.id}>Cap. {chapter.number} — {chapter.title}</option>)}</select><textarea value={issueDescription} onChange={(event) => setIssueDescription(event.target.value)} placeholder="Descreva a pendência..." /><button type="button" onClick={() => void addIssue()}>+ Registrar pendência</button></div><div className="verbum-general-review-issues">{data.issues.length === 0 ? <p>Nenhuma pendência registrada.</p> : data.issues.map((issue) => <article key={issue.id} className={`${issue.status === 'resolved' ? 'is-resolved' : ''} priority-${issue.priority}`}><div><small>{issue.typeLabel}</small><span>{issue.priorityLabel}</span></div><p>{issue.description}</p>{issue.chapterId && <button type="button" onClick={() => openChapter(issue.chapterId)}>Abrir capítulo</button>}<footer><button type="button" onClick={() => void changeIssue(issue.id, issue.status === 'resolved' ? 'pending' : 'resolved')}>{issue.status === 'resolved' ? 'Reabrir' : '✓ Resolver'}</button><button type="button" onClick={() => void removeIssue(issue.id)}>Excluir</button></footer></article>)}</div></section>

          <section className="verbum-general-review-card verbum-general-review-assistant"><h3>Assistente de Revisão Geral</h3><p>Analisa resumos estruturados dos capítulos. Não altera a obra e não inventa fontes.</p><div>{[['coherence','Analisar coerência global'],['repetitions','Identificar repetições'],['gaps','Identificar lacunas'],['progression','Avaliar progressão'],['transitions','Analisar transições'],['objective','Comparar com objetivo'],['thesis','Comparar com tese'],['language','Uniformidade de linguagem']].map(([key, label]) => <button type="button" key={key} disabled={assistantBusy} onClick={() => void runAssistant(key)}>{label}</button>)}</div>{assistantBusy && <small>Gerando análise...</small>}{assistantSuggestion && <div className="verbum-general-review-assistant-result"><textarea value={assistantSuggestion} onChange={(event) => setAssistantSuggestion(event.target.value)} /><div><button type="button" onClick={() => void acceptAssistant()}>Aceitar</button><button type="button" onClick={() => setAssistantSuggestion('')}>Descartar</button></div><small>Você pode editar a sugestão antes de aceitar. Ao aceitar, ela vira uma pendência editorial.</small></div>}</section>

          <section className="verbum-general-review-card"><h3>Snapshots da obra</h3>{data.snapshots.length === 0 ? <p>O snapshot final será criado ao concluir a Revisão Geral.</p> : data.snapshots.slice(0, 4).map((snapshot) => <div className="verbum-general-review-snapshot" key={snapshot.id}><strong>{new Date(snapshot.savedAt).toLocaleString('pt-BR')}</strong><span>{snapshot.chapterCount} capítulos · {snapshot.wordCount.toLocaleString('pt-BR')} palavras</span></div>)}</section>
        </aside>
      </div>

      {readingOpen && <div className="verbum-general-review-reading-overlay" role="dialog" aria-modal="true"><div className="verbum-general-review-reading"><header><div><h3>Leitura contínua da obra</h3><p>Leia os capítulos em sequência sem sair da Revisão Geral.</p></div><button type="button" onClick={() => setReadingOpen(false)}>Fechar</button></header>{readingBusy ? <p>Montando a obra completa...</p> : reading ? <div className="verbum-general-review-reading-grid"><nav>{reading.outline.map((item, index) => item.type === 'part' ? <strong key={`part-${index}`}>{item.title}</strong> : item.type === 'chapter' && item.chapter ? <a key={item.chapter.id} href={`#general-reading-${item.chapter.id}`}>Capítulo {item.chapter.number} — {item.chapter.title}</a> : item.title ? <span key={`sub-${index}`}>{item.title}</span> : null)}</nav><main>{reading.chapters.map((chapter) => <article key={chapter.id} id={`general-reading-${chapter.id}`}><span>Capítulo {chapter.number}</span><h2>{chapter.title}</h2><div dangerouslySetInnerHTML={{ __html: chapter.content }} /></article>)}</main></div> : <p>Não foi possível carregar a leitura contínua.</p>}</div></div>}
    </section>
    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('development')} onBackToLibrary={() => undefined} continueDisabled={!data.ready || data.completed} continueLabel={data.completed ? 'Etapa concluída ✓' : 'Concluir Revisão Geral ›'} onContinue={() => void finish()} />
  </>;
}
