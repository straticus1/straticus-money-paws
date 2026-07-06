import { useState } from 'preact/hooks';
import { ApiError } from '@paws/core';
import { Notice } from '../components/Notice';
import { client, navigate, setToken } from '../lib/api';

export function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totp, setTotp] = useState('');
  const [showTotp, setShowTotp] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(e: Event) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const result = await client.login(email, password, showTotp ? totp : undefined);
      setToken(result.token);
      navigate('/');
    } catch (e) {
      if (e instanceof ApiError && e.code === 'totp_required') {
        setShowTotp(true);
        setError('Please enter your authenticator code below.');
      } else {
        setError(e instanceof Error ? e.message : 'Login failed');
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <div class="auth-page">
      <div class="card auth-card">
        <h1>🐾 paws.money</h1>
        <h2>Sign in to your account</h2>
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
            <label for="password">Password</label>
            <input
              id="password"
              type="password"
              autocomplete="current-password"
              value={password}
              onInput={(e) => setPassword((e.target as HTMLInputElement).value)}
              required
            />
          </div>
          {showTotp && (
            <div class="field">
              <label for="totp">Authenticator Code</label>
              <input
                id="totp"
                type="text"
                inputMode="numeric"
                autocomplete="one-time-code"
                placeholder="6-digit code"
                value={totp}
                onInput={(e) => setTotp((e.target as HTMLInputElement).value)}
              />
            </div>
          )}
          <button type="submit" class="btn btn--primary btn--full" disabled={loading}>
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>
        <p class="auth-link">
          No account? <a href="#/register">Register</a>
        </p>
      </div>
    </div>
  );
}
