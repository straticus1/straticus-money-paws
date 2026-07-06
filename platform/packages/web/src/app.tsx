import { useEffect, useState } from 'preact/hooks';
import { getToken } from './lib/api';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { Dashboard } from './pages/Dashboard';
import { Store } from './pages/Store';
import { Wallet } from './pages/Wallet';

function getRoute(): string {
  // hash is like "#/login" → "/login", or "#/" → "/"
  const hash = window.location.hash;
  return hash.length > 1 ? hash.slice(1) : '/';
}

export function App() {
  const [route, setRoute] = useState(getRoute);

  useEffect(() => {
    const handler = () => setRoute(getRoute());
    window.addEventListener('hashchange', handler);
    return () => window.removeEventListener('hashchange', handler);
  }, []);

  const isPublic = route === '/login' || route === '/register';

  // Redirect unauthenticated visitors to login without a DOM side-effect in render
  if (!isPublic && getToken() === null) {
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
    default:
      return <Dashboard />;
  }
}
