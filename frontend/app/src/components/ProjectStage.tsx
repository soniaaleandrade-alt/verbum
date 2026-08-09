import { useEffect, useMemo, useState } from 'react';
import { completeWorkProject, getWorkProject, saveWorkProject } from '../services/library-service';
import type { WorkProjectInput, WorkProjectObjective, WorkProjectProgress, WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import { WorkspaceFooter } from './WorkspaceFooter';

type SaveState = 'saved' | 'dirty' | 'saving' | 'error';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onDirtyChange: (dirty: boolean) => void;
  onPersisted: () => void | Promise<void>;
};

type FormState = {
  generalObjective: string;
  specificObjectives: WorkProjectObjective[];
  purpose: string;
  audience: string;
  benefits: string;
  transformation: string;
  centralMessage: string;
  differentials: string;
  valueProposition: string;
  keyword: string;
  motivation: string;
  verse: string;
  guidingPhrase: string;
};

const emptyForm: FormState = {
  generalObjective: '', specificObjectives: [], purpose: '', audience: '', benefits: '', transformation: '',
  centralMessage: '', differentials: '', valueProposition: '', keyword: '', motivation: '', verse: '', guidingPhrase: '',
};

const checklistLabels = [
  ['generalObjective', 'Objetivo geral'], ['specificObjectives', 'Objetivos específicos'], ['purpose', 'Finalidade'],
  ['audience', 'Público'], ['benefits', 'Benefícios'], ['transformation', 'Transformação'], ['centralMessage', 'Mensagem central'],
  ['differentials', 'Diferenciais'], ['valueProposition', 'Proposta de valor'], ['motivation', 'Motivação'], ['verse', 'Versículo'],
  ['guidingPhrase', 'Frase norteadora'],
] as const;

function formFromStage(stage: WorkProjectProgress): FormState {
  return { ...emptyForm, ...stage.values, specificObjectives: stage.values.specificObjectives || [] };
}

