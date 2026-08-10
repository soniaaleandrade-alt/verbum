export type AppSection = 'dashboard' | 'library';

type SidebarProps = {
  open: boolean;
  activeSection: AppSection;
  onClose: () => void;
  onNavigate: (section: AppSection) => void;
};

type NavItem = { label: string; section?: AppSection };
type NavGroup = { label: string; items: NavItem[] };

const groups: NavGroup[] = [
  { label: 'Principal', items: [
    { label: 'Painel', section: 'dashboard' },
    { label: 'Mesa de Trabalho' },
    { label: 'Minhas Obras', section: 'library' },
  ] },
  { label: 'Escrita', items: [
    { label: 'Base de Conhecimento' },
    { label: 'Ideias' },
    { label: 'Citações' },
  ] },
  { label: 'Planejamento', items: [
    { label: 'Calendário Editorial' },
    { label: 'Cronograma' },
  ] },
  { label: 'Análise', items: [{ label: 'Relatórios' }] },
  { label: 'Sistema', items: [
    { label: 'Verbum Cloud' },
    { label: 'Backup' },
    { label: 'Preferências de Escrita' },
  ] },
  { label: 'Final', items: [{ label: 'Lixeira' }] },
];

export function Sidebar({ open, activeSection, onClose, onNavigate }: SidebarProps) {
  return (
    <>
      <button type="button" className={`verbum-sidebar-backdrop${open ? ' is-visible' : ''}`} aria-label="Fechar navegação" onClick={onClose} />
      <aside className={`verbum-sidebar${open ? ' is-open' : ''}`} aria-label="Navegação principal">
        <div className="verbum-brand">
          <span className="verbum-brand-mark">V</span>
          <span><strong>VERBUM</strong><small>STUDIO</small></span>
        </div>
        <nav className="verbum-nav">
          {groups.map((group) => (
            <section key={group.label} className="verbum-nav-group" aria-label={group.label}>
              <span className="verbum-nav-group-label">{group.label}</span>
              {group.items.map((item) => {
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
            </section>
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
