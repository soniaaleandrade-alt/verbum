import type { VerbumBook } from '../types/verbum';

type BookCardProps = {
  book: VerbumBook;
  projectName: string;
  onEdit: () => void;
  onArchive: () => void;
};

export function BookCard({ book, projectName, onEdit, onArchive }: BookCardProps) {
  const archived = book.status === 'archived';
  return (
    <article className={`verbum-book-card${archived ? ' is-archived' : ''}`}>
      <div className="verbum-book-color" style={{ backgroundColor: book.color || '#7a3042' }} aria-hidden="true" />
      <div className="verbum-book-card-body">
        <div className="verbum-book-card-topline">
          <span className="verbum-book-project">{projectName}</span>
          <span className={`verbum-status-chip ${archived ? 'is-muted' : 'is-active'}`}>{archived ? 'Arquivada' : (book.workflowStatus || 'Planejamento')}</span>
        </div>
        <h3>{book.title}</h3>
        {book.subtitle && <p className="verbum-book-subtitle">{book.subtitle}</p>}
        <div className="verbum-book-meta">
          <span>{book.genre || book.category || 'Sem gênero definido'}</span>
          <span>{book.plannedChapters ? `${book.plannedChapters} capítulos previstos` : 'Capítulos ainda não definidos'}</span>
        </div>
        <div className="verbum-book-stage">
          <span>Etapa atual</span>
          <strong>Identificação da Obra</strong>
        </div>
      </div>
      <div className="verbum-book-card-actions">
        <button type="button" className="verbum-text-button" onClick={onEdit}>Editar</button>
        {!archived && <button type="button" className="verbum-text-button is-danger" onClick={onArchive}>Arquivar</button>}
      </div>
    </article>
  );
}
