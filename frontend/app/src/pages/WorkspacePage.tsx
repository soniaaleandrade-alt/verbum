import { useMemo, useState } from 'react';
import type { CreateProjectInput, LibraryData } from '../types/verbum';

const TYPES = [
  ['book', 'Livro'],
  ['ebook', 'eBook'],
  ['devotional', 'Devocional'],
  ['article', 'Artigo / Ensaio'],
  ['course', 'Apostila / Curso'],
  ['academic', 'Projeto acadêmico'],
  ['other', 'Outro'],
] as const;

function decodeDescription(value: string) {
  const match = value.match(/^\[\[workspace_type:([a-z_]+)\]\]\s*/);
  return {
    type: match?.[1] ?? 'book',
    description: value.replace(/^\[\[workspace_type:[a-z_]+\]\]\s*/, '').trim(),
  };
}

function typeLabel(type: string) {
  return TYPES.find(([key]) => key === type)?.[1] ?? 'Outro';
}

type WorkspacePageProps = {
  data: LibraryData;
  loading: boolean;
  error: string;
  onReload: () => void;
  onCreateProject: (input: CreateProjectInput) => Promise<void>;
  onOpenLibrary: () => void;
};

export function WorkspacePage({ data, loading, error, onReload, onCreateProject, onOpenLibrary }: WorkspacePageProps) {
  const [dialogOpen, setDialogOpen] = useState(false);
  const [name, setName] = useState('');
  const [type, setType] = useState('book');
  const [description, setDescription] = useState('');
  const [saving, setSaving] = useState(false);
  const [actionError, setActionError] = useState('');

  const projects = useMemo(() => data.projects.filter((project) => project.status === 'active' && project.name !== 'Projeto Teste Verbum'), [data.projects]);
  const activeBooks = data.books.filter((book) => book.status === 'active');

  function closeDialog() {
    if (saving) return;
    setDialogOpen(false);
    setName('');
    setType('book');
    setDescription('');
    setActionError('');
  }

  async function submit(event: React.FormEvent) {
    event.preventDefault();
    if (!name.trim()) {
      setActionError('Informe o nome da Área de Trabalho.');
      return;
    }
    setSaving(true);
    setActionError('');
    try {
      await onCreateProject({
        name: name.trim(),
        description: `[[workspace_type:${type}]]\n${description.trim()}`,
      });
      closeDialog();
    } catch (cause) {
      setActionError(cause instanceof Error ? cause.message : 'Não foi possível criar a Área de Trabalho.');
      setSaving(false);
    }
  }

  return (
    <div className="verbum-workspace-manager">
      <section className="verbum-page-heading verbum-workspace-manager-heading">
        <div>
          <span className="verbum-eyebrow">Workspace editorial</span>
          <h2>Área de Trabalho</h2>
          <p>Organize seus projetos editoriais e as obras vinculadas a cada um.</p>
        </div>
        <div className="verbum-page-actions">
          <button type="button" className="verbum-primary-button" onClick={() => setDialogOpen(true)}>+ Nova Área de Trabalho</button>
        </div>
      </section>

      <section className="verbum-workspace-manager-summary" aria-label="Resumo da Área de Trabalho">
        <div><strong>{projects.length}</strong><span>Áreas ativas</span></div>
        <div><strong>{activeBooks.length}</strong><span>Obras ativas</span></div>
        <div><strong>{TYPES.length}</strong><span>Tipos disponíveis</span></div>
      </section>

      {error && <div className="verbum-inline-error" role="alert"><span>{error}</span><button type="button" onClick={onReload}>Tentar novamente</button></div>}

      {loading ? (
        <section className="verbum-library-loading">Carregando Área de Trabalho...</section>
      ) : projects.length === 0 ? (
        <section className="verbum-panel verbum-workspace-manager-empty">
          <div>
            <span className="verbum-eyebrow">Primeiro passo</span>
            <h3>Crie sua primeira Área de Trabalho</h3>
            <p>Depois, você poderá cadastrar uma ou mais obras dentro dela.</p>
          </div>
          <button type="button" className="verbum-primary-button" onClick={() => setDialogOpen(true)}>+ Nova Área de Trabalho</button>
        </section>
      ) : (
        <div className="verbum-workspace-manager-grid">
          {projects.map((project) => {
            const details = decodeDescription(project.description);
            const books = activeBooks.filter((book) => book.projectId === project.id);
            return (
              <article key={project.id} className="verbum-panel verbum-workspace-manager-card">
                <div className="verbum-workspace-manager-card-meta">
                  <span>{typeLabel(details.type)}</span>
                  <small>{books.length} {books.length === 1 ? 'obra' : 'obras'}</small>
                </div>
                <h3>{project.name}</h3>
                <p>{details.description || 'Área editorial para organizar suas obras e acompanhar o desenvolvimento.'}</p>
                <div className="verbum-workspace-manager-card-footer">
                  <button type="button" className="verbum-secondary-button" onClick={onOpenLibrary}>Ver obras</button>
                </div>
              </article>
            );
          })}
        </div>
      )}

      {dialogOpen && (
        <div className="verbum-dialog-backdrop" role="presentation" onMouseDown={(event) => event.currentTarget === event.target && closeDialog()}>
          <section className="verbum-dialog" role="dialog" aria-modal="true" aria-labelledby="verbum-workspace-dialog-title">
            <div className="verbum-dialog-header">
              <div>
                <span className="verbum-eyebrow">Área de Trabalho</span>
                <h2 id="verbum-workspace-dialog-title">Criar nova Área de Trabalho</h2>
              </div>
              <button type="button" className="verbum-icon-button" onClick={closeDialog} aria-label="Fechar">×</button>
            </div>
            <form className="verbum-form" onSubmit={submit}>
              <label><span>Nome da área *</span><input value={name} onChange={(event) => setName(event.target.value)} maxLength={120} autoFocus /></label>
              <label><span>Tipo de projeto *</span><select value={type} onChange={(event) => setType(event.target.value)}>{TYPES.map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
              <label><span>Descrição</span><textarea value={description} onChange={(event) => setDescription(event.target.value)} rows={4} maxLength={900} /></label>
              {actionError && <p className="verbum-form-error" role="alert">{actionError}</p>}
              <div className="verbum-dialog-actions"><button type="button" className="verbum-secondary-button" onClick={closeDialog} disabled={saving}>Cancelar</button><button type="submit" className="verbum-primary-button" disabled={saving}>{saving ? 'Criando...' : 'Criar Área de Trabalho'}</button></div>
            </form>
          </section>
        </div>
      )}
    </div>
  );
}
