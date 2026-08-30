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
  const [category, setCategory] = useState('all');

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
        <div class="store-heading"><div><p>Pet outfitter & neighborhood supply</p><h1>The General Store</h1></div><span>Food, toys, homes, props, habitats, and small wonders.</span></div>
        {loading && <Notice type="loading" message="Loading store…" />}
        {error !== null && <Notice type="error" message={error} />}
        <div class="store-filters" role="group" aria-label="Store categories">
          {['all', ...Array.from(new Set(items.map((item) => item.category)))].map((name) => <button class={category === name ? 'is-active' : ''} onClick={() => setCategory(name)}>{name}</button>)}
        </div>
        <div class="store-grid">
          {items.filter((item) => category === 'all' || item.category === category).map((item) => {
            const itemBuyError = buyErrors[item.id];
            const isBuying = buying[item.id] === true;
            return (
              <div key={item.id} class="card store-card">
                <div class="store-emoji">{item.effect.emoji}</div>
                <small class="store-category">{item.category}</small>
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
