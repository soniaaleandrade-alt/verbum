import { useEffect, useState } from 'react';
import { AppShell } from '../components/AppShell';
import { getCurrentUser, getHealth } from '../services/core-service';
import type { CurrentUser } from '../types/verbum';
import { Dashboard } from './Dashboard';
import '../styles/verbum.css';

const fallbackUser: CurrentUser = {
  id: '0',
  name: 'Você',
  email: '',
};

export function VerbumApp() {
  const [user, setUser] = useState<CurrentUser>(fallbackUser);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let active = true;

    async function boot() {
      const [healthResult, userResult] = await Promise.allSettled([getHealth(), getCurrentUser()]);

      if (!active) return;

      if (healthResult.status === 'rejected' || healthResult.value.status !== 'ok') {
        setError('Não foi possível carregar o Verbum Studio. A API principal não está respondendo.');
        setLoading(false);
        return;
      }

      if (userResult.status === 'fulfilled' && userResult.value?.name) {
        setUser(userResult.value);
      }

      setLoading(false);
    }

    boot().catch(() => {
      if (active) {
        setError('Não foi possível carregar o Verbum Studio. Tente novamente em alguns instantes.');
        setLoading(false);
      }
    });

    return () => {
      active = false;
    };
  }, []);

  if (loading) {
    return (
      <section className="verbum-app verbum-app-state verbum-loading" aria-live="polite">
        <span className="verbum-state-mark">V</span>
        <strong>Carregando Verbum Studio...</strong>
        <small>Preparando seu espaço de escrita.</small>
      </section>
    );
  }

  if (error) {
    return (
      <section className="verbum-app verbum-app-state verbum-error" role="alert">
        <span className="verbum-state-mark">V</span>
        <strong>Não foi possível abrir o Verbum Studio</strong>
        <p>{error}</p>
      </section>
    );
  }

  return (
    <div className="verbum-app">
      <AppShell user={user}>
        <Dashboard userName={user.name} />
      </AppShell>
    </div>
  );
}
