const actions = [
  { title: 'Criar nova obra', description: 'Inicie um novo projeto editorial.' },
  { title: 'Registrar uma ideia', description: 'Guarde um ponto para desenvolver depois.' },
  { title: 'Abrir calendário', description: 'Visualize seu planejamento editorial.' },
];

export function QuickActions() {
  return (
    <section className="verbum-panel verbum-quick-actions" aria-labelledby="verbum-quick-actions-title">
      <div className="verbum-section-heading">
        <div>
          <span className="verbum-eyebrow">Atalhos</span>
          <h2 id="verbum-quick-actions-title">Ações rápidas</h2>
        </div>
      </div>
      <div className="verbum-action-list">
        {actions.map((action) => (
          <button type="button" key={action.title} className="verbum-action-card" aria-disabled="true">
            <span className="verbum-action-plus" aria-hidden="true">+</span>
            <span>
              <strong>{action.title}</strong>
              <small>{action.description}</small>
            </span>
          </button>
        ))}
      </div>
    </section>
  );
}
