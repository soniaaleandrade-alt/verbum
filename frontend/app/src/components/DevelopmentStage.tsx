import { useEffect, useMemo, useState } from 'react';
import type { CSSProperties } from 'react';
import { completeWorkDevelopment, getDevelopmentChapter, getWorkDevelopment } from '../services/library-service';
import type { ChapterStageKey, DevelopmentChapter, WorkDevelopmentProgress, WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import { WorkspaceFooter } from './WorkspaceFooter';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onPersisted: () => void | Promise<void>;
};

const filters: Array<{ key: 'all' | ChapterStageKey | 'completed'; label: string }> = [
  { key: 'all', label: 'Todos' },
  { key: 'preparation', label: 'Preparação' },
  { key: 'research', label: 'Pesquisa' },
  { key: 'writing', label: 'Redação' },
  { key: 'revision', label: 'Revisão' },
  { key: 'completed', label: 'Concluídos' },
];

const stageColors: Record<ChapterStageKey, string> = {
  preparation: '#0f7182', research: '#2d75b8', writing: '#bd7724', revision: '#7a3cc8',
};

type StageColorStyle = CSSProperties & { '--stage-color': string };

function relativeDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  const today = new Date();
  date.setHours(0, 0, 0, 0); today.setHours(0, 0, 0, 0);
  const diff = Math.floor((today.getTime() - date.getTime()) / 86400000);
  if (diff === 0) return 'hoje';
  if (diff === 1) return 'ontem';
  if (diff > 1 && diff < 30) return `há ${diff} dias`;
  return new Date(value).toLocaleDateString('pt-BR');
}

