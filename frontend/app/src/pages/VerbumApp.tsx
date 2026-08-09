import { useCallback, useEffect, useState } from 'react';
import { AppShell } from '../components/AppShell';
import type { AppSection } from '../components/Sidebar';
import { getCurrentUser, getHealth } from '../services/core-service';
import {
  archiveBook,
  archiveProject,
  createBook,
  createProject,
  getLibrary,
  updateBook,
  updateProject,
} from '../services/library-service';
import type { CreateBookInput, CreateProjectInput, CurrentUser, LibraryData, VerbumBook, VerbumProject } from '../types/verbum';
import { Dashboard } from './Dashboard';
import { LibraryPage } from './LibraryPage';
import '../styles/verbum.css';
import '../styles/library.css';

const fallbackUser: CurrentUser = {
  id: '0',
  name: 'Você',
  email: '',
};

const emptyLibrary: LibraryData = { projects: [], books: [] };

export function VerbumApp() {
  const [user, setUser] = useState<CurrentUser>(fallbackUser);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeSection, setActiveSection] = useState<AppSection>('dashboard');
  const [library, setLibrary] = useState<LibraryData>(emptyLibrary);
  const [libraryLoading, setLibraryLoading] = useState(true);
  const [libraryError, setLibraryError] = useState('');

  const refreshLibrary = useCallback(async () => {
    setLibraryLoading(true);
    setLibraryError('');
    try {
      setLibrary(await getLibrary());
    } catch (cause) {
      setLibraryError(cause instanceof Error ? cause.message : 'Não foi possível carregar o Banco de Obras.');
    } finally {
      setLibraryLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;

    async function boot() {
      const [healthResult, userResult, libraryResult] = await Promise.allSettled([getHealth(), getCurrentUser(), getLibrary()]);

      if (!active) return;

      if (healthResult.status === 'rejected' || healthResult.value.status !== 'ok') {
        setError('Não foi possível carregar o Verbum Studio. A API principal não está respondendo.');
        setLoading(false);
        setLibraryLoading(false);
        return;
      }

      if (userResult.status === 'fulfilled' && userResult.value?.name) {
        setUser(userResult.value);
      }

      if (libraryResult.status === 'fulfilled') {
        setLibrary(libraryResult.value);
      } else {
        setLibraryError('Não foi possível carregar o Banco de Obras.');
      }

      setLibraryLoading(false);
      setLoading(false);
    }

    boot().catch(() => {
      if (active) {
        setError('Não foi possível carregar o Verbum Studio. Tente novamente em alguns instantes.');
        setLoading(false);
        setLibraryLoading(false);
      }
    });

    return () => {
      active = false;
    };
  }, []);

  async function createProjectAndRefresh(input: CreateProjectInput) {
    await createProject(input);
    await refreshLibrary();
  }

  async function updateProjectAndRefresh(project: VerbumProject, input: CreateProjectInput) {
    await updateProject(project.id, input);
    await refreshLibrary();
  }

  async function archiveProjectAndRefresh(project: VerbumProject) {
    await archiveProject(project.id);
    await refreshLibrary();
  }

  async function createBookAndRefresh(input: CreateBookInput) {
    await createBook(input);
    await refreshLibrary();
  }

  async function updateBookAndRefresh(book: VerbumBook, input: CreateBookInput) {
    await updateBook(book.id, input);
    await refreshLibrary();
  }

  async function archiveBookAndRefresh(book: VerbumBook) {
    await archiveBook(book.id);
    await refreshLibrary();
  }

  if (loading) {
    return (
      <section className="verbum-app verbum-app-state verbum-loading" aria-live="polite">
        <span className="verbum-state-mark">V</span>
        <strong>Carregando Verbum Studio...</strong>
        <small>Preparando seu espaço de escrita.</small>
      </section>
    );
  }

  if (error) {
    return (
      <section className="verbum-app verbum-app-state verbum-error" role="alert">
        <span className="verbum-state-mark">V</span>
        <strong>Não foi possível abrir o Verbum Studio</strong>
        <p>{error}</p>
      </section>
    );
  }

  return (
    <div className="verbum-app">
      <AppShell user={user} activeSection={activeSection} onNavigate={setActiveSection}>
        {activeSection === 'dashboard' ? (
          <Dashboard userName={user.name} library={library} onOpenLibrary={() => setActiveSection('library')} />
        ) : (
          <LibraryPage
            data={library}
            loading={libraryLoading}
            error={libraryError}
            onReload={refreshLibrary}
            onCreateProject={createProjectAndRefresh}
            onUpdateProject={updateProjectAndRefresh}
            onArchiveProject={archiveProjectAndRefresh}
            onCreateBook={createBookAndRefresh}
            onUpdateBook={updateBookAndRefresh}
            onArchiveBook={archiveBookAndRefresh}
          />
        )}
      </AppShell>
    </div>
  );
}
