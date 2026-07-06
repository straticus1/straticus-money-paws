import { useEffect, useState } from 'preact/hooks';
import { ApiError, formatMinor } from '@paws/core';
import type { StoreItem } from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';

export function Store() {
  const [items, setItems] = useState<StoreItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [buyErrors, setBuyErrors] = useState<Record<string, string>>({});
  const [buying, setBuying] = useState<Record<string, boolean>>({});

  useEffect(() => {
    call(() => client.storeItems())
      .then(setItems)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load store'))
      .finally(() => setLoading(false));
  }, []);

  async function handleBuy(item: StoreItem) {
    setBuying((prev) => ({ ...prev, [item.id]: true }));
    setBuyErrors((prev) => {
      const next = { ...prev };
      delete next[item.id];
      return next;
    });
    try {
      await call(() => client.purchase(item.id, 1));
    } catch (e) {
      const msg =
        e instanceof ApiError
          ? `${e.message} (${e.code})`
          : e instanceof Error
            ? e.message
            : 'Purchase failed';
      setBuyErrors((prev) => ({ ...prev, [item.id]: msg }));
    } finally {
      setBuying((prev) => ({ ...prev, [item.id]: false }));
    }
  }

  return (
    <>
      <Header />
      <main class="main">
        <h1>Store</h1>
        {loading && <Notice type="loading" message="Loading store…" />}
        {error !== null && <Notice type="error" message={error} />}
        <div class="store-grid">
          {items.map((item) => {
            const itemBuyError = buyErrors[item.id];
            const isBuying = buying[item.id] === true;
            return (
              <div key={item.id} class="card store-card">
                <div class="store-emoji">{item.effect.emoji}</div>
                <h3>{item.name}</h3>
                <p class="store-description">{item.description}</p>
                <p class="store-price">{formatMinor(item.priceMinor, item.currency)}</p>
                {itemBuyError !== undefined && (
                  <Notice type="error" message={itemBuyError} />
                )}
                <button
                  class="btn btn--primary"
                  onClick={() => handleBuy(item)}
                  disabled={isBuying}
                >
                  {isBuying ? 'Buying…' : 'Buy'}
                </button>
              </div>
            );
          })}
        </div>
      </main>
    </>
  );
}
