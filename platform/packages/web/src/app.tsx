import { useEffect, useState } from 'preact/hooks';
import { client } from './lib/api';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { Dashboard } from './pages/Dashboard';
import { Store } from './pages/Store';
import { Wallet } from './pages/Wallet';
import { Games } from './pages/Games';
import { TrailTails } from './pages/TrailTails';
import { MidnightPantry } from './pages/MidnightPantry';
import { PawMatch } from './pages/PawMatch';
import { LanternLines } from './pages/LanternLines';
import { PocketPost } from './pages/PocketPost';
import { ParadePractice } from './pages/ParadePractice';
import { Honors } from './pages/Honors';
import { Settings } from './pages/Settings';

function getRoute(): string {
  // hash is like "#/login" → "/login", or "#/" → "/"
  const hash = window.location.hash;
  return hash.length > 1 ? hash.slice(1) : '/';
}

export function App() {
  const [route, setRoute] = useState(getRoute);
  const [authenticated, setAuthenticated] = useState<boolean | null>(null);

  useEffect(() => {
    const handler = () => setRoute(getRoute());
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const isPublic = route === '/login' || route === '/register';

  useEffect(() => {
    if (isPublic) {
      setAuthenticated(null);
      return;
    }
    let active = true;
    client.me().then(() => {
      if (active) setAuthenticated(true);
    }).catch(() => {
      if (active) setAuthenticated(false);
    });
    return () => { active = false; };
  }, [route, isPublic]);

  if (!isPublic && authenticated === null) {
    return <div class="auth-page"><div class="card auth-card"><h1>🐾 paws.money</h1><p>Opening your field journal…</p></div></div>;
  }
  if (!isPublic && authenticated === false) {
    return <Login />;
  }

  switch (route) {
    case '/login':
      return <Login />;
    case '/register':
      return <Register />;
    case '/store':
      return <Store />;
    case '/wallet':
      return <Wallet />;
    case '/games':
      return <Games />;
    case '/games/paw-match':
      return <PawMatch />;
    case '/games/trail-tails':
      return <TrailTails />;
    case '/games/midnight-pantry':
      return <MidnightPantry />;
    case '/games/lantern-lines':
      return <LanternLines />;
    case '/games/pocket-post':
      return <PocketPost />;
    case '/games/parade-practice':
      return <ParadePractice />;
    case '/honors':
      return <Honors />;
    case '/settings':
      return <Settings />;
    default:
      return <Dashboard />;
  }
}
