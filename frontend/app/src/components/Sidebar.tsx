type SidebarProps = {
  open: boolean;
  onClose: () => void;
};

const navigation = [
  { label: 'Início', active: true },
  { label: 'Workspace' },
  { label: 'Obras' },
  { label: 'Calendário editorial' },
  { label: 'Relatórios' },
  { label: 'Ideias' },
];

export function Sidebar({ open, onClose }: SidebarProps) {
  return (
    <>
      <button
        type="button"
        className={`verbum-sidebar-backdrop${open ? ' is-visible' : ''}`}
        aria-label="Fechar navegação"
        onClick={onClose}
      />
      <aside className={`verbum-sidebar${open ? ' is-open' : ''}`} aria-label="Navegação principal">
        <div className="verbum-brand">
          <span className="verbum-brand-mark">V</span>
          <span>
            <strong>VERBUM</strong>
            <small>STUDIO</small>
          </span>
        </div>

        <nav className="verbum-nav">
          {navigation.map((item) => (
            <button
              key={item.label}
              type="button"
              className={`verbum-nav-item${item.active ? ' is-active' : ''}`}
              aria-current={item.active ? 'page' : undefined}
              aria-disabled={item.active ? undefined : true}
            >
              <span className="verbum-nav-dot" aria-hidden="true" />
              <span>{item.label}</span>
            </button>
          ))}
        </nav>

        <div className="verbum-sidebar-footer">
          <span className="verbum-sidebar-kicker">Ambiente editorial</span>
          <p>Um espaço de trabalho para acompanhar cada etapa da sua obra.</p>
        </div>
      </aside>
    </>
  );
}
