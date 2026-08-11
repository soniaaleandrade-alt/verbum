import { useEffect, useMemo, useState } from 'react';
import { completeWorkPlanning, generatePlanningChapters, getWorkDevelopment, getWorkPlanning, saveWorkPlanning } from '../services/library-service';
import type { PlanningStructureItem, PlanningStructureType, WorkDevelopmentProgress, WorkPlanningInput, WorkPlanningProgress, WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import { WorkspaceFooter } from './WorkspaceFooter';

type SaveState = 'saved' | 'dirty' | 'saving' | 'error';
type Props = { workspace: WorkWorkspaceData; onWorkspaceChange: (workspace: WorkWorkspaceData) => void; onStageChange: (stage: WorkStageKey) => void; onDirtyChange: (dirty: boolean) => void; onPersisted: () => void | Promise<void> };
type FormState = { centralQuestion: string; mainThesis: string; overview: string; methodology: string; presentationForm: string; approach: string; generalStructure: string; editorialNotes: string; writingStrategy: string; initialSchedule: string; targetChapters: number; targetWords: number; targetPages: number; structureItems: PlanningStructureItem[] };

const emptyForm: FormState = { centralQuestion: '', mainThesis: '', overview: '', methodology: '', presentationForm: '', approach: '', generalStructure: '', editorialNotes: '', writingStrategy: '', initialSchedule: '', targetChapters: 0, targetWords: 0, targetPages: 0, structureItems: [] };
const presentationOptions = ['Expositiva', 'Narrativa', 'Didática', 'Catequética', 'Devocional', 'Acadêmica', 'Testemunhal', 'Mista'];
const approachOptions = ['Teológica', 'Bíblica', 'Pastoral', 'Espiritual', 'Histórica', 'Doutrinal', 'Científica', 'Acadêmica', 'Prática', 'Mista'];
const typeLabels: Record<PlanningStructureType, string> = { part: 'Parte', chapter: 'Capítulo', subchapter: 'Subcapítulo' };

function fromStage(stage: WorkPlanningProgress): FormState { return { ...emptyForm, ...stage.values, structureItems: stage.values.structureItems || [] }; }
function withChapterMetric(workspace: WorkWorkspaceData, planning: WorkPlanningProgress): WorkWorkspaceData { return { ...workspace, metrics: { ...workspace.metrics, chapters: planning.generatedChapterIds.length } }; }

export function PlanningStage({ workspace, onWorkspaceChange, onStageChange, onDirtyChange, onPersisted }: Props) {
  const [stage, setStage] = useState<WorkPlanningProgress | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saveState, setSaveState] = useState<SaveState>('saved');
  const [message, setMessage] = useState('');
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  const [preview, setPreview] = useState<WorkDevelopmentProgress | null>(null);
  const [previewLoading, setPreviewLoading] = useState(false);
  const [previewError, setPreviewError] = useState('');

  useEffect(() => {
    let active = true;
    setLoading(true);
    getWorkPlanning(workspace.book.id).then((data) => {
      if (!active) return;
      setStage(data);
      setForm(fromStage(data));
      if (workspace.metrics.chapters !== data.generatedChapterIds.length) onWorkspaceChange(withChapterMetric(workspace, data));
      setLoading(false);
    }).catch((cause) => {
      if (!active) return;
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível carregar o Planejamento.');
      setSaveState('error');
      setLoading(false);
    });
    return () => { active = false; };
  }, [workspace.book.id]);

  const checklist = stage?.checklist || [];
  const localChecklist = useMemo(() => {
    if (!stage) return checklist;
    const generated = stage.chaptersGenerated;
    const flags: Record<string, boolean> = {
      central_question: form.centralQuestion.trim() !== '', main_thesis: form.mainThesis.trim() !== '', overview: form.overview.trim() !== '', methodology: form.methodology.trim() !== '', presentation_form: form.presentationForm.trim() !== '', approach: form.approach.trim() !== '', general_structure: form.generalStructure.trim() !== '', provisional_index: form.structureItems.length > 0, work_goal: form.targetChapters > 0 && form.targetWords > 0 && form.targetPages > 0, chapters_generated: generated,
    };
    return checklist.map((item) => ({ ...item, completed: flags[item.key] ?? item.completed }));
  }, [checklist, form, stage]);
  const completedCount = localChecklist.filter((item) => item.completed).length;
  const progress = localChecklist.length ? Math.round(completedCount / localChecklist.length * 100) : 0;
  const ready = localChecklist.length > 0 && completedCount === localChecklist.length;
  const completed = Boolean(stage?.completed) && saveState !== 'dirty';

  function update<K extends keyof FormState>(key: K, value: FormState[K]) { setForm((current) => ({ ...current, [key]: value })); setSaveState('dirty'); setMessage(''); onDirtyChange(true); }
  function addItem(type: PlanningStructureType = 'chapter') { update('structureItems', [...form.structureItems, { id: `new-${Date.now()}`, type, title: '', order: form.structureItems.length + 1 }]); }
  function updateItem(index: number, patch: Partial<PlanningStructureItem>) { update('structureItems', form.structureItems.map((item, i) => i === index ? { ...item, ...patch } : item)); }
  function removeItem(index: number) { update('structureItems', form.structureItems.filter((_, i) => i !== index).map((item, i) => ({ ...item, order: i + 1 }))); }
  function moveItem(from: number, to: number) { if (to < 0 || to >= form.structureItems.length || from === to) return; const next = [...form.structureItems]; const [moved] = next.splice(from, 1); next.splice(to, 0, moved); update('structureItems', next.map((item, i) => ({ ...item, order: i + 1 }))); }

  function payload(): WorkPlanningInput { return { central_question: form.centralQuestion.trim(), main_thesis: form.mainThesis.trim(), overview: form.overview.trim(), methodology: form.methodology.trim(), presentation_form: form.presentationForm.trim(), approach: form.approach.trim(), general_structure: form.generalStructure.trim(), editorial_notes: form.editorialNotes.trim(), writing_strategy: form.writingStrategy.trim(), initial_schedule: form.initialSchedule.trim(), target_chapters: Number(form.targetChapters) || 0, target_words: Number(form.targetWords) || 0, target_pages: Number(form.targetPages) || 0, structure_items: form.structureItems.map((item, index) => ({ id: item.id, type: item.type, title: item.title.trim(), parentId: item.parentId || '', order: index + 1 })) }; }

  async function persist() {
    setSaveState('saving'); setMessage('');
    try {
      const result = await saveWorkPlanning(workspace.book.id, payload());
      setStage(result.planningStage); setForm(fromStage(result.planningStage)); onWorkspaceChange(withChapterMetric(result.workspace, result.planningStage)); onDirtyChange(false); setSaveState('saved'); setMessage('Alterações salvas.'); await onPersisted(); return result;
    } catch (cause) { setSaveState('error'); setMessage(cause instanceof Error ? cause.message : 'Não foi possível salvar o Planejamento.'); throw cause; }
  }
  async function generate() {
    try {
      if (saveState === 'dirty' || saveState === 'error') await persist();
      setSaveState('saving'); setPreview(null); setPreviewError('');
      const result = await generatePlanningChapters(workspace.book.id);
      setStage(result.planningStage); setForm(fromStage(result.planningStage)); onWorkspaceChange(withChapterMetric(result.workspace, result.planningStage)); onDirtyChange(false); setSaveState('saved'); setMessage('Os capítulos já foram criados. Você pode visualizá-los antes de concluir o Planejamento.'); await onPersisted();
    } catch (cause) { setSaveState('error'); setMessage(cause instanceof Error ? cause.message : 'Não foi possível gerar os capítulos.'); }
  }
  async function viewChapters() {
    if (!stage?.chaptersGenerated || previewLoading) return;
    setPreviewLoading(true); setPreviewError('');
    try {
      const result = await getWorkDevelopment(workspace.book.id);
      setPreview(result);
      if (workspace.metrics.chapters !== result.summary.total) onWorkspaceChange({ ...workspace, metrics: { ...workspace.metrics, chapters: result.summary.total } });
    } catch (cause) { setPreview(null); setPreviewError(cause instanceof Error ? cause.message : 'Não foi possível carregar os capítulos.'); }
    finally { setPreviewLoading(false); }
  }
  async function finish() {
    try {
      if (saveState === 'dirty' || saveState === 'error') await persist();
      if (!ready) { setMessage('Complete os 10 itens do Planejamento da Obra antes de continuar.'); return; }
      setSaveState('saving');
      const result = await completeWorkPlanning(workspace.book.id);
      setStage(result.planningStage); onWorkspaceChange(withChapterMetric(result.workspace, result.planningStage)); onDirtyChange(false); setSaveState('saved'); await onPersisted(); onStageChange('development');
    } catch (cause) { setSaveState('error'); setMessage(cause instanceof Error ? cause.message : 'Não foi possível concluir o Planejamento.'); }
  }

  if (loading) return <section className="verbum-stage-content verbum-planning-state">Carregando Planejamento da Obra...</section>;
  const counts = stage?.counts || { parts: 0, chapters: 0, subchapters: 0 };

  return <>
    <section className="verbum-stage-content verbum-planning-stage">
      <header className="verbum-planning-heading"><h2>Planejamento da Obra</h2><p>Índice, estrutura editorial e geração de capítulos.</p></header>
      <div className="verbum-planning-layout">
        <form className="verbum-planning-form" onSubmit={(e) => { e.preventDefault(); persist().catch(() => undefined); }}>
          <section className="verbum-planning-card"><h3>◉ Pergunta Central</h3><label><span>Pergunta Central <b>*</b></span><textarea rows={4} value={form.centralQuestion} onChange={(e) => update('centralQuestion', e.target.value)} placeholder="Qual é a principal pergunta que esta obra pretende responder?" /></label><label><span>Tese Principal <b>*</b></span><textarea rows={4} value={form.mainThesis} onChange={(e) => update('mainThesis', e.target.value)} placeholder="Qual é a principal ideia que será defendida ao longo da obra?" /></label><label><span>Visão Geral da Obra</span><textarea rows={4} value={form.overview} onChange={(e) => update('overview', e.target.value)} placeholder="Descreva a visão completa do livro..." /></label></section>
          <section className="verbum-planning-card"><h3>⚗ Metodologia</h3><label><span>Metodologia</span><textarea rows={4} value={form.methodology} onChange={(e) => update('methodology', e.target.value)} placeholder="Descreva como a obra será desenvolvida..." /></label><div className="verbum-planning-two"><label><span>Forma de Apresentação</span><select value={form.presentationForm} onChange={(e) => update('presentationForm', e.target.value)}><option value="">Selecione...</option>{presentationOptions.map((option) => <option key={option}>{option}</option>)}</select></label><label><span>Abordagem</span><select value={form.approach} onChange={(e) => update('approach', e.target.value)}><option value="">Selecione...</option>{approachOptions.map((option) => <option key={option}>{option}</option>)}</select></label></div><label><span>Estrutura Geral</span><textarea rows={4} value={form.generalStructure} onChange={(e) => update('generalStructure', e.target.value)} placeholder="Descreva como a obra será organizada..." /></label></section>
          <section className="verbum-planning-card"><h3>☷ Estrutura da Obra</h3><p className="verbum-planning-help">Construa o índice provisório. Cada item pode ser transformado em Parte, Capítulo ou Subcapítulo. Arraste um item sobre outro para reordená-lo.</p><div className="verbum-outline-list">{form.structureItems.length === 0 ? <div className="verbum-outline-empty">Nenhum item ainda. Clique em “Adicionar item” para começar.</div> : form.structureItems.map((item, index) => <div className={`verbum-outline-item is-${item.type}`} key={item.id} draggable onDragStart={() => setDragIndex(index)} onDragOver={(e) => e.preventDefault()} onDrop={() => { if (dragIndex !== null) moveItem(dragIndex, index); setDragIndex(null); }}><span className="verbum-outline-handle">⋮⋮</span><select value={item.type} onChange={(e) => updateItem(index, { type: e.target.value as PlanningStructureType })}><option value="part">Parte</option><option value="chapter">Capítulo</option><option value="subchapter">Subcapítulo</option></select><input value={item.title} onChange={(e) => updateItem(index, { title: e.target.value })} placeholder={`${typeLabels[item.type]} ${index + 1}`} /><button type="button" onClick={() => moveItem(index, index - 1)} disabled={index === 0}>↑</button><button type="button" onClick={() => moveItem(index, index + 1)} disabled={index === form.structureItems.length - 1}>↓</button><button type="button" className="is-danger" onClick={() => removeItem(index)}>Remover</button></div>)}</div><div className="verbum-outline-actions"><button type="button" className="verbum-text-button" onClick={() => addItem('chapter')}>＋ Adicionar item</button></div></section>
          <section className="verbum-planning-card"><h3>⌘ Organização Editorial</h3><label><span>Observações Gerais</span><textarea rows={3} value={form.editorialNotes} onChange={(e) => update('editorialNotes', e.target.value)} placeholder="Observações sobre a organização editorial..." /></label><label><span>Estratégia de Escrita</span><textarea rows={3} value={form.writingStrategy} onChange={(e) => update('writingStrategy', e.target.value)} placeholder="Como pretende desenvolver esta obra?" /></label><label><span>Cronograma Inicial (opcional)</span><textarea rows={3} value={form.initialSchedule} onChange={(e) => update('initialSchedule', e.target.value)} placeholder="Defina um cronograma inicial para a escrita..." /></label><div className="verbum-goal-title">Meta da Obra</div><div className="verbum-planning-three"><label><span>Capítulos (estimado)</span><input type="number" min="0" value={form.targetChapters || ''} onChange={(e) => update('targetChapters', Number(e.target.value))} placeholder="Ex: 18" /></label><label><span>Palavras (estimado)</span><input type="number" min="0" value={form.targetWords || ''} onChange={(e) => update('targetWords', Number(e.target.value))} placeholder="Ex: 50000" /></label><label><span>Páginas (estimado)</span><input type="number" min="0" value={form.targetPages || ''} onChange={(e) => update('targetPages', Number(e.target.value))} placeholder="Ex: 200" /></label></div></section>
          <section className="verbum-planning-card verbum-generation-card">
            <h3>▣ Geração dos Capítulos</h3>
            <div className="verbum-generation-counts"><div><strong>{counts.parts}</strong><span>Partes</span></div><div><strong>{counts.chapters}</strong><span>Capítulos</span></div><div><strong>{counts.subchapters}</strong><span>Subcapítulos</span></div></div>
            {form.structureItems.length === 0 ? <p>Adicione itens ao índice provisório para gerar os capítulos.</p> : <button type="button" className="verbum-secondary-button" onClick={generate} disabled={saveState === 'saving'}>{stage?.chaptersGenerated ? 'Sincronizar capítulos' : 'Gerar capítulos'}</button>}
            {stage?.chaptersGenerated && <div className="verbum-generation-success">Os capítulos já foram criados. Visualize a prévia abaixo ou conclua o Planejamento para editá-los no Desenvolvimento.</div>}
            <button type="button" className="verbum-outline-view" onClick={viewChapters} disabled={!stage?.chaptersGenerated || previewLoading}>→ Visualizar capítulos</button>
            {(previewLoading || previewError || preview) && <section className="verbum-planning-preview">
              <div className="verbum-planning-preview-head"><div><strong>Capítulos gerados</strong><span>{preview ? `${preview.chapters.length} capítulo${preview.chapters.length === 1 ? '' : 's'} · ` : ''}Prévia somente leitura</span></div>{!previewLoading && <button type="button" onClick={() => { setPreview(null); setPreviewError(''); }}>Fechar</button>}</div>
              {previewLoading ? <p className="verbum-planning-preview-state">Carregando capítulos...</p> : previewError ? <p className="verbum-planning-preview-state is-error">{previewError}</p> : preview && preview.chapters.length > 0 ? <div className="verbum-planning-preview-list">{preview.chapters.map((chapter) => <article key={chapter.id}><span>{String(chapter.number).padStart(2, '0')}</span><div><strong>{chapter.title}</strong><small>{chapter.stageLabel} · {chapter.progress}%</small></div></article>)}</div> : <p className="verbum-planning-preview-state">Nenhum capítulo gerado.</p>}
              {!previewLoading && !previewError && <p className="verbum-planning-preview-note">Conclua o Planejamento para editar os capítulos no Desenvolvimento.</p>}
            </section>}
          </section>
          {message && <p className={`verbum-project-message${saveState === 'error' ? ' is-error' : ''}`} role="status">{message}</p>}
        </form>
        <aside className="verbum-project-progress"><div className="verbum-progress-heading"><h3>Progresso da Etapa</h3><strong>{progress}%</strong></div><div className="verbum-progress-track"><span style={{ width: `${progress}%` }} /></div><p>{completedCount} de {localChecklist.length} itens concluídos</p><div className="verbum-project-checklist">{localChecklist.map((item) => <div key={item.key} className={item.completed ? 'is-completed' : ''}><span>{item.completed ? '✓' : ''}</span><span>{item.label}</span></div>)}</div></aside>
      </div>
    </section>
    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('project')} onBackToLibrary={() => undefined} saveState={saveState} saveDisabled={saveState !== 'dirty' && saveState !== 'error'} continueDisabled={!ready || saveState === 'saving' || completed} continueLabel={completed ? 'Etapa concluída ✓' : ready ? 'Etapa concluída ›' : 'Salvar e continuar ›'} onSave={() => persist().catch(() => undefined)} onContinue={finish} />
  </>;
}