export function ProjectStage({ workspace, onWorkspaceChange, onStageChange, onDirtyChange, onPersisted }: Props) {
  const [stage, setStage] = useState<WorkProjectProgress | null>(null);
  const [form, setForm] = useState<FormState>(emptyForm);
  const [loading, setLoading] = useState(true);
  const [saveState, setSaveState] = useState<SaveState>('saved');
  const [message, setMessage] = useState('');

  useEffect(() => {
    let active = true;
    setLoading(true);
    getWorkProject(workspace.book.id).then((data) => {
      if (!active) return;
      setStage(data);
      setForm(formFromStage(data));
      setLoading(false);
    }).catch((cause) => {
      if (!active) return;
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível carregar o Projeto da Obra.');
      setSaveState('error');
      setLoading(false);
    });
    return () => { active = false; };
  }, [workspace.book.id]);

  const checklist = useMemo(() => {
    const completed: Record<string, boolean> = {
      generalObjective: form.generalObjective.trim() !== '',
      specificObjectives: form.specificObjectives.some((item) => item.text.trim() !== ''),
      purpose: form.purpose.trim() !== '', audience: form.audience.trim() !== '', benefits: form.benefits.trim() !== '',
      transformation: form.transformation.trim() !== '', centralMessage: form.centralMessage.trim() !== '', differentials: form.differentials.trim() !== '',
      valueProposition: form.valueProposition.trim() !== '', motivation: form.motivation.trim() !== '', verse: form.verse.trim() !== '', guidingPhrase: form.guidingPhrase.trim() !== '',
    };
    return checklistLabels.map(([key, label]) => ({ key, label, completed: completed[key] }));
  }, [form]);

  const completedCount = checklist.filter((item) => item.completed).length;
  const progress = Math.round((completedCount / checklist.length) * 100);
  const ready = completedCount === checklist.length;
  const completed = Boolean(stage?.completed) && saveState !== 'dirty';

  function update<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
    setSaveState('dirty');
    setMessage('');
    onDirtyChange(true);
  }

  function addObjective() {
    update('specificObjectives', [...form.specificObjectives, { id: `new-${Date.now()}`, text: '', order: form.specificObjectives.length + 1 }]);
  }

  function updateObjective(id: string, text: string) {
    update('specificObjectives', form.specificObjectives.map((item) => item.id === id ? { ...item, text } : item));
  }

  function removeObjective(id: string) {
    update('specificObjectives', form.specificObjectives.filter((item) => item.id !== id).map((item, index) => ({ ...item, order: index + 1 })));
  }

  function moveObjective(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= form.specificObjectives.length) return;
    const next = [...form.specificObjectives];
    [next[index], next[target]] = [next[target], next[index]];
    update('specificObjectives', next.map((item, itemIndex) => ({ ...item, order: itemIndex + 1 })));
  }

  function payload(): WorkProjectInput {
    return {
      general_objective: form.generalObjective.trim(), purpose: form.purpose.trim(), audience: form.audience.trim(), benefits: form.benefits.trim(),
      transformation: form.transformation.trim(), central_message: form.centralMessage.trim(), differentials: form.differentials.trim(),
      value_proposition: form.valueProposition.trim(), keyword: form.keyword.trim(), motivation: form.motivation.trim(), verse: form.verse.trim(),
      guiding_phrase: form.guidingPhrase.trim(), specific_objectives: form.specificObjectives.map((item, index) => ({ id: item.id, text: item.text.trim(), order: index + 1 })),
    };
  }

  async function persist() {
    setSaveState('saving');
    setMessage('');
    try {
      const result = await saveWorkProject(workspace.book.id, payload());
      setStage(result.projectStage);
      setForm(formFromStage(result.projectStage));
      onWorkspaceChange(result.workspace);
      onDirtyChange(false);
      setSaveState('saved');
      setMessage('Alterações salvas.');
      await onPersisted();
      return result;
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível salvar o Projeto da Obra.');
      throw cause;
    }
  }

  async function finish() {
    try {
      if (saveState === 'dirty' || saveState === 'error') await persist();
      if (!ready) {
        setMessage('Complete os 12 itens do Projeto da Obra antes de continuar.');
        return;
      }
      setSaveState('saving');
      const result = await completeWorkProject(workspace.book.id);
      setStage(result.projectStage);
      onWorkspaceChange(result.workspace);
      onDirtyChange(false);
      setSaveState('saved');
      await onPersisted();
      onStageChange('planning');
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível concluir o Projeto da Obra.');
    }
  }

  if (loading) return <section className="verbum-stage-content verbum-project-stage-state">Carregando Projeto da Obra...</section>;

  return (
    <>
      <section className="verbum-stage-content verbum-project-stage">
        <header className="verbum-project-stage-heading">
          <h2>Projeto da Obra</h2>
          <p>Objetivos, público-alvo, metodologia e mensagem central.</p>
        </header>
        <div className="verbum-project-stage-layout">
          <form className="verbum-project-stage-form" onSubmit={(event) => { event.preventDefault(); persist().catch(() => undefined); }}>
            <section className="verbum-project-card">
              <h3><span>◎</span> Propósito da Obra</h3>
              <label><span>Objetivo Geral <b>*</b></span><textarea rows={4} value={form.generalObjective} onChange={(e) => update('generalObjective', e.target.value)} /></label>
              <div className="verbum-specific-objectives">
                <div className="verbum-field-row"><span>Objetivos Específicos</span><button type="button" className="verbum-text-button" onClick={addObjective}>＋ Adicionar objetivo</button></div>
                {form.specificObjectives.length === 0 ? <p className="verbum-project-empty-copy">Nenhum objetivo específico ainda. Clique em “Adicionar objetivo”.</p> : form.specificObjectives.map((item, index) => (
                  <div className="verbum-objective-row" key={item.id}>
                    <textarea rows={2} value={item.text} onChange={(e) => updateObjective(item.id, e.target.value)} placeholder={`Objetivo específico ${index + 1}`} />
                    <div><button type="button" onClick={() => moveObjective(index, -1)} disabled={index === 0} aria-label="Mover objetivo para cima">↑</button><button type="button" onClick={() => moveObjective(index, 1)} disabled={index === form.specificObjectives.length - 1} aria-label="Mover objetivo para baixo">↓</button><button type="button" className="is-danger" onClick={() => removeObjective(item.id)}>Remover</button></div>
                  </div>
                ))}
              </div>
              <label><span>Finalidade da Obra</span><textarea rows={4} value={form.purpose} onChange={(e) => update('purpose', e.target.value)} /></label>
            </section>

            <section className="verbum-project-card">
              <h3><span>♧</span> Público e Impacto</h3>
              <label><span>Público-Alvo <b>*</b></span><textarea rows={4} value={form.audience} onChange={(e) => update('audience', e.target.value)} placeholder="Para quem se destina esta obra?" /></label>
              <label><span>Benefícios Esperados para o Leitor</span><textarea rows={3} value={form.benefits} onChange={(e) => update('benefits', e.target.value)} placeholder="O que o leitor ganha ao ler esta obra?" /></label>
              <label><span>Transformação Esperada</span><textarea rows={3} value={form.transformation} onChange={(e) => update('transformation', e.target.value)} placeholder="O que desejo que o leitor seja capaz de compreender ou viver após concluir esta obra?" /></label>
            </section>

            <section className="verbum-project-card">
              <h3><span>◉</span> Identidade da Obra</h3>
              <label><span>Mensagem Central <b>*</b></span><textarea rows={3} value={form.centralMessage} onChange={(e) => update('centralMessage', e.target.value)} placeholder="Qual é a mensagem que o leitor deve levar consigo?" /></label>
              <label><span>Diferenciais da Obra</span><textarea rows={3} value={form.differentials} onChange={(e) => update('differentials', e.target.value)} placeholder="O que torna esta obra única em relação às demais?" /></label>
              <label><span>Proposta de Valor</span><textarea rows={3} value={form.valueProposition} onChange={(e) => update('valueProposition', e.target.value)} placeholder="Qual é a proposta de valor da obra?" /></label>
              <label><span>Palavra-chave da Obra</span><input value={form.keyword} onChange={(e) => update('keyword', e.target.value)} placeholder="Ex.: fé, esperança, espiritualidade..." /><small>Utilizada para indexação interna.</small></label>
            </section>

            <section className="verbum-project-card">
              <h3><span>✣</span> Inspiração</h3>
              <label><span>Motivação Pessoal</span><textarea rows={3} value={form.motivation} onChange={(e) => update('motivation', e.target.value)} placeholder="Por que desejo escrever esta obra?" /></label>
              <label><span>Versículo Inspirador</span><input value={form.verse} onChange={(e) => update('verse', e.target.value)} placeholder="Ex.: Jo 1,1 — No princípio era o Verbo..." /></label>
              <label><span>Frase Norteadora</span><textarea rows={3} value={form.guidingPhrase} onChange={(e) => update('guidingPhrase', e.target.value)} placeholder="A frase que guiará toda a obra..." /><small>Esta frase será utilizada futuramente em relatórios internos.</small></label>
            </section>
            {message && <p className={`verbum-project-message${saveState === 'error' ? ' is-error' : ''}`} role="status">{message}</p>}
          </form>

          <aside className="verbum-project-progress" aria-label="Progresso do Projeto da Obra">
            <div className="verbum-progress-heading"><h3>Progresso da Etapa</h3><strong>{progress}%</strong></div>
            <div className="verbum-progress-track"><span style={{ width: `${progress}%` }} /></div>
            <p>{completedCount} de {checklist.length} itens concluídos</p>
            <div className="verbum-project-checklist">
              {checklist.map((item) => <div key={item.key} className={item.completed ? 'is-completed' : ''}><span>{item.completed ? '✓' : ''}</span><span>{item.label}</span></div>)}
            </div>
          </aside>
        </div>
      </section>
      <WorkspaceFooter
        canGoBack
        onPrevious={() => onStageChange('identification')}
        onBackToLibrary={() => undefined}
        saveState={saveState}
        saveDisabled={saveState !== 'dirty' && saveState !== 'error'}
        continueDisabled={!ready || saveState === 'saving' || completed}
        continueLabel={completed ? 'Etapa concluída ✓' : ready ? 'Concluir etapa ›' : 'Salvar e continuar ›'}
        onSave={() => persist().catch(() => undefined)}
        onContinue={finish}
      />
    </>
  );
}
