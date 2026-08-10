import { useEffect, useMemo, useRef, useState } from 'react';
import {
  assistChapterWriting,
  completeChapterWriting,
  getChapterWriting,
  saveChapterWriting,
} from '../services/library-service';
import type {
  ChapterWritingFlags,
  ChapterWritingNote,
  ChapterWritingProgress,
  ChapterWritingSection,
  ChapterWritingSource,
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

type Draft = { introduction: string; sections: ChapterWritingSection[]; conclusion: string };
type EditorKey = 'introduction' | 'conclusion' | `section:${string}`;
type WritingMeta = {
  wordGoal: number;
  notes: ChapterWritingNote[];
  comments: ChapterWritingNote[];
  flags: ChapterWritingFlags;
  usedSourceIds: string[];
  usedIdeaIds: string[];
};

type RichEditorProps = {
  html: string;
  editorKey: EditorKey;
  placeholder: string;
  onFocus: (key: EditorKey, node: HTMLDivElement) => void;
  onChange: (key: EditorKey, html: string) => void;
};

function RichEditor({ html, editorKey, placeholder, onFocus, onChange }: RichEditorProps) {
  const ref = useRef<HTMLDivElement | null>(null);
  const initialized = useRef(false);
  useEffect(() => {
    if (!ref.current || initialized.current) return;
    ref.current.innerHTML = html;
    initialized.current = true;
  }, [html]);
  return <div
    ref={ref}
    className="verbum-writing-editor"
    contentEditable
    suppressContentEditableWarning
    data-placeholder={placeholder}
    onFocus={() => ref.current && onFocus(editorKey, ref.current)}
    onInput={() => ref.current && onChange(editorKey, ref.current.innerHTML)}
  />;
}

function plainText(html: string) {
  const node = document.createElement('div');
  node.innerHTML = html;
  return (node.textContent || '').replace(/\s+/g, ' ').trim();
}

function countDraft(draft: Draft) {
  const text = [plainText(draft.introduction), ...draft.sections.map((section) => plainText(section.content)), plainText(draft.conclusion)].filter(Boolean).join(' ');
  return { words: text ? text.split(/\s+/).filter(Boolean).length : 0, characters: text.length };
}

function escapeHtml(value: string) {
  return value.replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char] || char));
}

function formatTime(seconds: number) {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  if (hours > 0) return `${hours}h ${minutes}min`;
  return `${minutes}min`;
}

