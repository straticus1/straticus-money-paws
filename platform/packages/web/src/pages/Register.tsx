import { useState } from 'preact/hooks';
import { Notice } from '../components/Notice';
import { client, navigate } from '../lib/api';

export function Register() {
  const [email, setEmail] = useState('');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: Event) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      await client.register(email, username, password);
      navigate('/');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Registration failed');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div class="auth-page">
      <div class="card auth-card">
        <h1>🐾 paws.money</h1>
        <h2>Create your account</h2>
        {error !== null && <Notice type="error" message={error} />}
        <form onSubmit={handleSubmit}>
          <div class="field">
            <label for="email">Email</label>
            <input
              id="email"
              type="email"
              autocomplete="email"
              value={email}
              onInput={(e) => setEmail((e.target as HTMLInputElement).value)}
              required
            />
          </div>
          <div class="field">
            <label for="username">Username</label>
            <input
              id="username"
              type="text"
              autocomplete="username"
              value={username}
              onInput={(e) => setUsername((e.target as HTMLInputElement).value)}
              required
            />
          </div>
          <div class="field">
            <label for="password">Password</label>
            <input
              id="password"
              type="password"
              autocomplete="new-password"
              value={password}
              onInput={(e) => setPassword((e.target as HTMLInputElement).value)}
              required
            />
          </div>
          <button type="submit" class="btn btn--primary btn--full" disabled={loading}>
            {loading ? 'Creating account…' : 'Register'}
          </button>
        </form>
        <p class="auth-link">
          Already have an account? <a href="#/login">Sign in</a>
        </p>
      </div>
    </div>
  );
}
