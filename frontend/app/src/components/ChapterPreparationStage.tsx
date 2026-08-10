import { useEffect, useMemo, useState } from 'react';
import { completeChapterPreparation, getChapterPreparation, saveChapterPreparation } from '../services/library-service';
import type { ChapterPreparationInput, ChapterPreparationProgress, DevelopmentChapter, WorkDevelopmentProgress } from '../types/verbum';

type Props = {
  bookId: string;
  chapter: DevelopmentChapter;
  onChapterChange: (chapter: DevelopmentChapter) => void;
  onDevelopmentChange: (development: WorkDevelopmentProgress) => void;
};

const emptyInput: ChapterPreparationInput = {
  subtitle: '', objective: '', central_question: '', purpose: '', thesis: '', main_message: '', guiding_phrase: '',
  keywords: [], structure_items: [], source_categories: [], spiritual_intention: '', virtue: '', writing_prayer: '', notes: '',
};

export function ChapterPreparationStage({ bookId, chapter, onChapterChange, onDevelopmentChange }: Props) {
  const [data, setData] = useState<ChapterPreparationProgress | null>(null);
  const [form, setForm] = useState<ChapterPreparationInput>(emptyInput);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;
    setData(null); setError('');
    getChapterPreparation(bookId, chapter.id).then((result) => {
      if (!active) return;
      setData(result);
      const v = result.values;
      setForm({
        subtitle: v.subtitle, objective: v.objective, central_question: v.centralQuestion, purpose: v.purpose,
        thesis: v.thesis, main_message: v.mainMessage, guiding_phrase: v.guidingPhrase, keywords: v.keywords,
        structure_items: v.structureItems, source_categories: v.sourceCategories, spiritual_intention: v.spiritualIntention,
        virtue: v.virtue, writing_prayer: v.writingPrayer, notes: v.notes,
      });
    }).catch((cause) => { if (active) setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Preparação.'); });
    return () => { active = false; };
  }, [bookId, chapter.id]);

  const requiredReady = useMemo(() => Boolean(form.objective.trim() && form.central_question.trim() && form.thesis.trim() && form.structure_items.length && form.source_categories.length), [form]);

  function patch<K extends keyof ChapterPreparationInput>(key: K, value: ChapterPreparationInput[K]) {
    setForm((current) => ({ ...current, [key]: value })); setMessage('');
  }

  function addStructure() {
    patch('structure_items', [...form.structure_items, { id: `new-${Date.now()}`, text: '', order: form.structure_items.length + 1 }]);
  }

  function updateStructure(index: number, text: string) {
    patch('structure_items', form.structure_items.map((item, itemIndex) => itemIndex === index ? { ...item, text } : item));
  }

  function moveStructure(index: number, direction: -1 | 1) {
    const next = index + direction;
    if (next < 0 || next >= form.structure_items.length) return;
    const items = [...form.structure_items];
    [items[index], items[next]] = [items[next], items[index]];
    patch('structure_items', items.map((item, itemIndex) => ({ ...item, order: itemIndex + 1 })));
  }

  function removeStructure(index: number) {
    patch('structure_items', form.structure_items.filter((_, itemIndex) => itemIndex !== index).map((item, itemIndex) => ({ ...item, order: itemIndex + 1 })));
  }

  async function save() {
    setSaving(true); setError(''); setMessage('');
    try {
      const result = await saveChapterPreparation(bookId, chapter.id, form);
      setData(result.preparation); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage); setMessage('Alterações salvas.');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a Preparação.'); }
    finally { setSaving(false); }
  }

  async function complete() {
    if (!requiredReady || saving) return;
    setSaving(true); setError(''); setMessage('');
    try {
      await saveChapterPreparation(bookId, chapter.id, form);
      const result = await completeChapterPreparation(bookId, chapter.id);
      setData(result.preparation); onChapterChange(result.chapter); onDevelopmentChange(result.developmentStage); setMessage('Preparação concluída. Pesquisa liberada.');
    } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a Preparação.'); }
    finally { setSaving(false); }
  }

  if (!data && !error) return <div className="verbum-preparation-state">Carregando Preparação do Capítulo...</div>;
  if (!data) return <div className="verbum-preparation-state is-error">{error}</div>;

  return <div className="verbum-preparation-layout">
    <div className="verbum-preparation-main">
      <section className="verbum-preparation-card">
        <h3>Identidade do Capítulo</h3>
        <label><span>Título do capítulo</span><input value={chapter.title} readOnly /></label>
        <label><span>Subtítulo do capítulo</span><input value={form.subtitle} onChange={(event) => patch('subtitle', event.target.value)} placeholder="Opcional" /></label>
        <label><span>Objetivo do capítulo *</span><textarea value={form.objective} onChange={(event) => patch('objective', event.target.value)} placeholder="O que este capítulo precisa alcançar dentro da obra?" /></label>
        <label><span>Pergunta Central *</span><textarea value={form.central_question} onChange={(event) => patch('central_question', event.target.value)} placeholder="Qual pergunta principal este capítulo pretende responder?" /></label>
        <label><span>Finalidade do capítulo</span><textarea value={form.purpose} onChange={(event) => patch('purpose', event.target.value)} placeholder="Qual é a função deste capítulo dentro da construção da obra?" /></label>
      </section>

      <section className="verbum-preparation-card">
        <h3>Direção da Escrita</h3>
        <label><span>Tese do capítulo *</span><textarea value={form.thesis} onChange={(event) => patch('thesis', event.target.value)} placeholder="Ideia central que deverá ser desenvolvida." /></label>
        <label><span>Mensagem principal</span><textarea value={form.main_message} onChange={(event) => patch('main_message', event.target.value)} placeholder="O que precisa permanecer no leitor ao final?" /></label>
        <label><span>Frase norteadora</span><input value={form.guiding_phrase} onChange={(event) => patch('guiding_phrase', event.target.value)} /></label>
        <label><span>Palavras-chave</span><input value={form.keywords.join(', ')} onChange={(event) => patch('keywords', event.target.value.split(',').map((value) => value.trim()).filter(Boolean))} placeholder="sacerdócio, Eucaristia, missão" /></label>
      </section>

      <section className="verbum-preparation-card">
        <div className="verbum-preparation-card-heading"><div><h3>Estrutura Inicial</h3><p>Organize os pontos que precisam ser desenvolvidos neste capítulo.</p></div><button type="button" onClick={addStructure}>+ Adicionar item</button></div>
        <div className="verbum-preparation-structure">
          {form.structure_items.length === 0 ? <div className="verbum-preparation-empty">Nenhum item adicionado.</div> : form.structure_items.map((item, index) => <div className="verbum-preparation-structure-item" key={item.id || index}>
            <span>{index + 1}</span><input value={item.text} onChange={(event) => updateStructure(index, event.target.value)} placeholder="Ponto a desenvolver" />
            <button type="button" disabled={index === 0} onClick={() => moveStructure(index, -1)}>↑</button>
            <button type="button" disabled={index === form.structure_items.length - 1} onClick={() => moveStructure(index, 1)}>↓</button>
            <button type="button" className="is-danger" onClick={() => removeStructure(index)}>Remover</button>
          </div>)}
        </div>
      </section>

      <section className="verbum-preparation-card">
        <h3>Fontes que deverão ser pesquisadas *</h3>
        <p className="verbum-preparation-help">Marque as categorias que orientarão a Pesquisa deste capítulo.</p>
        <div className="verbum-preparation-sources">
          {data.sourceOptions.map((option) => <label key={option.key}><input type="checkbox" checked={form.source_categories.includes(option.key)} onChange={(event) => patch('source_categories', event.target.checked ? [...form.source_categories, option.key] : form.source_categories.filter((key) => key !== option.key))} /><span>{option.label}</span></label>)}
        </div>
      </section>

      <section className="verbum-preparation-card is-spiritual">
        <h3>Intenção Espiritual do Capítulo</h3>
        <p className="verbum-preparation-help">Bloco opcional para obras que desejam registrar uma intenção espiritual para a escrita.</p>
        <label><span>Intenção</span><textarea value={form.spiritual_intention} onChange={(event) => patch('spiritual_intention', event.target.value)} /></label>
        <label><span>Virtude relacionada</span><input value={form.virtue} onChange={(event) => patch('virtue', event.target.value)} /></label>
        <label><span>Oração antes da escrita</span><textarea value={form.writing_prayer} onChange={(event) => patch('writing_prayer', event.target.value)} /></label>
      </section>

      <section className="verbum-preparation-card"><h3>Observações da Preparação</h3><label><textarea value={form.notes} onChange={(event) => patch('notes', event.target.value)} placeholder="Registre decisões, lembretes e observações para este capítulo." /></label></section>

      {error && <p className="verbum-preparation-message is-error">{error}</p>}
      {message && <p className="verbum-preparation-message is-success">{message}</p>}
      <div className="verbum-preparation-actions"><button type="button" className="verbum-secondary-button" disabled={saving} onClick={save}>{saving ? 'Salvando…' : 'Salvar'}</button><button type="button" className="verbum-primary-button" disabled={!requiredReady || saving || data.completed} onClick={complete}>{data.completed ? 'Etapa concluída ✓' : 'Concluir Preparação ›'}</button></div>
    </div>

    <aside className="verbum-preparation-progress">
      <div className="verbum-preparation-progress-top"><div><span>Progresso da Preparação</span><strong>{data.progress}%</strong></div><div className="verbum-preparation-progress-bar"><span style={{ width: `${data.progress}%` }} /></div><small>{data.completedCount} de {data.total} itens concluídos</small></div>
      <div className="verbum-preparation-checklist">{data.checklist.map((item) => <div key={item.key} className={item.completed ? 'is-complete' : ''}><span>{item.completed ? '✓' : ''}</span><p>{item.label}</p></div>)}</div>
      <p className="verbum-preparation-required">Obrigatórios para liberar Pesquisa: objetivo, pergunta central, tese, estrutura inicial e pelo menos uma categoria de fonte.</p>
    </aside>
  </div>;
}
