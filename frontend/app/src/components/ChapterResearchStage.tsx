import { useEffect, useMemo, useState } from 'react';
import {
  completeChapterResearch,
  createChapterResearchSource,
  deleteChapterResearchSource,
  getChapterResearch,
  saveChapterResearchState,
  updateChapterResearchSource,
} from '../services/library-service';
import type {
  ChapterResearchIdea,
  ChapterResearchProgress,
  ChapterResearchSource,
  ChapterResearchSourceInput,
  DevelopmentChapter,
  ResearchCategory,
  WorkDevelopmentProgress,
} from '../types/verbum';

type Props = {
  bookId: string;
  chapter: DevelopmentChapter;
  onChapterChange: (chapter: DevelopmentChapter) => void;
  onDevelopmentChange: (development: WorkDevelopmentProgress) => void;
};

const emptySource: ChapterResearchSourceInput = {
  category: 'other', title: '', author: '', reference: '', excerpt: '', notes: '', application: '', tags: [], url: '',
  structure_item_id: '', highlighted: false, selected_for_writing: false, details: {},
};

export function ChapterResearchStage({ bookId, chapter, onChapterChange, onDevelopmentChange }: Props) {
  const [data, setData] = useState<ChapterResearchProgress | null>(null);
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState<'all' | ResearchCategory>('all');
  const [filter, setFilter] = useState<'all' | 'highlighted' | 'selected'>('all');
  const [source, setSource] = useState<ChapterResearchSourceInput | null>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    getChapterResearch(bookId, chapter.id).then((result) => { if (active) setData(result); })
      .catch((cause) => { if (active) setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Pesquisa.'); });
    return () => { active = false; };
  }, [bookId, chapter.id]);

  const visibleSources = useMemo(() => {
    if (!data) return [];
    const q = query.trim().toLocaleLowerCase('pt-BR');
    return data.sources.filter((item) => {
      const haystack = [item.author, item.title, item.reference, item.excerpt, item.notes, item.application, ...item.tags].join(' ').toLocaleLowerCase('pt-BR');
      return (!q || haystack.includes(q))
        && (category === 'all' || item.category === category)
        && (filter === 'all' || (filter === 'highlighted' ? item.highlighted : item.selectedForWriting));
    });
  }, [data, query, category, filter]);

  function sourceInput(item: ChapterResearchSource): ChapterResearchSourceInput {
    return {
      category: item.category, title: item.title, author: item.author, reference: item.reference, excerpt: item.excerpt,
      notes: item.notes, application: item.application, tags: [...item.tags], url: item.url, structure_item_id: item.structureItemId,
      highlighted: item.highlighted, selected_for_writing: item.selectedForWriting, details: { ...item.details },
    };
  }

  async function persistState(nextData = data) {
    if (!nextData || busy) return;
    setBusy(true); setError('');
    try {
      const result = await saveChapterResearchState(bookId, chapter.id, {
        direction_reviewed: nextData.directionReviewed,
        reviewed_categories: nextData.reviewedCategories,
        ideas: nextData.ideas,
      });
      setData(result.research); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a organização da Pesquisa.'); }
    finally { setBusy(false); }
  }

  async function saveSource() {
    if (!source || busy) return;
    setBusy(true); setError('');
    try {
      const result = editingId
        ? await updateChapterResearchSource(bookId, chapter.id, editingId, source)
        : await createChapterResearchSource(bookId, chapter.id, source);
      setData(result.research); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
      setSource(null); setEditingId(null);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a fonte.'); }
    finally { setBusy(false); }
  }

  async function quickUpdate(item: ChapterResearchSource, patch: Partial<ChapterResearchSourceInput>) {
    try {
      const result = await updateChapterResearchSource(bookId, chapter.id, item.id, patch);
      setData(result.research); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível atualizar a fonte.'); }
  }

  async function removeSource(item: ChapterResearchSource) {
    if (!window.confirm('Excluir esta fonte da pesquisa?')) return;
    try {
      const result = await deleteChapterResearchSource(bookId, chapter.id, item.id);
      setData(result.research); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível excluir a fonte.'); }
  }

  async function finish() {
    if (!data?.ready || busy) return;
    setBusy(true); setError('');
    try {
      const result = await completeChapterResearch(bookId, chapter.id);
      setData(result.research); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage);
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a Pesquisa.'); }
    finally { setBusy(false); }
  }

  if (!data) return <div className={`verbum-research-state${error ? ' is-error' : ''}`}>{error || 'Carregando Pesquisa do Capítulo...'}</div>;

  return <div className="verbum-research-layout">
    <div className="verbum-research-main">
      <section className="verbum-research-direction">
        <div className="verbum-research-section-heading"><div><span>Direção da Pesquisa</span><h3>Pesquise com o objetivo do capítulo em vista</h3></div><label><input type="checkbox" checked={data.directionReviewed} onChange={(event) => setData({ ...data, directionReviewed: event.target.checked })} /> Direção revisada</label></div>
        <div className="verbum-research-direction-grid">
          <article><span>Objetivo</span><p>{data.preparation.objective || '—'}</p></article>
          <article><span>Pergunta Central</span><p>{data.preparation.centralQuestion || '—'}</p></article>
          <article><span>Tese</span><p>{data.preparation.thesis || '—'}</p></article>
        </div>
        <div className="verbum-research-keywords">{data.preparation.keywords.map((keyword) => <span key={keyword}>{keyword}</span>)}</div>
      </section>

      <section className="verbum-research-summary">
        <article><strong>{data.counts.total}</strong><span>Total de fontes</span></article>
        <article><strong>{data.counts.scripture}</strong><span>Bíblia</span></article>
        <article><strong>{data.counts.catechism}</strong><span>Catecismo</span></article>
        <article><strong>{data.counts.magisterium}</strong><span>Magistério</span></article>
        <article><strong>{data.counts.highlighted}</strong><span>Destacadas</span></article>
        <article><strong>{data.counts.selectedForWriting}</strong><span>Para Redação</span></article>
      </section>

      <section className="verbum-research-central">
        <div className="verbum-research-section-heading"><div><span>Central de Pesquisa</span><h3>Fontes do capítulo</h3></div><button type="button" className="verbum-primary-button" onClick={() => { const first = data.categoryOptions.find((item) => item.selectedInPreparation); setSource({ ...emptySource, category: first?.key ?? 'other' }); setEditingId(null); }}>+ Adicionar fonte</button></div>
        <div className="verbum-research-toolbar"><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Pesquisar nas fontes deste capítulo..." /><div><button className={filter === 'all' ? 'is-active' : ''} onClick={() => setFilter('all')}>Todos</button><button className={filter === 'highlighted' ? 'is-active' : ''} onClick={() => setFilter('highlighted')}>Destacados</button><button className={filter === 'selected' ? 'is-active' : ''} onClick={() => setFilter('selected')}>Selecionados para Redação</button></div></div>
        <div className="verbum-research-categories"><button className={category === 'all' ? 'is-active' : ''} onClick={() => setCategory('all')}>Todas <span>{data.counts.total}</span></button>{data.categoryOptions.map((item) => <button key={item.key} className={`${category === item.key ? 'is-active' : ''}${item.selectedInPreparation ? ' is-priority' : ''}`} onClick={() => setCategory(item.key)}>{item.label} <span>{item.count}</span></button>)}</div>

        {source && <div className="verbum-research-source-editor">
          <div className="verbum-research-section-heading"><div><span>{editingId ? 'Editar fonte' : 'Nova fonte'}</span><h3>Registro estruturado</h3></div><button type="button" onClick={() => { setSource(null); setEditingId(null); }}>Cancelar</button></div>
          <div className="verbum-research-form-grid">
            <label><span>Categoria</span><select value={source.category} onChange={(event) => setSource({ ...source, category: event.target.value as ResearchCategory })}>{data.categoryOptions.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select></label>
            <label><span>Título / assunto</span><input value={source.title} onChange={(event) => setSource({ ...source, title: event.target.value })} /></label>
            <label><span>Autor</span><input value={source.author} onChange={(event) => setSource({ ...source, author: event.target.value })} /></label>
            <label><span>Referência</span><input value={source.reference} onChange={(event) => setSource({ ...source, reference: event.target.value })} placeholder="Ex.: Jo 20,21-23 · CIC 1548" /></label>
            <label className="is-wide"><span>Trecho / citação</span><textarea value={source.excerpt} onChange={(event) => setSource({ ...source, excerpt: event.target.value })} /></label>
            <label className="is-wide"><span>Por que é importante / comentário</span><textarea value={source.notes} onChange={(event) => setSource({ ...source, notes: event.target.value })} /></label>
            <label className="is-wide"><span>Aplicação no capítulo</span><textarea value={source.application} onChange={(event) => setSource({ ...source, application: event.target.value })} /></label>
            <label><span>Tags</span><input value={source.tags.join(', ')} onChange={(event) => setSource({ ...source, tags: event.target.value.split(',').map((value) => value.trim()).filter(Boolean) })} /></label>
            <label><span>Vincular à estrutura</span><select value={source.structure_item_id} onChange={(event) => setSource({ ...source, structure_item_id: event.target.value })}><option value="">Sem vínculo</option>{data.preparation.structureItems.map((item) => <option key={item.id} value={item.id}>{item.order}. {item.text}</option>)}</select></label>
            <label><span>Link opcional</span><input value={source.url} onChange={(event) => setSource({ ...source, url: event.target.value })} /></label>
            <div className="verbum-research-flags"><label><input type="checkbox" checked={source.highlighted} onChange={(event) => setSource({ ...source, highlighted: event.target.checked })} /> Destacar</label><label><input type="checkbox" checked={source.selected_for_writing} onChange={(event) => setSource({ ...source, selected_for_writing: event.target.checked })} /> Selecionar para Redação</label></div>
          </div>
          <button type="button" className="verbum-primary-button" onClick={saveSource} disabled={busy}>Salvar fonte</button>
        </div>}

        <div className="verbum-research-source-list">{visibleSources.length === 0 ? <div className="verbum-research-empty">Nenhuma fonte encontrada para este filtro.</div> : visibleSources.map((item) => <article key={item.id} className={`verbum-research-source-card${item.highlighted ? ' is-highlighted' : ''}${item.selectedForWriting ? ' is-selected' : ''}`}>
          <div className="verbum-research-source-top"><span>{item.categoryLabel}</span><div>{item.highlighted && <b>★ Destaque</b>}{item.selectedForWriting && <b>Selecionado para Redação</b>}</div></div>
          <h4>{item.reference || item.title || item.author || item.categoryLabel}</h4>{item.title && item.reference && <strong>{item.title}</strong>}{item.excerpt && <p>{item.excerpt}</p>}
          <div className="verbum-research-tags">{item.tags.map((tag) => <span key={tag}>{tag}</span>)}</div>
          <div className="verbum-research-source-actions"><button onClick={() => { setEditingId(item.id); setSource(sourceInput(item)); }}>Editar</button><button onClick={() => quickUpdate(item, { highlighted: !item.highlighted })}>{item.highlighted ? 'Remover destaque' : '★ Destacar'}</button><button onClick={() => quickUpdate(item, { selected_for_writing: !item.selectedForWriting })}>{item.selectedForWriting ? 'Retirar da Redação' : 'Usar na Redação'}</button><button className="is-danger" onClick={() => removeSource(item)}>Excluir</button></div>
        </article>)}</div>
      </section>

      <section className="verbum-research-ideas">
        <div className="verbum-research-section-heading"><div><span>Banco de Ideias do capítulo</span><h3>Ideias surgidas durante a pesquisa</h3></div><button type="button" onClick={() => setData({ ...data, ideas: [...data.ideas, { id: `new-${Date.now()}`, title: '', description: '', tags: [], structureItemId: '' }] })}>+ Adicionar ideia</button></div>
        {data.ideas.map((idea, index) => <div className="verbum-research-idea" key={idea.id}><input value={idea.title} placeholder="Título curto" onChange={(event) => { const ideas = [...data.ideas]; ideas[index] = { ...idea, title: event.target.value }; setData({ ...data, ideas }); }} /><textarea value={idea.description} placeholder="Descreva a ideia" onChange={(event) => { const ideas = [...data.ideas]; ideas[index] = { ...idea, description: event.target.value }; setData({ ...data, ideas }); }} /><select value={idea.structureItemId} onChange={(event) => { const ideas = [...data.ideas]; ideas[index] = { ...idea, structureItemId: event.target.value }; setData({ ...data, ideas }); }}><option value="">Sem vínculo</option>{data.preparation.structureItems.map((item) => <option key={item.id} value={item.id}>{item.order}. {item.text}</option>)}</select><input value={idea.tags.join(', ')} placeholder="Tags" onChange={(event) => { const ideas = [...data.ideas]; ideas[index] = { ...idea, tags: event.target.value.split(',').map((value) => value.trim()).filter(Boolean) }; setData({ ...data, ideas }); }} /><button className="is-danger" onClick={() => setData({ ...data, ideas: data.ideas.filter((current) => current.id !== idea.id) })}>Remover</button></div>)}
      </section>

      {error && <p className="verbum-research-message is-error">{error}</p>}
      <div className="verbum-research-actions"><button type="button" className="verbum-secondary-button" disabled={busy} onClick={() => persistState()}>Salvar organização</button><button type="button" className="verbum-primary-button" disabled={busy || !data.ready || data.completed} onClick={finish}>{data.completed ? 'Pesquisa concluída ✓' : 'Concluir Pesquisa ›'}</button></div>
    </div>

    <aside className="verbum-research-progress"><div><span>Progresso da Pesquisa</span><strong>{data.progress}%</strong><div className="verbum-research-progress-bar"><span style={{ width: `${data.progress}%` }} /></div><small>{data.completedCount} de {data.total} itens concluídos</small></div><div className="verbum-research-checklist">{data.checklist.map((item) => <div key={item.key} className={item.completed ? 'is-complete' : ''}><span>{item.completed ? '✓' : ''}</span><p>{item.label}</p></div>)}</div><div className="verbum-research-category-review"><h4>Categorias da Preparação</h4>{data.categoryOptions.filter((item) => item.selectedInPreparation).map((item) => <label key={item.key}><input type="checkbox" checked={data.reviewedCategories.includes(item.key)} onChange={(event) => { const reviewed = event.target.checked ? [...data.reviewedCategories, item.key] : data.reviewedCategories.filter((key) => key !== item.key); setData({ ...data, reviewedCategories: reviewed }); }} /><span>{item.label}</span></label>)}</div><p>Para liberar Redação: cadastre uma fonte, revise todas as categorias escolhidas e selecione ao menos um material para a escrita.</p></aside>
  </div>;
}
