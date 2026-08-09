import { useEffect, useMemo, useRef, useState } from 'react';
import {
  completeIdentification,
  removeBookCover,
  saveIdentification,
  uploadBookCover,
} from '../services/library-service';
import type { IdentificationInput, WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import { WorkspaceFooter } from './WorkspaceFooter';

type SaveState = 'saved' | 'dirty' | 'saving' | 'error';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onBackToLibrary: () => void;
  onDirtyChange: (dirty: boolean) => void;
  onPersisted: () => void | Promise<void>;
};

type FormState = {
  title: string;
  subtitle: string;
  workflowStatus: string;
  genre: string;
  language: string;
  audience: string;
  synopsis: string;
  keywords: string;
  color: string;
};

const statuses = ['Planejamento', 'Em andamento', 'Em pausa'];
const genres = ['Formação', 'Ensaio', 'Romance', 'Biografia', 'Devocional', 'Manual', 'Catequético', 'Acadêmico', 'Testemunho', 'Meditação', 'Não ficção', 'Ficção', 'Outro'];
const languages = ['Português (BR)', 'Português (PT)', 'Espanhol', 'Inglês', 'Italiano', 'Francês', 'Outro'];
const audiences = ['Leitores em geral', 'Crianças', 'Adolescentes', 'Jovens', 'Adultos', 'Famílias', 'Estudantes', 'Profissionais', 'Leigos', 'Clero e vida consagrada', 'Outro'];
const colors = ['#15677a', '#9b6b2f', '#4f825e', '#8b38d1', '#c41f25', '#244bb8', '#bf1f62', '#187a6f', '#c84b0a', '#4d3dc4'];

const checklistLabels = [
  ['title', 'Definir título'],
  ['subtitle', 'Definir subtítulo'],
  ['synopsis', 'Escrever sinopse'],
  ['keywords', 'Definir palavras-chave'],
  ['workflowStatus', 'Definir status'],
  ['genre', 'Definir gênero'],
  ['language', 'Definir idioma'],
  ['audience', 'Definir público-alvo'],
  ['color', 'Escolher cor da obra'],
  ['cover', 'Enviar capa da obra'],
] as const;

function initialForm(workspace: WorkWorkspaceData): FormState {
  const book = workspace.book;
  const language = book.language === 'Português' ? 'Português (BR)' : (book.language || 'Português (BR)');
  return {
    title: book.title || '',
    subtitle: book.subtitle || '',
    workflowStatus: book.workflowStatus || 'Planejamento',
    genre: book.genre || '',
    language,
    audience: book.audience || '',
    synopsis: book.synopsis || '',
    keywords: Array.isArray(book.keywords) ? book.keywords.join(', ') : (book.keyword || ''),
    color: book.color || '',
  };
}

function withCurrent(options: string[], current: string) {
  return current && !options.includes(current) ? [current, ...options] : options;
}

