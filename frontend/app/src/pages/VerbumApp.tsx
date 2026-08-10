import { useCallback, useEffect, useState } from 'react';
import { AppShell } from '../components/AppShell';
import type { AppSection } from '../components/Sidebar';
import { WorkHeader } from '../components/WorkHeader';
import { getCurrentUser, getHealth } from '../services/core-service';
import {
  archiveBook,
  archiveProject,
  createBook,
  createProject,
  getLibrary,
  getWorkWorkspace,
  updateBook,
  updateProject,
} from '../services/library-service';
import type {
  CreateBookInput,
  CreateProjectInput,
  CurrentUser,
  LibraryData,
  VerbumBook,
  VerbumProject,
  WorkStageKey,
  WorkWorkspaceData,
} from '../types/verbum';
import { Dashboard } from './Dashboard';
import { LibraryPage } from './LibraryPage';
import { WorkWorkspace } from './WorkWorkspace';
import '../styles/verbum.css';
import '../styles/library.css';
import '../styles/workspace.css';
import '../styles/identification.css';
import '../styles/project-stage.css';
import '../styles/technical.css';
import '../styles/dashboard-official.css';

const fallbackUser: CurrentUser = { id: '0', name: 'Você', email: '' };
const emptyLibrary: LibraryData = { projects: [], books: [] };
const stageKeys: WorkStageKey[] = ['identification','project','planning','development','general_review','versions','audit','editorial_desk','layout','legal','publication'];

function readWorkspaceRoute() {
  const params = new URLSearchParams(window.location.search);
  const bookId = params.get('verbum_work');
  const rawStage = params.get('verbum_stage');
  const stage = stageKeys.includes(rawStage as WorkStageKey) ? rawStage as WorkStageKey : null;
  return bookId ? { bookId, stage } : null;
}

function writeWorkspaceRoute(bookId: string | null, stage?: WorkStageKey, replace = false) {
  const url = new URL(window.location.href);
  if (bookId) {
    url.searchParams.set('verbum_work', bookId);
    if (stage) url.searchParams.set('verbum_stage', stage);
  } else {
    url.searchParams.delete('verbum_work');
    url.searchParams.delete('verbum_stage');
  }
  window.history[replace ? 'replaceState' : 'pushState']({}, '', url);
}

