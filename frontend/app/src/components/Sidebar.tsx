export type AppSection = 'dashboard' | 'library';

type SidebarProps = {
  open: boolean;
  activeSection: AppSection;
  onClose: () => void;
  onNavigate: (section: AppSection) => void;
};

const navigation: Array<{ label: string; section?: AppSection }> = [
  { label: 'Início', section: 'dashboard' },
  { label: 'Workspace' },
  { label: 'Obras', section: 'library' },
  { label: 'Calendário editorial' },
  { label: 'Relatórios' },
  { label: 'Ideias' },
];

export function Sidebar({ open, activeSection, onClose, onNavigate }: SidebarProps) {
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
          {navigation.map((item) => {
            const active = item.section === activeSection;
            const disabled = !item.section;
            return (
              <button
                key={item.label}
                type="button"
                className={`verbum-nav-item${active ? ' is-active' : ''}`}
                aria-current={active ? 'page' : undefined}
                aria-disabled={disabled || undefined}
                disabled={disabled}
                onClick={() => {
                  if (!item.section) return;
                  onNavigate(item.section);
                  onClose();
                }}
              >
                <span className="verbum-nav-dot" aria-hidden="true" />
                <span>{item.label}</span>
              </button>
            );
          })}
        </nav>

        <div className="verbum-sidebar-footer">
          <span className="verbum-sidebar-kicker">Ambiente editorial</span>
          <p>Um espaço de trabalho para acompanhar cada etapa da sua obra.</p>
        </div>
      </aside>
    </>
  );
}
