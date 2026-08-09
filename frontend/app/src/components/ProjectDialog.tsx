import { useEffect, useState } from 'react';
import type { CreateProjectInput, VerbumProject } from '../types/verbum';

type ProjectDialogProps = {
  open: boolean;
  project?: VerbumProject | null;
  onClose: () => void;
  onSave: (input: CreateProjectInput) => Promise<void>;
};

export function ProjectDialog({ open, project, onClose, onSave }: ProjectDialogProps) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!open) return;
    setName(project?.name ?? '');
    setDescription(project?.description ?? '');
    setError('');
  }, [open, project]);

  if (!open) return null;

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (!name.trim()) {
      setError('Informe o nome do projeto.');
      return;
    }

    setSaving(true);
    setError('');
    try {
      await onSave({ name: name.trim(), description: description.trim() });
      onClose();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível salvar o projeto.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="verbum-dialog-backdrop" role="presentation" onMouseDown={(event) => event.currentTarget === event.target && onClose()}>
      <section className="verbum-dialog" role="dialog" aria-modal="true" aria-labelledby="verbum-project-dialog-title">
        <div className="verbum-dialog-header">
          <div>
            <span className="verbum-eyebrow">Banco de Obras</span>
            <h2 id="verbum-project-dialog-title">{project ? 'Editar projeto' : 'Novo projeto'}</h2>
          </div>
          <button type="button" className="verbum-icon-button" onClick={onClose} aria-label="Fechar">×</button>
        </div>

        <form className="verbum-form" onSubmit={submit}>
          <label>
            <span>Nome do projeto *</span>
            <input value={name} onChange={(event) => setName(event.target.value)} maxLength={120} autoFocus />
          </label>
          <label>
            <span>Descrição</span>
            <textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={4} maxLength={1000} />
          </label>
          {error && <p className="verbum-form-error" role="alert">{error}</p>}
          <div className="verbum-dialog-actions">
            <button type="button" className="verbum-secondary-button" onClick={onClose} disabled={saving}>Cancelar</button>
            <button type="submit" className="verbum-primary-button" disabled={saving}>{saving ? 'Salvando...' : 'Salvar projeto'}</button>
          </div>
        </form>
      </section>
    </div>
  );
}
