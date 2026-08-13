import { useEffect, useState, type FormEvent } from 'react';
import type { CreateBookInput, VerbumBook, VerbumProject } from '../types/verbum';

type BookDialogProps = {
  open: boolean;
  projects: VerbumProject[];
  book?: VerbumBook | null;
  defaultProjectId?: string;
  onClose: () => void;
  onSave: (input: CreateBookInput) => Promise<void>;
};

type FormState = { projectId: string; title: string; subtitle: string };

const emptyForm: FormState = { projectId: '', title: '', subtitle: '' };

export function BookDialog({ open, projects, book, defaultProjectId, onClose, onSave }: BookDialogProps) {
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!open) return;
    setForm(book ? {
      projectId: book.projectId,
      title: book.title ?? '',
      subtitle: book.subtitle ?? '',
    } : {
      ...emptyForm,
      projectId: defaultProjectId || projects.find((project) => project.status === 'active')?.id || '',
    });
    setError('');
  }, [open, book, projects, defaultProjectId]);

  if (!open) return null;

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!form.projectId) {
      setError('Não foi possível preparar o cadastro da obra.');
      return;
    }
    if (!form.title.trim()) {
      setError('Informe o título da obra.');
      return;
    }

    setSaving(true);
    setError('');
    try {
      await onSave({
        project_id: Number(form.projectId),
        title: form.title.trim(),
        subtitle: form.subtitle.trim(),
      });
      onClose();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a obra.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="verbum-dialog-backdrop" role="presentation" onMouseDown={(event) => event.currentTarget === event.target && onClose()}>
      <section className="verbum-dialog" role="dialog" aria-modal="true" aria-labelledby="verbum-book-dialog-title">
        <div className="verbum-dialog-header">
          <div>
            <span className="verbum-eyebrow">Banco de Obras</span>
            <h2 id="verbum-book-dialog-title">{book ? 'Editar obra' : 'Criar nova obra'}</h2>
          </div>
          <button type="button" className="verbum-icon-button" onClick={onClose} aria-label="Fechar">×</button>
        </div>

        <form className="verbum-form" onSubmit={submit}>
          <div className="verbum-form-grid">
            <label>
              <span>Título *</span>
              <input value={form.title} onChange={(event) => set('title', event.target.value)} maxLength={180} autoFocus />
            </label>
            <label>
              <span>Subtítulo</span>
              <input value={form.subtitle} onChange={(event) => set('subtitle', event.target.value)} maxLength={220} />
            </label>
          </div>
          <p className="verbum-form-helper">Cadastre apenas os dados iniciais. A identificação editorial completa será preenchida dentro do Workspace da Obra.</p>
          {error && <p className="verbum-form-error" role="alert">{error}</p>}
          <div className="verbum-dialog-actions">
            <button type="button" className="verbum-secondary-button" onClick={onClose} disabled={saving}>Cancelar</button>
            <button type="submit" className="verbum-primary-button" disabled={saving}>{saving ? 'Salvando...' : 'Salvar obra'}</button>
          </div>
        </form>
      </section>
    </div>
  );
}
