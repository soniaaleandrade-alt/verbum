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
  genre: string;
  language: string;
  synopsis: string;
  color: string;
};

const genres = ['Formação', 'Ensaio', 'Romance', 'Biografia', 'Devocional', 'Manual', 'Catequético', 'Acadêmico', 'Testemunho', 'Meditação', 'Não ficção', 'Ficção', 'Outro'];
const languages = ['Português (BR)', 'Português (PT)', 'Espanhol', 'Inglês', 'Italiano', 'Francês', 'Outro'];
const colors = ['#15677a', '#9b6b2f', '#4f825e', '#8b38d1', '#c41f25', '#244bb8', '#bf1f62', '#187a6f', '#c84b0a', '#4d3dc4'];

function initialForm(workspace: WorkWorkspaceData): FormState {
  const book = workspace.book;
  const language = book.language === 'Português' ? 'Português (BR)' : (book.language || 'Português (BR)');
  return {
    title: book.title || '',
    subtitle: book.subtitle || '',
    genre: book.genre || '',
    language,
    synopsis: book.synopsis || '',
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
  const autoSaveTimer = useRef<number | null>(null);

  useEffect(() => {
    if (saveState === 'dirty' || saveState === 'saving') return;
    setForm(initialForm(workspace));
  }, [workspace, saveState]);

  const essentialItems = useMemo(() => [
    { key: 'title', label: 'Título provisório', completed: form.title.trim() !== '' },
    { key: 'genre', label: 'Gênero', completed: form.genre.trim() !== '' },
    { key: 'language', label: 'Idioma', completed: form.language.trim() !== '' },
  ], [form.title, form.genre, form.language]);

  const optionalItems = useMemo(() => [
    { key: 'subtitle', label: 'Subtítulo provisório', completed: form.subtitle.trim() !== '' },
    { key: 'synopsis', label: 'Descrição inicial', completed: form.synopsis.trim() !== '' },
    { key: 'cover', label: 'Capa provisória', completed: Boolean(workspace.book.coverUrl) },
    { key: 'color', label: 'Cor de identificação', completed: form.color.trim() !== '' },
  ], [form.subtitle, form.synopsis, form.color, workspace.book.coverUrl]);

  const completedCount = essentialItems.filter((item) => item.completed).length;
  const progress = Math.round((completedCount / essentialItems.length) * 100);
  const ready = completedCount === essentialItems.length;

  function clearAutoSave() {
    if (autoSaveTimer.current !== null) {
      window.clearTimeout(autoSaveTimer.current);
      autoSaveTimer.current = null;
    }
  }

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
      genre: form.genre,
      language: form.language,
      synopsis: form.synopsis.trim(),
      color: form.color,
    } as IdentificationInput;
  }

  async function persist() {
    clearAutoSave();
    setSaveState('saving');
    setMessage('');
    try {
      const updated = await saveIdentification(workspace.book.id, payload());
      onWorkspaceChange(updated);
      onDirtyChange(false);
      setSaveState('saved');
      await onPersisted();
      return updated;
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível salvar as alterações.');
      throw cause;
    }
  }

  useEffect(() => {
    if (saveState !== 'dirty') return;
    clearAutoSave();
    autoSaveTimer.current = window.setTimeout(() => {
      persist().catch(() => undefined);
    }, 900);
    return clearAutoSave;
  }, [form, saveState]);

  async function finish() {
    clearAutoSave();
    try {
      let current = workspace;
      if (saveState === 'dirty' || saveState === 'error') current = await persist();
      if (!current.identification.ready) {
        const missing = essentialItems.filter((item) => !item.completed).map((item) => item.label).join(', ');
        setMessage(`Preencha os campos essenciais antes de continuar: ${missing}.`);
        return;
      }
      setSaveState('saving');
      const updated = await completeIdentification(workspace.book.id);
      onWorkspaceChange(updated);
      onDirtyChange(false);
      setSaveState('saved');
      await onPersisted();
      // Compatibilidade temporária: Fundação usa a rota legada "project" até a nova página ser implementada.
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
    clearAutoSave();
    setCoverBusy(true);
    setSaveState('saving');
    try {
      const updated = await uploadBookCover(workspace.book.id, file);
      onWorkspaceChange(updated);
      onDirtyChange(false);
      setSaveState('saved');
      await onPersisted();
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível enviar a capa.');
    } finally {
      setCoverBusy(false);
      if (fileInput.current) fileInput.current.value = '';
    }
  }

  async function removeCover() {
    if (!workspace.book.coverUrl || !window.confirm('Remover a capa provisória desta obra?')) return;
    clearAutoSave();
    setCoverBusy(true);
    setSaveState('saving');
    setMessage('');
    try {
      const updated = await removeBookCover(workspace.book.id);
      onWorkspaceChange(updated);
      onDirtyChange(false);
      setSaveState('saved');
      await onPersisted();
    } catch (cause) {
      setSaveState('error');
      setMessage(cause instanceof Error ? cause.message : 'Não foi possível remover a capa.');
    } finally {
      setCoverBusy(false);
    }
  }

  return (
    <>
      <section className="verbum-stage-content verbum-identification-stage">
        <header className="verbum-identification-heading">
          <h2>Identificação da Obra</h2>
          <p>Cadastre o essencial para identificar sua obra. As decisões de Fundação serão desenvolvidas na próxima etapa.</p>
        </header>
        <div className="verbum-identification-layout">
          <form className="verbum-identification-card" onSubmit={(event) => { event.preventDefault(); persist().catch(() => undefined); }}>
            <section className="verbum-identification-section">
              <div className="verbum-identification-section-title"><span>1</span><div><h3>Dados essenciais</h3><p>Estes três campos liberam a continuação da obra.</p></div></div>
              <div className="verbum-identification-grid is-essential">
                <label className="verbum-identification-span-2">
                  <span>Título provisório <b>*</b></span>
                  <input value={form.title} onChange={(event) => update('title', event.target.value)} placeholder="Digite o título provisório da obra" />
                </label>
                <label>
                  <span>Gênero <b>*</b></span>
                  <select value={form.genre} onChange={(event) => update('genre', event.target.value)}>
                    <option value="">Selecione...</option>
                    {withCurrent(genres, form.genre).map((option) => <option key={option}>{option}</option>)}
                  </select>
                </label>
                <label>
                  <span>Idioma <b>*</b></span>
                  <select value={form.language} onChange={(event) => update('language', event.target.value)}>
                    {withCurrent(languages, form.language).map((option) => <option key={option}>{option}</option>)}
                  </select>
                </label>
              </div>
            </section>

            <section className="verbum-identification-section is-optional">
              <div className="verbum-identification-section-title"><span>2</span><div><h3>Identificação complementar</h3><p>Campos opcionais que ajudam a reconhecer a obra no sistema.</p></div></div>
              <div className="verbum-identification-grid">
                <label className="verbum-identification-span-2">
                  <span>Subtítulo provisório <em>Opcional</em></span>
                  <input value={form.subtitle} onChange={(event) => update('subtitle', event.target.value)} placeholder="Digite um subtítulo, se desejar" />
                </label>
                <label className="verbum-identification-span-2">
                  <span>Descrição inicial da obra <em>Opcional</em></span>
                  <textarea rows={5} value={form.synopsis} onChange={(event) => update('synopsis', event.target.value)} placeholder="Registre brevemente a ideia inicial do livro" />
                  <small>Registre brevemente a ideia do livro. Este texto poderá ser revisto durante o desenvolvimento.</small>
                </label>

                <div className="verbum-cover-field">
                  <span className="verbum-field-label">Capa provisória <em>Opcional</em></span>
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
                      <span>Arraste uma imagem ou clique para enviar</span>
                    </button>
                  </div>
                  <div className="verbum-cover-actions">
                    <button type="button" className="verbum-secondary-button" disabled={coverBusy} onClick={() => fileInput.current?.click()}>{workspace.book.coverUrl ? 'Trocar imagem' : 'Enviar imagem'}</button>
                    <button type="button" className="verbum-text-button is-danger" disabled={coverBusy || !workspace.book.coverUrl} onClick={removeCover}>Remover</button>
                  </div>
                  <input ref={fileInput} className="sr-only" type="file" accept="image/jpeg,image/png,image/webp" onChange={(event) => { const file = event.target.files?.[0]; if (file) upload(file); }} />
                </div>

                <div className="verbum-color-field">
                  <span className="verbum-field-label">Cor de identificação no sistema <em>Opcional</em></span>
                  <div className="verbum-color-palette" role="radiogroup" aria-label="Cor de identificação da obra">
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
            </section>
            {message && <p className={`verbum-identification-message${saveState === 'error' ? ' is-error' : ''}`} role="status">{message}</p>}
          </form>

          <aside className="verbum-identification-progress" aria-label="Progresso da Identificação">
            <div className="verbum-progress-heading"><h3>{ready ? 'Identificação pronta para continuar' : 'Identificação em andamento'}</h3><strong>{progress}%</strong></div>
            <div className="verbum-progress-track"><span style={{ width: `${progress}%` }} /></div>
            <p>{completedCount} de 3 itens essenciais preenchidos</p>
            <div className="verbum-identification-checklist">
              {essentialItems.map((item) => (
                <div key={item.key} className={item.completed ? 'is-completed' : ''}>
                  <span aria-hidden="true">{item.completed ? '✓' : ''}</span>
                  <span>{item.label}</span>
                </div>
              ))}
            </div>
            {!ready && <div className="verbum-identification-missing"><strong>Para continuar:</strong>{essentialItems.filter((item) => !item.completed).map((item) => <span key={item.key}>{item.label}</span>)}</div>}
            <div className="verbum-identification-optional-progress">
              <strong>Opcionais preenchidos</strong>
              {optionalItems.map((item) => <div key={item.key} className={item.completed ? 'is-completed' : ''}><span>{item.completed ? '✓' : '○'}</span><span>{item.label}</span></div>)}
            </div>
          </aside>
        </div>
      </section>
      <WorkspaceFooter
        canGoBack={false}
        hidePrevious
        onPrevious={onBackToLibrary}
        onBackToLibrary={onBackToLibrary}
        saveState={saveState}
        hideSaveButton
        saveStateLabels={{ saved: 'Alterações salvas', dirty: 'Aguardando salvamento...', saving: 'Salvando...', error: 'Erro ao salvar' }}
        continueDisabled={!ready || saveState === 'saving' || coverBusy}
        continueLabel="Continuar para Fundação"
        onSave={() => persist().catch(() => undefined)}
        onContinue={finish}
      />
    </>
  );
}
