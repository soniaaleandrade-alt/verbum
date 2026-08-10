import { useEffect, useMemo, useRef, useState } from 'react';
import {
  assistChapterRevision,
  completeChapterRevision,
  createChapterRevisionIssue,
  deleteChapterRevisionIssue,
  getChapterRevision,
  saveChapterRevision,
  updateChapterRevisionIssue,
} from '../services/library-service';
import type {
  ChapterRevisionFlags,
  ChapterRevisionIssueType,
  ChapterRevisionProgress,
  ChapterWritingSection,
  DevelopmentChapter,
  WorkDevelopmentProgress,
} from '../types/verbum';

type Props = {
  bookId: string;
  chapter: DevelopmentChapter;
  chapters: DevelopmentChapter[];
  onOpenChapter: (id: string) => void;
  onChapterChange: (chapter: DevelopmentChapter) => void;
  onDevelopmentChange: (development: WorkDevelopmentProgress) => void;
};

type EditorKey = 'introduction' | 'conclusion' | `section:${string}`;
type Draft = { introduction: string; sections: ChapterWritingSection[]; conclusion: string };
type ReviewMode = 'content' | 'structure' | 'clarity' | 'language';
type RevisionMeta = {
  flags: ChapterRevisionFlags;
  verifiedSourceIds: string[];
  dismissedSourceIds: string[];
  resolvedNoteIds: string[];
  resolvedCommentIds: string[];
};

const reviewModes: Array<{ key: ReviewMode; label: string; description: string }> = [
  { key: 'content', label: 'Conteúdo', description: 'Objetivo, pergunta central, tese, argumentos, lacunas e repetições.' },
  { key: 'structure', label: 'Estrutural', description: 'Introdução, sequência lógica, tópicos, transições, equilíbrio e conclusão.' },
  { key: 'clarity', label: 'Clareza e Estilo', description: 'Fluidez, redundâncias, vocabulário, tom e trechos confusos.' },
  { key: 'language', label: 'Linguística', description: 'Ortografia, gramática, concordância, pontuação e digitação.' },
];

function RichEditor({ html, editorKey, onFocus, onChange }: { html: string; editorKey: EditorKey; onFocus: (key: EditorKey, node: HTMLDivElement) => void; onChange: (key: EditorKey, html: string) => void }) {
  const ref = useRef<HTMLDivElement | null>(null);
  const initialized = useRef(false);
  useEffect(() => {
    if (!ref.current || initialized.current) return;
    ref.current.innerHTML = html;
    initialized.current = true;
  }, [html]);
  return <div ref={ref} className="verbum-revision-editor" contentEditable suppressContentEditableWarning onFocus={() => ref.current && onFocus(editorKey, ref.current)} onInput={() => ref.current && onChange(editorKey, ref.current.innerHTML)} />;
}

function plainText(html: string) {
  const node = document.createElement('div');
  node.innerHTML = html;
  return (node.textContent || '').replace(/\s+/g, ' ').trim();
}