export function DevelopmentStage({ workspace, onWorkspaceChange, onStageChange, onPersisted }: Props) {
  const [data, setData] = useState<WorkDevelopmentProgress | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState<'all' | ChapterStageKey | 'completed'>('all');
  const [sort, setSort] = useState<'order' | 'updated' | 'title' | 'progress'>('order');
  const [chapter, setChapter] = useState<DevelopmentChapter | null>(null);

  useEffect(() => {
    let active = true;
    setLoading(true);
    getWorkDevelopment(workspace.book.id).then((result) => {
      if (!active) return;
      setData(result); setLoading(false);
      if (workspace.metrics.chapters !== result.summary.total || workspace.metrics.words !== result.summary.words) {
        onWorkspaceChange({ ...workspace, metrics: { ...workspace.metrics, chapters: result.summary.total, words: result.summary.words } });
      }
    }).catch((cause) => { if (!active) return; setError(cause instanceof Error ? cause.message : 'Não foi possível carregar o Desenvolvimento.'); setLoading(false); });
    return () => { active = false; };
  }, [workspace.book.id]);

  const visible = useMemo(() => {
    if (!data) return [];
    const normalized = query.trim().toLocaleLowerCase('pt-BR');
    const list = data.chapters.filter((item) => {
      const matchesQuery = !normalized || `${item.number} ${item.title}`.toLocaleLowerCase('pt-BR').includes(normalized);
      const matchesFilter = filter === 'all' || (filter === 'completed' ? item.completed : (!item.completed && item.stage === filter));
      return matchesQuery && matchesFilter;
    });
    return [...list].sort((a, b) => {
      if (sort === 'updated') return new Date(b.lastEdited).getTime() - new Date(a.lastEdited).getTime();
      if (sort === 'title') return a.title.localeCompare(b.title, 'pt-BR');
      if (sort === 'progress') return b.progress - a.progress;
      return a.number - b.number;
    });
  }, [data, query, filter, sort]);

  async function openChapter(id: string) {
    setError('');
    try { setChapter(await getDevelopmentChapter(workspace.book.id, id)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível abrir o capítulo.'); }
  }

  async function finish() {
    if (!data?.ready) return;
    try {
      const result = await completeWorkDevelopment(workspace.book.id);
      setData(result.developmentStage); onWorkspaceChange(result.workspace); await onPersisted(); onStageChange('general_review');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível concluir o Desenvolvimento.'); }
  }

  if (loading) return <section className="verbum-stage-content verbum-development-state">Carregando Desenvolvimento da Obra...</section>;
  if (!data) return <section className="verbum-stage-content verbum-development-state is-error">{error || 'Desenvolvimento indisponível.'}</section>;

  if (chapter) {
    return <section className="verbum-stage-content verbum-chapter-workspace">
      <button type="button" className="verbum-chapter-back" onClick={() => setChapter(null)}>‹ Desenvolvimento</button>
      <header className="verbum-chapter-header">
        <div><span>Capítulo {chapter.number}</span><h2>{chapter.title}</h2><p>{chapter.stageLabel} · {chapter.progress}% concluído</p></div>
        <span className="verbum-chapter-save-state">Salvo</span>
      </header>
      <nav className="verbum-chapter-workflow">
        {chapter.workflow.map((step) => <div key={step.key} className={`is-${step.status}`}><span>{step.status === 'completed' ? '✓' : step.status === 'locked' ? '⌑' : step.order}</span><strong>{step.label}</strong></div>)}
      </nav>
      <div className="verbum-chapter-stage-placeholder">
        <span className="verbum-chapter-stage-icon">{chapter.stage === 'preparation' ? '◎' : chapter.stage === 'research' ? '⌕' : chapter.stage === 'writing' ? '✎' : '✓'}</span>
        <h3>{chapter.stageLabel} do Capítulo</h3>
        <p>O fluxo do capítulo está pronto. Os campos funcionais desta etapa serão implementados no sprint correspondente.</p>
      </div>
      <footer className="verbum-chapter-navigation">
        <button type="button" disabled={!chapter.previousId} onClick={() => chapter.previousId && openChapter(chapter.previousId)}>‹ Capítulo anterior</button>
        <span>{chapter.position} de {chapter.totalChapters}</span>
        <button type="button" disabled={!chapter.nextId} onClick={() => chapter.nextId && openChapter(chapter.nextId)}>Próximo capítulo ›</button>
      </footer>
    </section>;
  }

  const summary = data.summary;
  return <>
    <section className="verbum-stage-content verbum-development-stage">
      <header className="verbum-development-heading"><h2>Desenvolvimento da Obra</h2><p>Gestão e redação dos capítulos da obra.</p></header>
      <div className="verbum-development-stats">
        {[
          ['Total', summary.total], ['Concluídos', summary.completed], ['Preparação', summary.preparation], ['Pesquisa', summary.research], ['Redação', summary.writing], ['Revisão', summary.revision], ['Obra', `${summary.progress}%`],
        ].map(([label, value]) => <article key={String(label)}><strong>{value}</strong><span>{label}</span></article>)}
      </div>
      <div className="verbum-development-toolbar">
        <label className="verbum-development-search"><span>⌕</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Pesquisar por título, número ou palavra-chave..." /></label>
        <select value={sort} onChange={(event) => setSort(event.target.value as typeof sort)}><option value="order">Ordem da obra</option><option value="updated">Última edição</option><option value="title">Título</option><option value="progress">Progresso</option></select>
      </div>
      <div className="verbum-development-filters">
        {filters.map((item) => {
          const count = item.key === 'all' ? summary.total : item.key === 'completed' ? summary.completed : summary[item.key];
          return <button type="button" key={item.key} className={filter === item.key ? 'is-active' : ''} onClick={() => setFilter(item.key)}>{item.label} <span>{count}</span></button>;
        })}
      </div>
      {error && <p className="verbum-development-message is-error">{error}</p>}
      {data.chapters.length === 0 ? <div className="verbum-development-empty"><h3>Nenhum capítulo foi gerado ainda.</h3><p>Volte ao Planejamento da Obra, adicione capítulos ao índice provisório e gere a estrutura.</p><button type="button" onClick={() => onStageChange('planning')}>Voltar ao Planejamento</button></div> : visible.length === 0 ? <div className="verbum-development-empty"><h3>Nenhum capítulo encontrado.</h3><p>Ajuste a pesquisa ou os filtros para visualizar outros capítulos.</p></div> : <div className="verbum-chapter-list">
        {visible.map((item) => <article className="verbum-chapter-card" key={item.id}>
          <div className="verbum-chapter-card-order">{String(item.number).padStart(2, '0')}</div>
          <div className="verbum-chapter-card-main"><div className="verbum-chapter-card-top"><span className="verbum-chapter-stage-badge" style={{ '--stage-color': stageColors[item.stage] } as StageColorStyle}>{item.completed ? 'Concluído' : item.stageLabel}</span><span>{item.progress}% concluído</span></div><h3>{item.title}</h3><div className="verbum-chapter-progress"><span style={{ width: `${item.progress}%` }} /></div><div className="verbum-chapter-card-meta"><span>{item.wordCount.toLocaleString('pt-BR')} palavras</span><span>Última edição: {relativeDate(item.lastEdited)}</span></div></div>
          <button type="button" className="verbum-chapter-open" onClick={() => openChapter(item.id)}>Abrir capítulo ›</button>
        </article>)}
      </div>}
    </section>
    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('planning')} onBackToLibrary={() => undefined} continueDisabled={!data.ready || data.completed} continueLabel={data.completed ? 'Etapa concluída ✓' : 'Concluir Desenvolvimento ›'} onContinue={finish} />
  </>;
}
