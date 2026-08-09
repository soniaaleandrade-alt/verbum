import { useMemo, useState } from 'react';
import { BookCard } from '../components/BookCard';
import { BookDialog } from '../components/BookDialog';
import { ProjectDialog } from '../components/ProjectDialog';
import type { CreateBookInput, CreateProjectInput, LibraryData, VerbumBook, VerbumProject } from '../types/verbum';

type LibraryPageProps = {
  data: LibraryData;
  loading: boolean;
  error: string;
  onReload: () => void;
  onOpenBook: (book: VerbumBook) => void;
  onCreateProject: (input: CreateProjectInput) => Promise<void>;
  onUpdateProject: (project: VerbumProject, input: CreateProjectInput) => Promise<void>;
  onArchiveProject: (project: VerbumProject) => Promise<void>;
  onCreateBook: (input: CreateBookInput) => Promise<void>;
  onUpdateBook: (book: VerbumBook, input: CreateBookInput) => Promise<void>;
  onArchiveBook: (book: VerbumBook) => Promise<void>;
};

export function LibraryPage({ data, loading, error, onReload, onOpenBook, onCreateProject, onUpdateProject, onArchiveProject, onCreateBook, onUpdateBook, onArchiveBook }: LibraryPageProps) {
  const [query, setQuery] = useState('');
  const [status, setStatus] = useState<'active' | 'archived' | 'all'>('active');
  const [projectDialog, setProjectDialog] = useState<VerbumProject | 'new' | null>(null);
  const [bookDialog, setBookDialog] = useState<VerbumBook | 'new' | null>(null);
  const [defaultBookProjectId, setDefaultBookProjectId] = useState('');
  const [actionError, setActionError] = useState('');

  const projects = useMemo(() => {
    const normalized = query.trim().toLocaleLowerCase('pt-BR');
    return data.projects.filter((project) => {
      const hasBookWithStatus = status !== 'all' && data.books.some((book) => book.projectId === project.id && book.status === status);
      if (status !== 'all' && project.status !== status && !hasBookWithStatus) return false;
      if (!normalized) return true;
      const projectMatches = `${project.name} ${project.description}`.toLocaleLowerCase('pt-BR').includes(normalized);
      const bookMatches = data.books.some((book) => book.projectId === project.id && `${book.title} ${book.subtitle ?? ''} ${book.genre ?? ''}`.toLocaleLowerCase('pt-BR').includes(normalized));
      return projectMatches || bookMatches;
    });
  }, [data, query, status]);

  const activeProjects = data.projects.filter((project) => project.status === 'active');
  const activeBooks = data.books.filter((book) => book.status === 'active');

  function openNewBook(projectId = '') {
    setDefaultBookProjectId(projectId);
    setBookDialog('new');
  }

  function closeBookDialog() {
    setBookDialog(null);
    setDefaultBookProjectId('');
  }

  async function archiveProject(project: VerbumProject) {
    if (!window.confirm(`Arquivar o projeto “${project.name}” e suas obras?`)) return;
    setActionError('');
    try { await onArchiveProject(project); } catch (cause) { setActionError(cause instanceof Error ? cause.message : 'Não foi possível arquivar o projeto.'); }
  }

  async function archiveBook(book: VerbumBook) {
    if (!window.confirm(`Arquivar a obra “${book.title}”?`)) return;
    setActionError('');
    try { await onArchiveBook(book); } catch (cause) { setActionError(cause instanceof Error ? cause.message : 'Não foi possível arquivar a obra.'); }
  }

  return (
    <div className="verbum-library">
      <section className="verbum-page-heading">
        <div><span className="verbum-eyebrow">Seu acervo editorial</span><h2>Banco de Obras</h2><p>Organize seus projetos e livros. Cada obra permanece vinculada ao projeto em que nasceu.</p></div>
        <div className="verbum-page-actions"><button type="button" className="verbum-secondary-button" onClick={() => setProjectDialog('new')}>Novo projeto</button><button type="button" className="verbum-primary-button" onClick={() => openNewBook()} disabled={activeProjects.length === 0}>Criar nova obra</button></div>
      </section>

      <section className="verbum-library-summary" aria-label="Resumo do Banco de Obras">
        <div><strong>{activeProjects.length}</strong><span>Projetos ativos</span></div>
        <div><strong>{activeBooks.length}</strong><span>Obras ativas</span></div>
        <div><strong>{data.books.filter((book) => book.status === 'archived').length}</strong><span>Obras arquivadas</span></div>
      </section>

      <section className="verbum-library-toolbar">
        <label className="verbum-search-field"><span className="sr-only">Buscar</span><input value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Buscar projeto, título, subtítulo ou gênero..." /></label>
        <div className="verbum-filter-tabs" aria-label="Filtrar por status">{(['active', 'archived', 'all'] as const).map((value) => <button key={value} type="button" className={status === value ? 'is-active' : ''} onClick={() => setStatus(value)}>{value === 'active' ? 'Ativos' : value === 'archived' ? 'Arquivados' : 'Todos'}</button>)}</div>
      </section>

      {(error || actionError) && <div className="verbum-inline-error" role="alert"><span>{actionError || error}</span>{error && <button type="button" onClick={onReload}>Tentar novamente</button>}</div>}

      {loading ? (
        <section className="verbum-library-loading" aria-live="polite">Carregando seu Banco de Obras...</section>
      ) : data.projects.length === 0 ? (
        <section className="verbum-panel verbum-library-empty"><span className="verbum-empty-symbol">V</span><div><span className="verbum-eyebrow">Primeiro passo</span><h3>Crie seu primeiro projeto</h3><p>O projeto reúne uma ou mais obras. Depois de criá-lo, você poderá cadastrar o primeiro livro e preencher sua identificação.</p></div><button type="button" className="verbum-primary-button" onClick={() => setProjectDialog('new')}>Criar projeto</button></section>
      ) : projects.length === 0 ? (
        <section className="verbum-panel verbum-library-empty compact"><div><h3>Nenhum resultado encontrado</h3><p>Ajuste a busca ou o filtro para localizar suas obras.</p></div></section>
      ) : (
        <div className="verbum-project-list">
          {projects.map((project) => {
            const books = data.books.filter((book) => book.projectId === project.id && (status === 'all' || book.status === status));
            return (
              <section key={project.id} className={`verbum-panel verbum-project-section${project.status === 'archived' ? ' is-archived' : ''}`}>
                <div className="verbum-project-heading"><div><span className="verbum-eyebrow">Projeto</span><h3>{project.name}</h3>{project.description && <p>{project.description}</p>}</div><div className="verbum-project-actions"><span>{books.length} {books.length === 1 ? 'obra' : 'obras'}</span><button type="button" className="verbum-text-button" onClick={() => setProjectDialog(project)}>Editar</button>{project.status !== 'archived' && <button type="button" className="verbum-text-button is-danger" onClick={() => archiveProject(project)}>Arquivar</button>}</div></div>
                {books.length > 0 ? (
                  <div className="verbum-book-grid">{books.map((book) => <BookCard key={book.id} book={book} projectName={project.name} onOpen={() => onOpenBook(book)} onEdit={() => { setDefaultBookProjectId(''); setBookDialog(book); }} onArchive={() => archiveBook(book)} />)}</div>
                ) : (
                  <div className="verbum-project-empty"><p>Nenhuma obra neste projeto.</p>{project.status === 'active' && <button type="button" className="verbum-text-button" onClick={() => openNewBook(project.id)}>Adicionar obra</button>}</div>
                )}
              </section>
            );
          })}
        </div>
      )}

      <ProjectDialog open={projectDialog !== null} project={projectDialog && projectDialog !== 'new' ? projectDialog : null} onClose={() => setProjectDialog(null)} onSave={(input) => projectDialog && projectDialog !== 'new' ? onUpdateProject(projectDialog, input) : onCreateProject(input)} />
      <BookDialog open={bookDialog !== null} projects={data.projects} book={bookDialog && bookDialog !== 'new' ? bookDialog : null} defaultProjectId={defaultBookProjectId} onClose={closeBookDialog} onSave={(input) => bookDialog && bookDialog !== 'new' ? onUpdateBook(bookDialog, input) : onCreateBook(input)} />
    </div>
  );
}
