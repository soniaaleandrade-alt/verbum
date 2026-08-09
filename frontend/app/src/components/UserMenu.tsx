import { useState } from 'react';
import type { CurrentUser } from '../types/verbum';

type UserMenuProps = {
  user: CurrentUser;
};

function initialFor(name: string) {
  return name.trim().charAt(0).toUpperCase() || 'V';
}

export function UserMenu({ user }: UserMenuProps) {
  const [open, setOpen] = useState(false);

  return (
    <div className="verbum-user-menu">
      <button
        type="button"
        className="verbum-user-trigger"
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        <span className="verbum-avatar" aria-hidden="true">{initialFor(user.name)}</span>
        <span className="verbum-user-copy">
          <strong>{user.name}</strong>
          <small>Minha conta</small>
        </span>
        <span className="verbum-user-chevron" aria-hidden="true">⌄</span>
      </button>

      {open ? (
        <div className="verbum-user-dropdown" role="menu">
          <button type="button" role="menuitem">Meu perfil</button>
          <button type="button" role="menuitem">Configurações</button>
          <span className="verbum-menu-separator" />
          <button type="button" role="menuitem">Sair</button>
        </div>
      ) : null}
    </div>
  );
}
