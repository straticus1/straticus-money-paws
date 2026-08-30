import { useEffect, useState } from 'preact/hooks';
import type { AccountSettings } from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';

export function Settings() {
  const [settings, setSettings] = useState<AccountSettings | null>(null);
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    call(() => client.settings()).then(setSettings)
      .catch((cause) => setError(cause instanceof Error ? cause.message : 'Settings could not be loaded.'));
  }, []);

  async function savePrivacy(e: Event) {
    e.preventDefault();
    if (!settings) return;
    setError(null);
    await call(() => client.updateSettings({
      leaderboardOptIn: settings.leaderboardOptIn,
      trophyShowcase: settings.trophyShowcase,
    })).then(() => setMessage('Privacy choices saved.'))
      .catch((cause) => setError(cause instanceof Error ? cause.message : 'Could not save settings.'));
  }

  async function changePassword(e: Event) {
    e.preventDefault();
    setError(null);
    await call(() => client.changePassword(currentPassword, newPassword)).then(() => {
      setCurrentPassword('');
      setNewPassword('');
      setMessage('Password changed. Other sessions were signed out.');
    }).catch((cause) => setError(cause instanceof Error ? cause.message : 'Password change failed.'));
  }

  return <>
    <Header />
    <main class="main settings-page">
      <section class="settings-heading">
        <p class="eyebrow">ACCOUNT FIELD MANUAL</p>
        <h1>Privacy & Security</h1>
        <p>Your public trail name, sign-in defenses, and session controls live here.</p>
      </section>
      {error && <Notice type="error" message={error} />}
      {message && <Notice type="info" message={message} />}
      {settings && <div class="settings-layout">
        <section class="settings-sheet">
          <span class="sheet-number">01</span><h2>Identity card</h2>
          <dl><div><dt>Trail name</dt><dd>{settings.username}</dd></div><div><dt>Email</dt><dd>{settings.email}</dd></div></dl>
          <p class="security-status"><span class={settings.twoFactorEnabled ? 'status-dot status-dot--on' : 'status-dot'} />
            Two-factor authentication is {settings.twoFactorEnabled ? 'enabled' : 'not enabled'}.</p>
        </section>
        <form class="settings-sheet" onSubmit={savePrivacy}>
          <span class="sheet-number">02</span><h2>Community privacy</h2>
          <label class="toggle-row"><span><strong>Join the Trail Register</strong><small>Publish only your username and verified play score.</small></span>
            <input type="checkbox" checked={settings.leaderboardOptIn} onChange={(e) => setSettings({ ...settings, leaderboardOptIn: (e.target as HTMLInputElement).checked })} />
          </label>
          <label class="toggle-row"><span><strong>Show trophy case</strong><small>Keep your earned pins ready for future public profiles.</small></span>
            <input type="checkbox" checked={settings.trophyShowcase} onChange={(e) => setSettings({ ...settings, trophyShowcase: (e.target as HTMLInputElement).checked })} />
          </label>
          <button class="btn btn--primary" type="submit">Save privacy choices</button>
        </form>
        <form class="settings-sheet settings-sheet--security" onSubmit={changePassword}>
          <span class="sheet-number">03</span><h2>Change the gate key</h2>
          <p>Changing your password revokes every existing session and issues this browser a fresh, protected session.</p>
          <div class="field"><label for="current-password">Current password</label><input id="current-password" type="password" autocomplete="current-password" value={currentPassword} onInput={(e) => setCurrentPassword((e.target as HTMLInputElement).value)} required /></div>
          <div class="field"><label for="new-password">New password</label><input id="new-password" type="password" autocomplete="new-password" minlength={8} value={newPassword} onInput={(e) => setNewPassword((e.target as HTMLInputElement).value)} required /></div>
          <button class="btn" type="submit">Change password & sign out other sessions</button>
        </form>
      </div>}
    </main>
  </>;
}
