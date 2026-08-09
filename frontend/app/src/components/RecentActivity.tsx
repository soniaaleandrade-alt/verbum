export function RecentActivity() {
  return (
    <section className="verbum-panel verbum-recent-activity" aria-labelledby="verbum-recent-title">
      <div className="verbum-section-heading">
        <div>
          <span className="verbum-eyebrow">Histórico</span>
          <h2 id="verbum-recent-title">Atividade recente</h2>
        </div>
      </div>
      <div className="verbum-activity-empty">
        <span className="verbum-activity-line" aria-hidden="true" />
        <p>Quando você começar a trabalhar em uma obra, suas atividades mais recentes aparecerão aqui.</p>
      </div>
    </section>
  );
}
