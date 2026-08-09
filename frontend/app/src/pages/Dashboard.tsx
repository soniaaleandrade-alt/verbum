import { EmptyState } from '../components/EmptyState';
import { QuickActions } from '../components/QuickActions';
import { RecentActivity } from '../components/RecentActivity';
import { StatCard } from '../components/StatCard';

type DashboardProps = {
  userName: string;
};

export function Dashboard({ userName }: DashboardProps) {
  return (
    <div className="verbum-dashboard">
      <section className="verbum-welcome">
        <span className="verbum-eyebrow">Seu espaço de escrita</span>
        <h2>Bom trabalho, {userName}</h2>
        <p>Organize sua obra com clareza, acompanhe cada etapa e mantenha o foco no que precisa ser escrito agora.</p>
      </section>

      <section className="verbum-stats" aria-label="Indicadores do painel">
        <StatCard label="Obras em andamento" value="0" helper="Nenhuma obra ativa" />
        <StatCard label="Capítulos" value="0" helper="Aguardando sua primeira obra" />
        <StatCard label="Palavras escritas" value="0" helper="Seu progresso aparecerá aqui" />
        <StatCard label="Progresso médio" value="0%" helper="Sem dados ainda" />
      </section>

      <section className="verbum-panel verbum-continue" aria-labelledby="verbum-continue-title">
        <div className="verbum-section-heading">
          <div>
            <span className="verbum-eyebrow">Retome sua jornada</span>
            <h2 id="verbum-continue-title">Continue escrevendo</h2>
          </div>
        </div>
        <EmptyState />
      </section>

      <div className="verbum-dashboard-grid">
        <QuickActions />
        <RecentActivity />
      </div>
    </div>
  );
}
