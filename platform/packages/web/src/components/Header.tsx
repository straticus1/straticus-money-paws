import { useEffect, useState } from 'preact/hooks';
import { formatMinor } from '@paws/core';
import type { Balance } from '@paws/core';
import { call, clearToken, client, navigate } from '../lib/api';

export function Header() {
  const [balances, setBalances] = useState<Balance[]>([]);

  useEffect(() => {
    call(() => client.balances())
      .then(setBalances)
      .catch(() => {
        // silently ignore header balance errors
      });
  }, []);

  function handleLogout() {
    call(() => client.logout()).catch(() => {
      // ignore logout errors — always clear locally
    });
    clearToken();
    navigate('/login');
  }

  return (
    <header class="header">
      <div class="header__logo">🐾 paws.money</div>
      <nav class="header__balances">
        {balances.map((b) => (
          <span key={b.currency} class="balance-chip">
            {formatMinor(b.amountMinor, b.currency)}
          </span>
        ))}
      </nav>
      <a href="#/store" style="font-size:0.88rem;color:var(--accent);text-decoration:none;font-weight:600;">
        Store
      </a>
      <a href="#/games" style="font-size:0.88rem;color:var(--accent);text-decoration:none;font-weight:600;">
        Games
      </a>
      <a href="#/wallet" style="font-size:0.88rem;color:var(--accent);text-decoration:none;font-weight:600;">
        Wallet
      </a>
      <button class="btn btn--ghost" onClick={handleLogout}>
        Logout
      </button>
    </header>
  );
}