export function VerbumApp() {
  const initialRoute = readWorkspaceRoute();
  const [user, setUser] = useState<CurrentUser>(fallbackUser);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [activeSection, setActiveSection] = useState<AppSection>(initialRoute ? 'library' : 'dashboard');
  const [library, setLibrary] = useState<LibraryData>(emptyLibrary);
  const [libraryLoading, setLibraryLoading] = useState(true);
  const [libraryError, setLibraryError] = useState('');
  const [workspaceBookId, setWorkspaceBookId] = useState<string | null>(initialRoute?.bookId ?? null);
  const [requestedStage, setRequestedStage] = useState<WorkStageKey | null>(initialRoute?.stage ?? null);
  const [selectedStage, setSelectedStage] = useState<WorkStageKey>('identification');
  const [workspace, setWorkspace] = useState<WorkWorkspaceData | null>(null);
  const [workspaceLoading, setWorkspaceLoading] = useState(false);
  const [workspaceError, setWorkspaceError] = useState('');

  const refreshLibrary = useCallback(async () => {
    setLibraryLoading(true);
    setLibraryError('');
    try { setLibrary(await getLibrary()); }
    catch (cause) { setLibraryError(cause instanceof Error ? cause.message : 'Não foi possível carregar o Banco de Obras.'); }
    finally { setLibraryLoading(false); }
  }, []);

  const loadWorkspace = useCallback(async (bookId: string, preferredStage?: WorkStageKey | null) => {
    setWorkspaceLoading(true);
    setWorkspaceError('');
    try {
      const data = await getWorkWorkspace(bookId);
      setWorkspace(data);
      const preferred = preferredStage && data.workflow.find((step) => step.key === preferredStage && step.status !== 'locked');
      const nextStage = preferred?.key ?? data.currentStage;
      setSelectedStage(nextStage);
      setRequestedStage(nextStage);
      writeWorkspaceRoute(bookId, nextStage, true);
    } catch (cause) {
      setWorkspace(null);
      setWorkspaceError(cause instanceof Error ? cause.message : 'Não foi possível carregar esta obra.');
    } finally {
      setWorkspaceLoading(false);
    }
  }, []);

  useEffect(() => {
    let active = true;
    async function boot() {
      const [healthResult, userResult, libraryResult] = await Promise.allSettled([getHealth(), getCurrentUser(), getLibrary()]);
      if (!active) return;
      if (healthResult.status === 'rejected' || healthResult.value.status !== 'ok') {
        setError('Não foi possível carregar o Verbum Studio. A API principal não está respondendo.');
        setLoading(false); setLibraryLoading(false); return;
      }
      if (userResult.status === 'fulfilled' && userResult.value?.name) setUser(userResult.value);
      if (libraryResult.status === 'fulfilled') setLibrary(libraryResult.value);
      else setLibraryError('Não foi possível carregar o Banco de Obras.');
      setLibraryLoading(false); setLoading(false);
    }
    boot().catch(() => {
      if (active) { setError('Não foi possível carregar o Verbum Studio. Tente novamente em alguns instantes.'); setLoading(false); setLibraryLoading(false); }
    });
    return () => { active = false; };
  }, []);

  useEffect(() => {
    if (!loading && workspaceBookId) loadWorkspace(workspaceBookId, requestedStage);
  }, [loading, workspaceBookId, loadWorkspace]);

  useEffect(() => {
    if (!workspace || !requestedStage) return;
    const requested = workspace.workflow.find((step) => step.key === requestedStage && step.status !== 'locked');
    if (requested) setSelectedStage(requested.key);
  }, [workspace, requestedStage]);

  useEffect(() => {
    const onPopState = () => {
      const route = readWorkspaceRoute();
      setWorkspaceBookId(route?.bookId ?? null);
      setRequestedStage(route?.stage ?? null);
      setActiveSection('library');
      if (!route) setWorkspace(null);
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  async function createProjectAndRefresh(input: CreateProjectInput) { await createProject(input); await refreshLibrary(); }
  async function updateProjectAndRefresh(project: VerbumProject, input: CreateProjectInput) { await updateProject(project.id, input); await refreshLibrary(); }
  async function archiveProjectAndRefresh(project: VerbumProject) { await archiveProject(project.id); await refreshLibrary(); }
  async function createBookAndRefresh(input: CreateBookInput) { await createBook(input); await refreshLibrary(); }
  async function updateBookAndRefresh(book: VerbumBook, input: CreateBookInput) { await updateBook(book.id, input); await refreshLibrary(); }
  async function archiveBookAndRefresh(book: VerbumBook) { await archiveBook(book.id); await refreshLibrary(); }

  function openBook(book: VerbumBook) {
    const stage = stageKeys.includes(book.stage as WorkStageKey) ? book.stage as WorkStageKey : 'identification';
    setActiveSection('library'); setWorkspaceBookId(book.id); setRequestedStage(stage); writeWorkspaceRoute(book.id, stage);
  }

  function closeWorkspace() {
    setWorkspaceBookId(null); setWorkspace(null); setWorkspaceError(''); setRequestedStage(null); setActiveSection('library'); writeWorkspaceRoute(null);
  }

  function changeStage(stage: WorkStageKey) {
    setSelectedStage(stage); setRequestedStage(stage);
    if (workspaceBookId) writeWorkspaceRoute(workspaceBookId, stage);
  }

  function navigateGlobal(section: AppSection) {
    if (workspaceBookId) { setWorkspaceBookId(null); setWorkspace(null); setRequestedStage(null); writeWorkspaceRoute(null); }
    setActiveSection(section);
  }

  if (loading) return <section className="verbum-app verbum-app-state verbum-loading" aria-live="polite"><span className="verbum-state-mark">V</span><strong>Carregando Verbum Studio...</strong><small>Preparando seu espaço de escrita.</small></section>;
  if (error) return <section className="verbum-app verbum-app-state verbum-error" role="alert"><span className="verbum-state-mark">V</span><strong>Não foi possível abrir o Verbum Studio</strong><p>{error}</p></section>;

  const workspaceHeader = workspace
    ? (onOpenNavigation: () => void) => <WorkHeader workspace={workspace} onBack={closeWorkspace} onOpenNavigation={onOpenNavigation} />
    : undefined;

  return (
    <div className="verbum-app">
      <AppShell
        user={user}
        activeSection={activeSection}
        onNavigate={navigateGlobal}
        customHeader={workspaceHeader}
        hideHeader={!workspaceBookId && activeSection === 'dashboard'}
      >
        {workspaceBookId ? (
          workspaceLoading ? <section className="verbum-workspace-state">Carregando a obra...</section> :
          workspaceError || !workspace ? <section className="verbum-workspace-state is-error"><h2>Não foi possível carregar esta obra</h2><p>{workspaceError || 'Obra não encontrada.'}</p><button type="button" className="verbum-primary-button" onClick={() => loadWorkspace(workspaceBookId, requestedStage)}>Tentar novamente</button><button type="button" className="verbum-secondary-button" onClick={closeWorkspace}>Voltar para Obras</button></section> :
          <WorkWorkspace
            workspace={workspace}
            selectedStage={selectedStage}
            onStageChange={changeStage}
            onBackToLibrary={closeWorkspace}
            onWorkspaceChange={setWorkspace}
            onPersisted={refreshLibrary}
          />
        ) : activeSection === 'dashboard' ? (
          <Dashboard userName={user.name} library={library} onOpenLibrary={() => setActiveSection('library')} onOpenBook={openBook} />
        ) : (
          <LibraryPage data={library} loading={libraryLoading} error={libraryError} onReload={refreshLibrary} onOpenBook={openBook} onCreateProject={createProjectAndRefresh} onUpdateProject={updateProjectAndRefresh} onArchiveProject={archiveProjectAndRefresh} onCreateBook={createBookAndRefresh} onUpdateBook={updateBookAndRefresh} onArchiveBook={archiveBookAndRefresh} />
        )}
      </AppShell>
    </div>
  );
}
