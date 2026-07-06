import { useEffect, useState } from 'preact/hooks';
import { formatMinor } from '@paws/core';
import type { Balance } from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';

export function Wallet() {
  const [balances, setBalances] = useState<Balance[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    call(() => client.balances())
      .then(setBalances)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load balances'))
      .finally(() => setLoading(false));
  }, []);

  return (
    <>
      <Header />
      <main class="main">
        <h1>Wallet</h1>
        {loading && <Notice type="loading" message="Loading balances…" />}
        {error !== null && <Notice type="error" message={error} />}
        <div class="wallet-balances">
          {balances.map((b) => (
            <div key={b.currency} class="card balance-card">
              <p class="balance-currency">{b.currency}</p>
              <p class="balance-amount">{formatMinor(b.amountMinor, b.currency)}</p>
            </div>
          ))}
        </div>
        <Notice
          type="info"
          message="Deposits and withdrawals are coming back online soon."
        />
      </main>
    </>
  );
}
