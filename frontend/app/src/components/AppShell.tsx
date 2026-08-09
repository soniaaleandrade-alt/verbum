import { useState, type ReactNode } from 'react';
import type { CurrentUser } from '../types/verbum';
import { Header } from './Header';
import { Sidebar } from './Sidebar';

type AppShellProps = {
  user: CurrentUser;
  children: ReactNode;
};

export function AppShell({ user, children }: AppShellProps) {
  const [navigationOpen, setNavigationOpen] = useState(false);

  return (
    <div className="verbum-shell">
      <Sidebar open={navigationOpen} onClose={() => setNavigationOpen(false)} />
      <div className="verbum-shell-main">
        <Header user={user} onOpenNavigation={() => setNavigationOpen(true)} />
        <main className="verbum-main" id="verbum-dashboard">{children}</main>
      </div>
    </div>
  );
}
