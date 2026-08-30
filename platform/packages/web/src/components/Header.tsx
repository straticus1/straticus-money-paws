import { useEffect, useState } from 'preact/hooks';
import { formatMinor } from '@paws/core';
import type { Balance } from '@paws/core';
import { call, client, navigate } from '../lib/api';

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
      <a href="#/store" class="header__link">
        Store
      </a>
      <a href="#/games" class="header__link">
        Games
      </a>
      <a href="#/honors" class="header__link">Honors</a>
      <a href="#/wallet" class="header__link">
        Wallet
      </a>
      <a href="#/settings" class="header__link">Settings</a>
      <button class="btn btn--ghost" onClick={handleLogout}>
        Logout
      </button>
    </header>
  );
}
