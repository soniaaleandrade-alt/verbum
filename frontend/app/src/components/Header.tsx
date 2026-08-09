import type { CurrentUser } from '../types/verbum';
import { UserMenu } from './UserMenu';

type HeaderProps = {
  user: CurrentUser;
  onOpenNavigation: () => void;
};

export function Header({ user, onOpenNavigation }: HeaderProps) {
  return (
    <header className="verbum-header">
      <div className="verbum-header-title">
        <button type="button" className="verbum-mobile-menu" onClick={onOpenNavigation} aria-label="Abrir navegação">
          <span />
          <span />
          <span />
        </button>
        <div>
          <span className="verbum-eyebrow">Área atual</span>
          <h1>Início</h1>
        </div>
      </div>
      <UserMenu user={user} />
    </header>
  );
}