export function ChapterWritingStage({ bookId, chapter, chapters, onOpenChapter, onChapterChange, onDevelopmentChange }: Props) {
  const [data, setData] = useState<ChapterWritingProgress | null>(null);
  const [sections, setSections] = useState<ChapterWritingSection[]>([]);
  const [wordGoal, setWordGoal] = useState(0);
  const [notes, setNotes] = useState<ChapterWritingNote[]>([]);
  const [comments, setComments] = useState<ChapterWritingNote[]>([]);
  const [flags, setFlags] = useState<ChapterWritingFlags>({});
  const [usedSourceIds, setUsedSourceIds] = useState<string[]>([]);
  const [usedIdeaIds, setUsedIdeaIds] = useState<string[]>([]);
  const [status, setStatus] = useState<'saved' | 'saving' | 'error'>('saved');
  const [error, setError] = useState('');
  const [focusMode, setFocusMode] = useState(false);
  const [leftOpen, setLeftOpen] = useState(true);
  const [rightOpen, setRightOpen] = useState(true);
  const [tick, setTick] = useState(0);
  const [assistantBusy, setAssistantBusy] = useState(false);
  const [assistantSuggestion, setAssistantSuggestion] = useState('');
  const draftRef = useRef<Draft>({ introduction: '', sections: [], conclusion: '' });
  const metaRef = useRef<WritingMeta>({ wordGoal: 0, notes: [], comments: [], flags: {}, usedSourceIds: [], usedIdeaIds: [] });
  const loadedRef = useRef(false);
  const activeEditorRef = useRef<HTMLDivElement | null>(null);
  const activeKeyRef = useRef<EditorKey>('introduction');
  const saveTimerRef = useRef<number | null>(null);
  const savingRef = useRef(false);
  const lastSessionSaveRef = useRef(Date.now());

  useEffect(() => {
    let active = true;
    loadedRef.current = false;
    setError('');
    getChapterWriting(bookId, chapter.id).then((result) => {
      if (!active) return;
      const sourceIds = result.sources.filter((source) => source.used).map((source) => source.id);
      draftRef.current = { introduction: result.introduction, sections: result.sections, conclusion: result.conclusion };
      metaRef.current = {
        wordGoal: result.wordGoal,
        notes: result.notes,
        comments: result.comments,
        flags: result.flags,
        usedSourceIds: sourceIds,
        usedIdeaIds: result.usedIdeaIds,
      };
      setSections(result.sections);
      setWordGoal(result.wordGoal);
      setNotes(result.notes);
      setComments(result.comments);
      setFlags(result.flags);
      setUsedSourceIds(sourceIds);
      setUsedIdeaIds(result.usedIdeaIds);
      setData(result);
      setTick((value) => value + 1);
      lastSessionSaveRef.current = Date.now();
      loadedRef.current = true;
    }).catch((cause) => setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Redação.'));
    return () => {
      active = false;
      if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current);
    };
  }, [bookId, chapter.id]);

  useEffect(() => {
    document.body.classList.toggle('verbum-writing-focus-mode', focusMode);
    return () => document.body.classList.remove('verbum-writing-focus-mode');
  }, [focusMode]);

  useEffect(() => {
    const timer = window.setInterval(() => setTick((value) => value + 1), 30000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    const handleBeforeUnload = () => flushKeepalive();
    window.addEventListener('beforeunload', handleBeforeUnload);
    return () => {
      window.removeEventListener('beforeunload', handleBeforeUnload);
      flushKeepalive();
    };
  }, [bookId, chapter.id]);

  function updateDraft(key: EditorKey, html: string) {
    if (key === 'introduction') draftRef.current.introduction = html;
    else if (key === 'conclusion') draftRef.current.conclusion = html;
    else {
      const id = key.slice('section:'.length);
      draftRef.current.sections = draftRef.current.sections.map((section) => section.id === id ? { ...section, content: html } : section);
    }
    setTick((value) => value + 1);
    scheduleSave();
  }

  function scheduleSave() {
    setStatus('saving');
    if (saveTimerRef.current) window.clearTimeout(saveTimerRef.current);
    saveTimerRef.current = window.setTimeout(() => void persist('autosave'), 1800);
  }

  function inputPayload(mode: 'autosave' | 'manual') {
    const now = Date.now();
    const sessionSeconds = Math.max(0, Math.min(3600, Math.floor((now - lastSessionSaveRef.current) / 1000)));
    const meta = metaRef.current;
    return {
      introduction: draftRef.current.introduction,
      sections: draftRef.current.sections,
      conclusion: draftRef.current.conclusion,
      word_goal: meta.wordGoal,
      notes: meta.notes,
      comments: meta.comments,
      flags: meta.flags,
      used_source_ids: meta.usedSourceIds,
      used_idea_ids: meta.usedIdeaIds,
      session_seconds: sessionSeconds,
      save_mode: mode,
    } as const;
  }

  function flushKeepalive() {
    if (!loadedRef.current) return;
    const config = window.VerbumStudioConfig;
    if (!config?.apiRoot) return;
    try {
      void fetch(`${config.apiRoot}/books/${bookId}/chapters/${chapter.id}/writing`, {
        method: 'PATCH',
        credentials: 'same-origin',
        keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce || '' },
        body: JSON.stringify(inputPayload('autosave')),
      }).catch(() => undefined);
    } catch {
      // Best-effort flush. Normal autosave remains the primary persistence path.
    }
  }

  async function persist(mode: 'autosave' | 'manual'): Promise<boolean> {
    if (!data || savingRef.current) return false;
    if (saveTimerRef.current) { window.clearTimeout(saveTimerRef.current); saveTimerRef.current = null; }
    savingRef.current = true;
    setStatus('saving');
    try {
      const result = await saveChapterWriting(bookId, chapter.id, inputPayload(mode));
      lastSessionSaveRef.current = Date.now();
      setData(result.writing);
      onChapterChange(result.chapter);
      onDevelopmentChange(result.developmentStage);
      setStatus('saved');
      setError('');
      return true;
    } catch (cause) {
      setStatus('error');
      setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a Redação.');
      return false;
    } finally {
      savingRef.current = false;
    }
  }

  function setActive(key: EditorKey, node: HTMLDivElement) {
    activeKeyRef.current = key;
    activeEditorRef.current = node;
    setTick((value) => value + 1);
  }

  function command(name: string, value?: string) {
    const editor = activeEditorRef.current;
    if (!editor) return;
    editor.focus();
    document.execCommand(name, false, value);
    updateDraft(activeKeyRef.current, editor.innerHTML);
  }

  function insertHtml(html: string) {
    const editor = activeEditorRef.current;
    if (!editor) return;
    editor.focus();
    document.execCommand('insertHTML', false, html);
    updateDraft(activeKeyRef.current, editor.innerHTML);
  }

  function addSection() {
    const next: ChapterWritingSection = { id: `new-${Date.now()}`, title: 'Novo tópico', content: '', order: sections.length + 1, sourceStructureItemId: '' };
    const updated = [...sections, next];
    setSections(updated);
    draftRef.current.sections = updated;
    scheduleSave();
  }

  function updateSectionTitle(id: string, title: string) {
    const updated = sections.map((section) => section.id === id ? { ...section, title } : section);
    setSections(updated); draftRef.current.sections = updated; scheduleSave();
  }

  function moveSection(index: number, delta: number) {
    const next = [...sections];
    const target = index + delta;
    if (target < 0 || target >= next.length) return;
    [next[index], next[target]] = [next[target], next[index]];
    const ordered = next.map((section, position) => ({ ...section, order: position + 1 }));
    setSections(ordered); draftRef.current.sections = ordered; scheduleSave();
  }

  function removeSection(id: string) {
    if (sections.length <= 1 || !window.confirm('Remover este tópico da estrutura de Redação? O conteúdo deste tópico será retirado da Redação, mas a Estrutura Inicial da Preparação será preservada.')) return;
    const updated = sections.filter((section) => section.id !== id).map((section, index) => ({ ...section, order: index + 1 }));
    setSections(updated); draftRef.current.sections = updated; scheduleSave();
  }

  function addNote(kind: 'note' | 'comment') {
    const text = window.prompt(kind === 'note' ? 'Digite a nota de produção:' : 'Digite o comentário:');
    if (!text?.trim()) return;
    const item = { id: `new-${Date.now()}`, text: text.trim(), createdAt: new Date().toISOString() };
    if (kind === 'note') {
      const next = [...metaRef.current.notes, item];
      metaRef.current.notes = next;
      setNotes(next);
    } else {
      const next = [...metaRef.current.comments, item];
      metaRef.current.comments = next;
      setComments(next);
    }
    scheduleSave();
  }

  function insertSource(source: ChapterWritingSource) {
    const excerpt = source.excerpt ? `<p>${escapeHtml(source.excerpt)}</p>` : '';
    const reference = source.reference || source.title || source.author;
    insertHtml(`<blockquote class="verbum-writing-inserted-source">${excerpt}${reference ? `<cite>${escapeHtml(reference)}</cite>` : ''}</blockquote><p><br></p>`);
    const next = metaRef.current.usedSourceIds.includes(source.id) ? metaRef.current.usedSourceIds : [...metaRef.current.usedSourceIds, source.id];
    metaRef.current.usedSourceIds = next;
    setUsedSourceIds(next);
    scheduleSave();
  }

  function useIdea(id: string, title: string, description: string) {
    const text = [title, description].filter(Boolean).join(': ');
    const nextNotes = [...metaRef.current.notes, { id: `new-${Date.now()}`, text, createdAt: new Date().toISOString() }];
    const nextIds = metaRef.current.usedIdeaIds.includes(id) ? metaRef.current.usedIdeaIds : [...metaRef.current.usedIdeaIds, id];
    metaRef.current.notes = nextNotes;
    metaRef.current.usedIdeaIds = nextIds;
    setNotes(nextNotes);
    setUsedIdeaIds(nextIds);
    scheduleSave();
  }

  async function runAssistant(action: string) {
    if (assistantBusy) return;
    setAssistantBusy(true); setAssistantSuggestion(''); setError('');
    try {
      const selected = window.getSelection()?.toString() || plainText(activeEditorRef.current?.innerHTML || '');
      const result = await assistChapterWriting(bookId, chapter.id, action, selected);
      setAssistantSuggestion(result.suggestion);
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível usar o Assistente de Escrita.');
    } finally { setAssistantBusy(false); }
  }

  function acceptAssistant() {
    if (!assistantSuggestion.trim()) return;
    insertHtml(`<p>${escapeHtml(assistantSuggestion).replace(/\n/g, '<br>')}</p>`);
    setAssistantSuggestion('');
  }

  async function finish() {
    if (!data || savingRef.current) return;
    const saved = await persist('manual');
    if (!saved) return;
    setStatus('saving');
    try {
      const result = await completeChapterWriting(bookId, chapter.id);
      setData(result.writing); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage); setStatus('saved');
    } catch (cause) {
      setStatus('error'); setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a Redação.');
    }
  }

  const metrics = useMemo(() => countDraft(draftRef.current), [tick, sections]);
  const elapsed = (data?.timeSeconds || 0) + Math.floor((Date.now() - lastSessionSaveRef.current) / 1000);
  const activeSection = activeKeyRef.current.startsWith('section:') ? sections.find((section) => `section:${section.id}` === activeKeyRef.current) : null;
  const relatedSources = data?.sources.filter((source) => activeSection?.sourceStructureItemId && source.structureItemId === activeSection.sourceStructureItemId) || [];
  const goalProgress = wordGoal > 0 ? Math.min(100, Math.round((metrics.words / wordGoal) * 100)) : 0;

  if (!data) return <div className={`verbum-writing-state${error ? ' is-error' : ''}`}>{error || 'Carregando Redação do Capítulo...'}</div>;

  return <div className={`verbum-writing-shell${focusMode ? ' is-focus' : ''}${leftOpen ? '' : ' is-left-closed'}${rightOpen ? '' : ' is-right-closed'}`}>
    <div className="verbum-writing-topbar">
      <div><button type="button" onClick={() => setLeftOpen((value) => !value)}>Capítulos</button><button type="button" onClick={() => setRightOpen((value) => !value)}>Pesquisa</button></div>
      <div className={`verbum-writing-save-state is-${status}`}>{status === 'saving' ? 'Salvando...' : status === 'error' ? 'Erro ao salvar' : 'Salvo'}</div>
      <div><button type="button" onClick={() => void persist('manual')}>Salvar agora</button><button type="button" className={focusMode ? 'is-active' : ''} onClick={() => setFocusMode((value) => !value)}>Modo Foco</button></div>
    </div>

    <div className="verbum-writing-layout">
      <aside className="verbum-writing-chapters">
        <span className="verbum-writing-panel-label">Capítulos da obra</span>
        <div>{chapters.map((item) => <button key={item.id} className={item.id === chapter.id ? 'is-active' : ''} onClick={async () => { if (item.id === chapter.id) return; const saved = await persist('autosave'); if (saved) onOpenChapter(item.id); }}><small>Capítulo {item.number} · {item.stageLabel}</small><strong>{item.title}</strong><span>{item.progress}%</span></button>)}</div>
      </aside>

      <main className="verbum-writing-center">
        <div className="verbum-writing-toolbar" role="toolbar" aria-label="Ferramentas de edição">
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('bold')}><b>B</b></button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('italic')}><i>I</i></button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('underline')}><u>U</u></button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('formatBlock', 'h2')}>Título</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('formatBlock', 'h3')}>Subtítulo</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('insertUnorderedList')}>• Lista</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('insertOrderedList')}>1. Lista</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('formatBlock', 'blockquote')}>Citação</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => { const url = window.prompt('URL da imagem:'); if (url) command('insertImage', url); }}>Imagem</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => insertHtml('<table><tbody><tr><td>Célula 1</td><td>Célula 2</td></tr><tr><td></td><td></td></tr></tbody></table><p><br></p>')}>Tabela</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => addNote('comment')}>Comentário</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => addNote('note')}>Nota</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('undo')}>↶</button>
          <button onMouseDown={(event) => event.preventDefault()} onClick={() => command('redo')}>↷</button>
        </div>

        <article className="verbum-writing-paper">
          <header><span>Capítulo {chapter.number}</span><h2>{chapter.title}</h2><p>Escreva com apoio da preparação, da pesquisa e das fontes selecionadas.</p></header>
          <section className="verbum-writing-block"><div className="verbum-writing-block-title"><span>Introdução</span></div><RichEditor html={draftRef.current.introduction} editorKey="introduction" placeholder="Apresente o capítulo e conduza o leitor ao tema central..." onFocus={setActive} onChange={updateDraft} /></section>
          <section className="verbum-writing-development">
            <div className="verbum-writing-block-title"><span>Desenvolvimento</span><button type="button" onClick={addSection}>+ Adicionar tópico</button></div>
            {sections.map((section, index) => <div className="verbum-writing-topic" key={section.id}>
              <div className="verbum-writing-topic-heading"><span>{index + 1}</span><input value={section.title} onChange={(event) => updateSectionTitle(section.id, event.target.value)} /><div><button disabled={index === 0} onClick={() => moveSection(index, -1)}>↑</button><button disabled={index === sections.length - 1} onClick={() => moveSection(index, 1)}>↓</button><button disabled={sections.length === 1} onClick={() => removeSection(section.id)}>×</button></div></div>
              <RichEditor html={section.content} editorKey={`section:${section.id}`} placeholder="Desenvolva este ponto do capítulo..." onFocus={setActive} onChange={updateDraft} />
            </div>)}
          </section>
          <section className="verbum-writing-block"><div className="verbum-writing-block-title"><span>Conclusão</span></div><RichEditor html={draftRef.current.conclusion} editorKey="conclusion" placeholder="Retome a mensagem essencial e conduza o leitor ao fechamento do capítulo..." onFocus={setActive} onChange={updateDraft} /></section>
        </article>

        <div className="verbum-writing-metrics"><span><strong>{metrics.words.toLocaleString('pt-BR')}</strong> palavras</span><span><strong>{metrics.characters.toLocaleString('pt-BR')}</strong> caracteres</span><span><strong>{formatTime(elapsed)}</strong> de escrita</span><label>Meta <input type="number" min="0" value={wordGoal || ''} onChange={(event) => { const next = Math.max(0, Number(event.target.value)); metaRef.current.wordGoal = next; setWordGoal(next); scheduleSave(); }} placeholder="0" /> palavras</label><span><strong>{goalProgress}%</strong> da meta</span></div>
      </main>

      <aside className="verbum-writing-research">
        <section className="verbum-writing-context"><span className="verbum-writing-panel-label">Direção do capítulo</span><div><small>Objetivo</small><p>{data.preparation.objective || '—'}</p></div><div><small>Pergunta Central</small><p>{data.preparation.centralQuestion || '—'}</p></div><div><small>Tese</small><p>{data.preparation.thesis || '—'}</p></div><div className="verbum-writing-keywords">{data.preparation.keywords.map((keyword) => <span key={keyword}>{keyword}</span>)}</div></section>

        {relatedSources.length > 0 && <section className="verbum-writing-related"><span className="verbum-writing-panel-label">Fontes relacionadas a este tópico</span><strong>{relatedSources.length} materiais</strong></section>}

        <section><span className="verbum-writing-panel-label">Fontes selecionadas</span><div className="verbum-writing-source-list">{data.sources.length === 0 ? <p>Nenhuma fonte selecionada.</p> : data.sources.map((source) => <article key={source.id} className={`${source.used || usedSourceIds.includes(source.id) ? 'is-used' : ''}${relatedSources.some((item) => item.id === source.id) ? ' is-related' : ''}`}><small>{source.category}</small><strong>{source.reference || source.title || source.author || 'Fonte'}</strong>{source.excerpt && <p>{source.excerpt}</p>}<button type="button" onClick={() => insertSource(source)}>{source.used || usedSourceIds.includes(source.id) ? 'Inserir novamente' : 'Inserir na Redação'}</button></article>)}</div></section>

        <section><span className="verbum-writing-panel-label">Ideias da Pesquisa</span><div className="verbum-writing-idea-list">{data.ideas.length === 0 ? <p>Nenhuma ideia registrada.</p> : data.ideas.map((idea) => <article key={idea.id} className={idea.used || usedIdeaIds.includes(idea.id) ? 'is-used' : ''}><strong>{idea.title || 'Ideia'}</strong><p>{idea.description}</p><button type="button" onClick={() => useIdea(idea.id, idea.title, idea.description)}>{idea.used || usedIdeaIds.includes(idea.id) ? 'Adicionar novamente às notas' : 'Inserir como anotação'}</button></article>)}</div></section>

        <section className="verbum-writing-assistant"><span className="verbum-writing-panel-label">Assistente de Escrita</span><p>Use o contexto do capítulo e apenas as fontes registradas na Pesquisa.</p><div>{[['develop','Desenvolver trecho'],['clarity','Melhorar clareza'],['rewrite','Reescrever'],['summarize','Resumir'],['expand','Expandir'],['transition','Sugerir transição'],['coherence','Verificar coerência']].map(([key, label]) => <button type="button" key={key} disabled={assistantBusy} onClick={() => void runAssistant(key)}>{label}</button>)}</div>{assistantBusy && <small>Gerando proposta...</small>}{assistantSuggestion && <div className="verbum-writing-assistant-result"><textarea value={assistantSuggestion} onChange={(event) => setAssistantSuggestion(event.target.value)} /><div><button type="button" onClick={acceptAssistant}>Aceitar</button><button type="button" onClick={() => setAssistantSuggestion('')}>Descartar</button></div></div>}</section>

        <section className="verbum-writing-notes"><span className="verbum-writing-panel-label">Notas e comentários</span><div className="verbum-writing-note-actions"><button onClick={() => addNote('note')}>+ Nota</button><button onClick={() => addNote('comment')}>+ Comentário</button></div>{notes.map((note) => <p key={note.id}><b>Nota</b> {note.text}</p>)}{comments.map((note) => <p key={note.id}><b>Comentário</b> {note.text}</p>)}</section>

        <section className="verbum-writing-progress"><div className="verbum-writing-progress-top"><span className="verbum-writing-panel-label">Progresso da Redação</span><strong>{data.progress}%</strong></div><div className="verbum-writing-progress-bar"><span style={{ width: `${data.progress}%` }} /></div>{data.checklist.map((item) => {
          const manual = Object.prototype.hasOwnProperty.call(flags, item.key);
          return <label key={item.key} className={item.completed ? 'is-complete' : ''}><input type="checkbox" disabled={!manual || item.key === 'completed'} checked={manual ? !!flags[item.key as keyof ChapterWritingFlags] : item.completed} onChange={(event) => { const next = { ...metaRef.current.flags, [item.key]: event.target.checked }; metaRef.current.flags = next; setFlags(next); scheduleSave(); }} /><span>{item.label}</span></label>;
        })}<button type="button" className="verbum-primary-button" disabled={!data.ready || data.completed || status === 'saving'} onClick={() => void finish()}>{data.completed ? 'Redação concluída ✓' : 'Concluir Redação ›'}</button></section>

        <section className="verbum-writing-versions"><span className="verbum-writing-panel-label">Histórico de versões</span>{data.versions.length === 0 ? <p>As versões de segurança aparecerão aqui.</p> : data.versions.slice(0, 5).map((version) => <div key={version.id}><span>{new Date(version.savedAt).toLocaleString('pt-BR')}</span><small>{version.wordCount.toLocaleString('pt-BR')} palavras · {version.kind}</small></div>)}</section>
      </aside>
    </div>

    {error && <p className="verbum-writing-message is-error">{error}</p>}
  </div>;
}