export function IdentificationStage({ workspace, onWorkspaceChange, onStageChange, onBackToLibrary, onDirtyChange, onPersisted }: Props) {
  const [form, setForm] = useState<FormState>(() => initialForm(workspace));
  const [saveState, setSaveState] = useState<SaveState>('saved');
  const [message, setMessage] = useState('');
  const [coverBusy, setCoverBusy] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (saveState === 'dirty') return;
    setForm(initialForm(workspace));
  }, [workspace, saveState]);

  const checklist = useMemo(() => {
    const values: Record<string, boolean> = {
      title: form.title.trim() !== '',
      subtitle: form.subtitle.trim() !== '',
      synopsis: form.synopsis.trim() !== '',
      keywords: form.keywords.split(',').some((item) => item.trim() !== ''),
      workflowStatus: form.workflowStatus.trim() !== '',
      genre: form.genre.trim() !== '',
      language: form.language.trim() !== '',
      audience: form.audience.trim() !== '',
      color: form.color.trim() !== '',
      cover: Boolean(workspace.book.coverUrl),
    };
    return checklistLabels.map(([key, label]) => ({ key, label, completed: values[key] }));
  }, [form, workspace.book.coverUrl]);

  const completedCount = checklist.filter((item) => item.completed).length;
  const progress = completedCount * 10;
  const ready = completedCount === checklist.length;
  const completed = workspace.identification.completed && saveState !== 'dirty';

  function update<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
    setSaveState('dirty');
    setMessage('');
    onDirtyChange(true);
  }

  function payload(): IdentificationInput {
    return {
      title: form.title.trim(),
      subtitle: form.subtitle.trim(),
      workflow_status: form.workflowStatus,
      genre: form.genre,
      language: form.language,
      audience: form.audience,
      synopsis: form.synopsis.trim(),
      keywords: form.keywords.split(',').map((item) => item.trim()).filter(Boolean),
      color: form.color,
    };
  }

  async function persist() {
    setSaveState('saving');
    setMessage('');
    try {
      const updated = await saveIdentification(workspace.book.id, payload());
      onWorkspaceChange(updated);
      onDirtyChange(false);
      setSaveState('saved');
      setMessage('Alterações salvas.');
      await onPersisted();
      return updated;
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível salvar as alterações.');
      throw cause;
    }
  }

  async function finish() {
    try {
      let current = workspace;
      if (saveState === 'dirty' || saveState === 'error') current = await persist();
      if (!current.identification.ready) {
        setMessage('Complete os itens pendentes da Identificação antes de continuar.');
        return;
      }
      setSaveState('saving');
      const updated = await completeIdentification(workspace.book.id);
      onWorkspaceChange(updated);
      onDirtyChange(false);
      setSaveState('saved');
      setMessage('Identificação concluída. Projeto da Obra foi liberado.');
      await onPersisted();
      onStageChange('project');
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível concluir a Identificação.');
    }
  }

  async function upload(file: File) {
    setMessage('');
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      setMessage('Use uma imagem JPG, JPEG, PNG ou WebP para a capa.');
      return;
    }
    if (file.size > 10 * 1024 * 1024) {
      setMessage('A capa deve ter no máximo 10 MB.');
      return;
    }
    setCoverBusy(true);
    try {
      const updated = await uploadBookCover(workspace.book.id, file);
      onWorkspaceChange(updated);
      await onPersisted();
      setMessage('Capa atualizada.');
    } catch (cause) {
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível enviar a capa.');
    } finally {
      setCoverBusy(false);
      if (fileInput.current) fileInput.current.value = '';
    }
  }

  async function removeCover() {
    if (!workspace.book.coverUrl || !window.confirm('Remover a capa desta obra?')) return;
    setCoverBusy(true);
    setMessage('');
    try {
      const updated = await removeBookCover(workspace.book.id);
      onWorkspaceChange(updated);
      await onPersisted();
      setMessage('Capa removida.');
    } catch (cause) {
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível remover a capa.');
    } finally {
      setCoverBusy(false);
    }
  }

  return (
    <>
      <section className="verbum-stage-content verbum-identification-stage">
        <div className="verbum-identification-layout">
          <form className="verbum-identification-card" onSubmit={(event) => { event.preventDefault(); persist().catch(() => undefined); }}>
            <div className="verbum-identification-grid">
              <label>
                <span>Título</span>
                <input value={form.title} onChange={(event) => update('title', event.target.value)} />
              </label>
              <label>
                <span>Subtítulo</span>
                <input value={form.subtitle} onChange={(event) => update('subtitle', event.target.value)} />
              </label>
              <label>
                <span>Status</span>
                <select value={form.workflowStatus} onChange={(event) => update('workflowStatus', event.target.value)}>
                  {withCurrent(statuses, form.workflowStatus).map((option) => <option key={option}>{option}</option>)}
                </select>
              </label>
              <label>
                <span>Gênero</span>
                <select value={form.genre} onChange={(event) => update('genre', event.target.value)}>
                  <option value="">Selecione...</option>
                  {withCurrent(genres, form.genre).map((option) => <option key={option}>{option}</option>)}
                </select>
              </label>
              <label>
                <span>Idioma</span>
                <select value={form.language} onChange={(event) => update('language', event.target.value)}>
                  {withCurrent(languages, form.language).map((option) => <option key={option}>{option}</option>)}
                </select>
              </label>
              <label>
                <span>Público-alvo</span>
                <select value={form.audience} onChange={(event) => update('audience', event.target.value)}>
                  <option value="">Selecione...</option>
                  {withCurrent(audiences, form.audience).map((option) => <option key={option}>{option}</option>)}
                </select>
              </label>
              <label className="verbum-identification-span-2">
                <span>Sinopse</span>
                <textarea rows={5} value={form.synopsis} onChange={(event) => update('synopsis', event.target.value)} />
              </label>
              <label>
                <span>Palavras-chave</span>
                <input value={form.keywords} onChange={(event) => update('keywords', event.target.value)} placeholder="sacerdote, leigos, católicos" />
                <small>Separe as palavras-chave por vírgula.</small>
              </label>
              <div className="verbum-cover-field">
                <span className="verbum-field-label">Capa da obra</span>
                <div className="verbum-cover-editor">
                  <div className="verbum-cover-preview" style={{ backgroundColor: form.color || '#15677a' }}>
                    {workspace.book.coverUrl ? <img src={workspace.book.coverUrl} alt={`Capa de ${form.title || 'obra'}`} /> : <span>V</span>}
                  </div>
                  <button
                    type="button"
                    className="verbum-cover-dropzone"
                    disabled={coverBusy}
                    onClick={() => fileInput.current?.click()}
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => {
                      event.preventDefault();
                      const file = event.dataTransfer.files?.[0];
                      if (file) upload(file);
                    }}
                  >
                    <strong>{coverBusy ? 'Enviando...' : '↻'}</strong>
                    <span>Arraste para substituir ou clique em “Trocar imagem”</span>
                  </button>
                </div>
                <div className="verbum-cover-actions">
                  <button type="button" className="verbum-secondary-button" disabled={coverBusy} onClick={() => fileInput.current?.click()}>Trocar imagem</button>
                  <button type="button" className="verbum-text-button is-danger" disabled={coverBusy || !workspace.book.coverUrl} onClick={removeCover}>Remover</button>
                </div>
                <input ref={fileInput} className="sr-only" type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => { const file = event.target.files?.[0]; if (file) upload(file); }} />
              </div>
              <div className="verbum-identification-span-2 verbum-color-field">
                <span className="verbum-field-label">Cor da obra</span>
                <div className="verbum-color-palette" role="radiogroup" aria-label="Cor da obra">
                  {colors.map((option) => (
                    <button
                      key={option}
                      type="button"
                      role="radio"
                      aria-checked={form.color === option}
                      className={form.color === option ? 'is-selected' : ''}
                      style={{ backgroundColor: option }}
                      onClick={() => update('color', option)}
                      aria-label={`Selecionar cor ${option}`}
                    />
                  ))}
                </div>
              </div>
            </div>
            {message && <p className={`verbum-identification-message${saveState === 'error' ? ' is-error' : ''}`} role="status">{message}</p>}
          </form>

          <aside className="verbum-identification-progress" aria-label="Progresso da Identificação">
            <div className="verbum-progress-heading"><h3>Progresso da Etapa</h3><strong>{progress}%</strong></div>
            <div className="verbum-progress-track"><span style={{ width: `${progress}%` }} /></div>
            <p>{completedCount} de {checklist.length} itens concluídos</p>
            <div className="verbum-identification-checklist">
              {checklist.map((item) => (
                <div key={item.key} className={item.completed ? 'is-completed' : ''}>
                  <span aria-hidden="true">{item.completed ? '✓' : ''}</span>
                  <span>{item.label}</span>
                </div>
              ))}
            </div>
          </aside>
        </div>
      </section>
      <WorkspaceFooter
        canGoBack={false}
        onPrevious={onBackToLibrary}
        onBackToLibrary={onBackToLibrary}
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
