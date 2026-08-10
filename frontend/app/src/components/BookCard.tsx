import type { WorkStageKey, VerbumBook } from '../types/verbum';

type BookCardProps = {
  book: VerbumBook;
  projectName: string;
  onOpen: () => void;
  onEdit: () => void;
  onArchive: () => void;
};

const stageLabels: Record<WorkStageKey, string> = {
  identification: 'Identificação',
  project: 'Projeto da Obra',
  planning: 'Planejamento',
  development: 'Desenvolvimento',
  general_review: 'Revisão Geral',
  versions: 'Controle de Versões',
  audit: 'Auditoria',
  editorial_desk: 'Mesa Editorial',
  layout: 'Diagramação',
  legal: 'Trâmites Legais',
  publication: 'Publicação',
};

export function BookCard({ book, projectName, onOpen, onEdit, onArchive }: BookCardProps) {
  const archived = book.status === 'archived';
  const stage = stageLabels[book.stage as WorkStageKey] ?? 'Identificação';
  return (
    <article className={`verbum-book-card${archived ? ' is-archived' : ''}`}>
      <div className="verbum-book-color" style={{ backgroundColor: book.color || '#7a3042' }} aria-hidden="true" />
      <div className="verbum-book-card-body">
        <div className="verbum-book-card-topline">
          <span className="verbum-book-project">{projectName}</span>
          <span className={`verbum-status-chip ${archived ? 'is-muted' : 'is-active'}`}>{archived ? 'Arquivada' : `Situação: ${book.workflowStatus || 'Planejamento'}`}</span>
        </div>
        <h3>{book.title}</h3>
        {book.subtitle && <p className="verbum-book-subtitle">{book.subtitle}</p>}
        <div className="verbum-book-meta">
          <span>{book.genre || book.category || 'Sem gênero definido'}</span>
          <span>{book.plannedChapters ? `${book.plannedChapters} capítulos previstos` : 'Capítulos ainda não definidos'}</span>
        </div>
        <div className="verbum-book-stage"><span>Etapa atual</span><strong>{stage}</strong></div>
      </div>
      <div className="verbum-book-card-actions">
        {!archived && <button type="button" className="verbum-primary-button verbum-open-work" onClick={onOpen}>Abrir obra</button>}
        <button type="button" className="verbum-text-button" onClick={onEdit}>Editar</button>
        {!archived && <button type="button" className="verbum-text-button is-danger" onClick={onArchive}>Arquivar</button>}
      </div>
    </article>
  );
}