export function ChapterRevisionStage({ bookId, chapter, chapters, onOpenChapter, onChapterChange, onDevelopmentChange }: Props) {
  const [data, setData] = useState<ChapterRevisionProgress | null>(null);
  const [flags, setFlags] = useState<ChapterRevisionFlags>({});
  const [verifiedSourceIds, setVerifiedSourceIds] = useState<string[]>([]);
  const [dismissedSourceIds, setDismissedSourceIds] = useState<string[]>([]);
  const [resolvedNoteIds, setResolvedNoteIds] = useState<string[]>([]);
  const [resolvedCommentIds, setResolvedCommentIds] = useState<string[]>([]);
  const [mode, setMode] = useState<ReviewMode>('content');
  const [status, setStatus] = useState<'saved' | 'saving' | 'error'>('saved');
  const [error, setError] = useState('');
  const [assistantBusy, setAssistantBusy] = useState(false);
  const [assistantSuggestion, setAssistantSuggestion] = useState('');
  const [issueType, setIssueType] = useState<ChapterRevisionIssueType>('clarity');
  const [issueDescription, setIssueDescription] = useState('');
  const draftRef = useRef<Draft>({ introduction: '', sections: [], conclusion: '' });
  const metaRef = useRef<RevisionMeta>({ flags: {}, verifiedSourceIds: [], dismissedSourceIds: [], resolvedNoteIds: [], resolvedCommentIds: [] });
  const activeEditorRef = useRef<HTMLDivElement | null>(null);
  const activeKeyRef = useRef<EditorKey>('introduction');
  const saveTimerRef = useRef<number | null>(null);
  const savingRef = useRef(false);
  const [tick, setTick] = useState(0);

  useEffect(() => {
    let active = true;
    setError('');
    getChapterRevision(bookId, chapter.id).then((result) => {
      if (!active) return;
      draftRef.current = { introduction: result.introduction, sections: result.sections, conclusion: result.conclusion };
      const meta: RevisionMeta = {
        flags: result.flags,
        verifiedSourceIds: result.verifiedSourceIds,
        dismissedSourceIds: result.dismissedSourceIds,
        resolvedNoteIds: result.resolvedNoteIds,
        resolvedCommentIds: result.resolvedCommentIds,
      };
      metaRef.current = meta;
      setFlags(meta.flags);
      setVerifiedSourceIds(meta.verifiedSourceIds);
      setDismissedSourceIds(meta.dismissedSourceIds);
      setResolvedNoteIds(meta.resolvedNoteIds);
      setResolvedCommentIds(meta.resolvedCommentIds);
      setData(result);
      setTick((value) => value + 1);
    }).catch((cause) => setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Revisão.'));
    return () => {
      active = false;
      if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current);
    };
  }, [bookId, chapter.id]);

  useEffect(() => { metaRef.current.flags = flags; }, [flags]);
  useEffect(() => { metaRef.current.verifiedSourceIds = verifiedSourceIds; }, [verifiedSourceIds]);
  useEffect(() => { metaRef.current.dismissedSourceIds = dismissedSourceIds; }, [dismissedSourceIds]);
  useEffect(() => { metaRef.current.resolvedNoteIds = resolvedNoteIds; }, [resolvedNoteIds]);
  useEffect(() => { metaRef.current.resolvedCommentIds = resolvedCommentIds; }, [resolvedCommentIds]);

  function payload(saveMode: 'autosave' | 'manual') {
    const meta = metaRef.current;
    return {
      introduction: draftRef.current.introduction,
      sections: draftRef.current.sections,
      conclusion: draftRef.current.conclusion,
      flags: meta.flags,
      verified_source_ids: meta.verifiedSourceIds,
      dismissed_source_ids: meta.dismissedSourceIds,
      resolved_note_ids: meta.resolvedNoteIds,
      resolved_comment_ids: meta.resolvedCommentIds,
      save_mode: saveMode,
    } as const;
  }

  async function persist(saveMode: 'autosave' | 'manual') {
    if (!data || savingRef.current) return;
    if (saveTimerRef.current) { window.clearTimeout(saveTimerRef.current); saveTimerRef.current = null; }
    savingRef.current = true; setStatus('saving');
    try {
      const result = await saveChapterRevision(bookId, chapter.id, payload(saveMode));
      setData(result.revision); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage); setStatus('saved'); setError('');
    } catch (cause) { setStatus('error'); setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a Revisão.'); }
    finally { savingRef.current = false; }
  }

  function scheduleSave() {
    setStatus('saving');
    if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current);
    saveTimerRef.current = window.setTimeout(() => void persist('autosave'), 1600);
  }

  function updateDraft(key: EditorKey, html: string) {
    if (key === 'introduction') draftRef.current.introduction = html;
    else if (key === 'conclusion') draftRef.current.conclusion = html;
    else {
      const id = key.slice('section:'.length);
      draftRef.current.sections = draftRef.current.sections.map((section) => section.id === id ? { ...section, content: html } : section);
    }
    setTick((value) => value + 1); scheduleSave();
  }

  function updateSectionTitle(id: string, title: string) {
    draftRef.current.sections = draftRef.current.sections.map((section) => section.id === id ? { ...section, title } : section);
    setTick((value) => value + 1); scheduleSave();
  }

  function setActive(key: EditorKey, node: HTMLDivElement) { activeKeyRef.current = key; activeEditorRef.current = node; }
  function command(name: string, value?: string) {
    const editor = activeEditorRef.current; if (!editor) return; editor.focus(); document.execCommand(name, false, value); updateDraft(activeKeyRef.current, editor.innerHTML);
  }
  function insertSuggestion() {
    const editor = activeEditorRef.current; if (!editor || !assistantSuggestion.trim()) return;
    editor.focus(); document.execCommand('insertHTML', false, `<p>${assistantSuggestion.replace(/[&<>]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[char] || char)).replace(/\n/g, '<br>')}</p>`);
    updateDraft(activeKeyRef.current, editor.innerHTML); setAssistantSuggestion('');
  }

  function toggleFlag(key: keyof ChapterRevisionFlags, checked: boolean) {
    const next = { ...metaRef.current.flags, [key]: checked };
    metaRef.current.flags = next;
    setFlags(next);
    scheduleSave();
  }
  function toggleId(current: string[], id: string, checked: boolean, setter: (ids: string[]) => void) {
    setter(checked ? Array.from(new Set([...current, id])) : current.filter((item) => item !== id)); scheduleSave();
  }

  async function addIssue() {
    const description = issueDescription.trim(); if (!description) return;
    try {
      const selected = window.getSelection()?.toString().trim() || '';
      const result = await createChapterRevisionIssue(bookId, chapter.id, { type: issueType, description, excerpt: selected });
      setData(result.revision); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage); setIssueDescription('');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível registrar a pendência.'); }
  }

  async function changeIssue(id: string, statusValue: 'pending' | 'resolved') {
    try {
      const result = await updateChapterRevisionIssue(bookId, chapter.id, id, { status: statusValue });
      setData(result.revision); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível atualizar a pendência.'); }
  }

  async function removeIssue(id: string) {
    if (!window.confirm('Excluir esta pendência da Revisão?')) return;
    try {
      const result = await deleteChapterRevisionIssue(bookId, chapter.id, id);
      setData(result.revision); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível excluir a pendência.'); }
  }

  async function convertNoteToIssue(id: string, text: string, kind: 'note' | 'comment') {
    try {
      const result = await createChapterRevisionIssue(bookId, chapter.id, { type: 'other', description: text });
      setData(result.revision); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
      if (kind === 'note') toggleId(resolvedNoteIds, id, true, setResolvedNoteIds); else toggleId(resolvedCommentIds, id, true, setResolvedCommentIds);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível converter a anotação em pendência.'); }
  }

  async function runAssistant(action: string) {
    if (assistantBusy) return;
    setAssistantBusy(true); setAssistantSuggestion(''); setError('');
    try {
      const text = window.getSelection()?.toString() || plainText(activeEditorRef.current?.innerHTML || '');
      const result = await assistChapterRevision(bookId, chapter.id, action, text);
      setAssistantSuggestion(result.suggestion);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível usar o Assistente de Revisão.'); }
    finally { setAssistantBusy(false); }
  }

  async function finish() {
    if (!data || savingRef.current) return;
    await persist('manual');
    try {
      const result = await completeChapterRevision(bookId, chapter.id);
      setData(result.revision); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage); setStatus('saved');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a Revisão.'); }
  }

  const sections = useMemo(() => draftRef.current.sections, [tick]);
  if (!data) return <div className={`verbum-revision-state${error ? ' is-error' : ''}`}>{error || 'Carregando Revisão do Capítulo...'}</div>;

  return <div className="verbum-revision-shell">
    <div className="verbum-revision-topbar">
      <div className="verbum-revision-modes">{reviewModes.map((item) => <button type="button" key={item.key} className={mode === item.key ? 'is-active' : ''} onClick={() => setMode(item.key)}>{item.label}</button>)}</div>
      <span className={`verbum-revision-save is-${status}`}>{status === 'saving' ? 'Salvando...' : status === 'error' ? 'Erro ao salvar' : 'Salvo'}</span>
      <button type="button" onClick={() => void persist('manual')}>Salvar agora</button>
    </div>
    <p className="verbum-revision-mode-help">{reviewModes.find((item) => item.key === mode)?.description}</p>
    {data.alteredAfterCompletion && <div className="verbum-revision-warning">Este capítulo foi alterado após a Revisão concluída. A versão concluída permanece preservada no histórico.</div>}

    <div className="verbum-revision-layout">
      <aside className="verbum-revision-left">
        <section><span className="verbum-revision-label">Capítulos da obra</span><div className="verbum-revision-chapters">{chapters.map((item) => <button type="button" key={item.id} className={item.id === chapter.id ? 'is-active' : ''} onClick={() => item.id !== chapter.id && onOpenChapter(item.id)}><small>Capítulo {item.number}</small><strong>{item.title}</strong><span>{item.completed ? 'Concluído' : item.stageLabel} · {item.progress}%</span></button>)}</div></section>
        <section className="verbum-revision-checklist"><div className="verbum-revision-progress-head"><span className="verbum-revision-label">Progresso da Revisão</span><strong>{data.progress}%</strong></div><div className="verbum-revision-progress"><span style={{ width: `${data.progress}%` }} /></div>{data.checklist.map((item) => {
          const manual = !item.automatic && item.key !== 'completed';
          const checked = manual ? Boolean(flags[item.key as keyof ChapterRevisionFlags]) : item.completed;
          return <label key={item.key} className={checked ? 'is-complete' : ''}><input type="checkbox" checked={checked} disabled={!manual} onChange={(event) => manual && toggleFlag(item.key as keyof ChapterRevisionFlags, event.target.checked)} /><span>{item.label}</span></label>;
        })}<button type="button" className="verbum-primary-button" disabled={!data.ready || data.completed} onClick={() => void finish()}>{data.completed ? 'Revisão concluída ✓' : 'Concluir Revisão'}</button></section>
      </aside>

      <main className="verbum-revision-center">
        <div className="verbum-revision-toolbar"><button onMouseDown={(event) => event.preventDefault()} onClick={() => command('bold')}><b>B</b></button><button onMouseDown={(event) => event.preventDefault()} onClick={() => command('italic')}><i>I</i></button><button onMouseDown={(event) => event.preventDefault()} onClick={() => command('underline')}><u>U</u></button><button onMouseDown={(event) => event.preventDefault()} onClick={() => command('formatBlock', 'blockquote')}>Citação</button><button onMouseDown={(event) => event.preventDefault()} onClick={() => command('undo')}>↶</button><button onMouseDown={(event) => event.preventDefault()} onClick={() => command('redo')}>↷</button></div>
        <article className="verbum-revision-paper"><header><span>Texto completo do capítulo</span><h2>{chapter.title}</h2><p>{data.wordCount.toLocaleString('pt-BR')} palavras · revise diretamente a versão atual.</p></header>
          <section><h3>Introdução</h3><RichEditor html={data.introduction} editorKey="introduction" onFocus={setActive} onChange={updateDraft} /></section>
          <section><h3>Desenvolvimento</h3>{sections.map((section) => <div className="verbum-revision-section" key={section.id}><input className="verbum-revision-section-title" value={section.title} onChange={(event) => updateSectionTitle(section.id, event.target.value)} /><RichEditor html={section.content} editorKey={`section:${section.id}`} onFocus={setActive} onChange={updateDraft} /></div>)}</section>
          <section><h3>Conclusão</h3><RichEditor html={data.conclusion} editorKey="conclusion" onFocus={setActive} onChange={updateDraft} /></section>
        </article>
      </main>

      <aside className="verbum-revision-right">
        <section className="verbum-revision-direction"><span className="verbum-revision-label">Direção original</span><div><small>Objetivo</small><p>{data.preparation.objective || '—'}</p></div><div><small>Pergunta Central</small><p>{data.preparation.centralQuestion || '—'}</p></div><div><small>Tese</small><p>{data.preparation.thesis || '—'}</p></div><div><small>Mensagem Principal</small><p>{data.preparation.mainMessage || '—'}</p></div></section>
        <section><span className="verbum-revision-label">Estrutura planejada × escrita</span><div className="verbum-revision-structure"><div><b>Planejado</b>{data.preparation.structureItems.map((item) => <span key={item.id}>{item.order}. {item.text}</span>)}</div><div><b>Redação</b>{data.writtenStructure.map((item) => <span key={item.id}>{item.order}. {item.title}</span>)}</div></div></section>
        <section><span className="verbum-revision-label">Fontes e Citações</span><div className="verbum-revision-sources">{data.usedSources.length === 0 ? <p>Nenhuma fonte foi marcada como utilizada.</p> : data.usedSources.map((source) => <label key={source.id}><input type="checkbox" checked={verifiedSourceIds.includes(source.id)} onChange={(event) => toggleId(verifiedSourceIds, source.id, event.target.checked, setVerifiedSourceIds)} /><span><strong>{source.reference || source.title || source.author || 'Fonte'}</strong><small>{verifiedSourceIds.includes(source.id) ? 'Referência confirmada ✓' : 'Verificar referência'}</small></span></label>)}</div>{data.unusedSelectedSources.length > 0 && <div className="verbum-revision-unused"><b>Selecionadas, mas não utilizadas</b>{data.unusedSelectedSources.map((source) => <div key={source.id}><span>{source.reference || source.title || source.author || 'Fonte'}</span><button type="button" onClick={() => toggleId(dismissedSourceIds, source.id, true, setDismissedSourceIds)}>Dispensar nesta versão</button></div>)}</div>}</section>
        <section className="verbum-revision-issues"><div className="verbum-revision-section-head"><span className="verbum-revision-label">Pendências da Revisão</span><strong>{data.pendingIssueCount} pendentes</strong></div><div className="verbum-revision-new-issue"><select value={issueType} onChange={(event) => setIssueType(event.target.value as ChapterRevisionIssueType)}>{data.issueTypes.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><textarea value={issueDescription} onChange={(event) => setIssueDescription(event.target.value)} placeholder="Descreva a pendência..." /><button type="button" onClick={() => void addIssue()}>+ Registrar pendência</button></div>{data.issues.map((issue) => <article key={issue.id} className={issue.status === 'resolved' ? 'is-resolved' : ''}><small>{issue.typeLabel}</small><p>{issue.description}</p>{issue.excerpt && <blockquote>{issue.excerpt}</blockquote>}<div><button type="button" onClick={() => void changeIssue(issue.id, issue.status === 'resolved' ? 'pending' : 'resolved')}>{issue.status === 'resolved' ? 'Reabrir' : '✓ Resolver'}</button><button type="button" onClick={() => void removeIssue(issue.id)}>Excluir</button></div></article>)}</section>
        <section><span className="verbum-revision-label">Notas e comentários da Redação</span>{[...data.notes, ...data.comments].map((item) => <article className={`verbum-revision-note${item.resolved ? ' is-resolved' : ''}`} key={`${item.kind}-${item.id}`}><small>{item.kind === 'note' ? 'Nota' : 'Comentário'}</small><p>{item.text}</p><div><button type="button" onClick={() => item.kind === 'note' ? toggleId(resolvedNoteIds, item.id, !resolvedNoteIds.includes(item.id), setResolvedNoteIds) : toggleId(resolvedCommentIds, item.id, !resolvedCommentIds.includes(item.id), setResolvedCommentIds)}>{item.resolved ? 'Manter' : 'Resolver'}</button>{!item.resolved && <button type="button" onClick={() => void convertNoteToIssue(item.id, item.text, item.kind)}>Converter em pendência</button>}</div></article>)}</section>
        <section className="verbum-revision-assistant"><span className="verbum-revision-label">Assistente de Revisão</span><p>Analisa somente o texto e as fontes registradas. Não substitui revisão profissional ou doutrinal especializada.</p><div>{[['clarity','Revisar clareza'],['repetition','Identificar repetições'],['simplify','Sugerir simplificação'],['coherence','Verificar coerência'],['transitions','Analisar transições'],['thesis','Comparar com a tese'],['gaps','Identificar lacunas'],['doctrine','Pontos para conferência doutrinal']].map(([key, label]) => <button type="button" key={key} disabled={assistantBusy} onClick={() => void runAssistant(key)}>{label}</button>)}</div>{assistantBusy && <small>Gerando proposta...</small>}{assistantSuggestion && <div className="verbum-revision-assistant-result"><textarea value={assistantSuggestion} onChange={(event) => setAssistantSuggestion(event.target.value)} /><div><button type="button" onClick={insertSuggestion}>Aceitar</button><button type="button" onClick={() => setAssistantSuggestion('')}>Descartar</button></div></div>}</section>
        <section className="verbum-revision-versions"><span className="verbum-revision-label">Versões da Revisão</span>{data.versions.length === 0 ? <p>As versões aparecerão após salvamentos manuais e conclusão.</p> : data.versions.slice(0, 5).map((version) => <div key={version.id}><span>{new Date(version.savedAt).toLocaleString('pt-BR')}</span><small>{version.wordCount.toLocaleString('pt-BR')} palavras · {version.kind}</small></div>)}</section>
      </aside>
    </div>
    {error && <p className="verbum-revision-message is-error">{error}</p>}
  </div>;
}
