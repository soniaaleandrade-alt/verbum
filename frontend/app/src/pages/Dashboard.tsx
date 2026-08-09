import { EmptyState } from '../components/EmptyState';
import { QuickActions } from '../components/QuickActions';
import { RecentActivity } from '../components/RecentActivity';
import { StatCard } from '../components/StatCard';
import type { LibraryData } from '../types/verbum';

type DashboardProps = {
  userName: string;
  library: LibraryData;
  onOpenLibrary: () => void;
};

export function Dashboard({ userName, library, onOpenLibrary }: DashboardProps) {
  const activeBooks = library.books.filter((book) => book.status === 'active');
  const activeProjects = library.projects.filter((project) => project.status === 'active');
  const plannedChapters = activeBooks.reduce((total, book) => total + Number(book.plannedChapters || 0), 0);
  const wordGoal = activeBooks.reduce((total, book) => total + Number(book.wordGoal || 0), 0);
  const currentBook = activeBooks[0];

  return (
    <div className="verbum-dashboard">
      <section className="verbum-welcome">
        <span className="verbum-eyebrow">Seu espaço de escrita</span>
        <h2>Bom trabalho, {userName}</h2>
        <p>Organize sua obra com clareza, acompanhe cada etapa e mantenha o foco no que precisa ser escrito agora.</p>
      </section>

      <section className="verbum-stats" aria-label="Indicadores do painel">
        <StatCard label="Obras em andamento" value={String(activeBooks.length)} helper={activeBooks.length ? `${activeProjects.length} projetos ativos` : 'Nenhuma obra ativa'} />
        <StatCard label="Capítulos previstos" value={String(plannedChapters)} helper={plannedChapters ? 'Meta inicial das obras' : 'Aguardando planejamento'} />
        <StatCard label="Meta de palavras" value={wordGoal ? wordGoal.toLocaleString('pt-BR') : '0'} helper={wordGoal ? 'Meta editorial cadastrada' : 'Defina na identificação da obra'} />
        <StatCard label="Etapa atual" value={activeBooks.length ? '01' : '—'} helper={activeBooks.length ? 'Identificação da Obra' : 'Sem obra ativa'} />
      </section>

      <section className="verbum-panel verbum-continue" aria-labelledby="verbum-continue-title">
        <div className="verbum-section-heading">
          <div>
            <span className="verbum-eyebrow">Retome sua jornada</span>
            <h2 id="verbum-continue-title">Continue escrevendo</h2>
          </div>
        </div>
        {currentBook ? (
          <div className="verbum-current-book">
            <span className="verbum-current-book-color" style={{ backgroundColor: currentBook.color || '#7a3042' }} aria-hidden="true" />
            <div>
              <span className="verbum-eyebrow">Identificação da Obra</span>
              <h3>{currentBook.title}</h3>
              <p>{currentBook.subtitle || 'Complete os dados da obra para avançar com segurança para o planejamento editorial.'}</p>
            </div>
            <button type="button" className="verbum-primary-button" onClick={onOpenLibrary}>Abrir obra</button>
          </div>
        ) : (
          <EmptyState onCreate={onOpenLibrary} />
        )}
      </section>

      <div className="verbum-dashboard-grid">
        <QuickActions onCreateWork={onOpenLibrary} />
        <RecentActivity />
      </div>
    </div>
  );
}
