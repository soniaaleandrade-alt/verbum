import { useState, type ReactNode } from 'react';
import type { CurrentUser } from '../types/verbum';
import { Header } from './Header';
import { Sidebar, type AppSection } from './Sidebar';

type AppShellProps = {
  user: CurrentUser;
  activeSection: AppSection;
  onNavigate: (section: AppSection) => void;
  children: ReactNode;
};

const sectionLabels: Record<AppSection, string> = {
  dashboard: 'Início',
  library: 'Obras',
};

export function AppShell({ user, activeSection, onNavigate, children }: AppShellProps) {
  const [navigationOpen, setNavigationOpen] = useState(false);

  return (
    <div className="verbum-shell">
      <Sidebar
        open={navigationOpen}
        activeSection={activeSection}
        onClose={() => setNavigationOpen(false)}
        onNavigate={onNavigate}
      />
      <div className="verbum-shell-main">
        <Header
          user={user}
          currentLabel={sectionLabels[activeSection]}
          onOpenNavigation={() => setNavigationOpen(true)}
        />
        <main className="verbum-main" id="verbum-main-content">{children}</main>
      </div>
    </div>
  );
}
