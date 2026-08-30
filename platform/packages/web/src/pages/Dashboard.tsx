import { useState, useEffect } from 'preact/hooks';
import type { InventoryItem, Pet } from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';
import { PET_EMOJI, PET_SPECIES, petEmoji, type PetSpecies } from '../lib/pets';

function StatBar({ value, color }: { value: number; color: string }) {
  const pct = Math.min(100, Math.max(0, value));
  return (
    <div class="stat-bar">
      <div class="stat-bar__fill" style={{ width: `${pct}%`, background: color }} />
    </div>
  );
}

function FeedModal({
  pet,
  inventory,
  onFeed,
  onClose,
}: {
  pet: Pet;
  inventory: InventoryItem[];
  onFeed: (itemId: string) => void;
  onClose: () => void;
}) {
  const available = inventory.filter((i) => i.quantity > 0 && (i.category === 'food' || i.category === 'treat'));
  return (
    <div class="modal-overlay" onClick={onClose}>
      <div class="modal" onClick={(e) => e.stopPropagation()}>
        <h3>Feed {pet.name}</h3>
        {available.length === 0 ? (
          <p>No food items in inventory — visit the store!</p>
        ) : (
          <ul class="feed-list">
            {available.map((item) => (
              <li key={item.itemId}>
                <button class="btn" onClick={() => onFeed(item.itemId)}>
                  {item.effect?.emoji !== undefined ? `${item.effect.emoji} ` : ''}
                  {item.name} ×{item.quantity}
                </button>
              </li>
            ))}
          </ul>
        )}
        <button class="btn btn--ghost" onClick={onClose}>
          Cancel
        </button>
      </div>
    </div>
  );
}

export function Dashboard() {
  const [pets, setPets] = useState<Pet[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [newName, setNewName] = useState('');
  const [newSpecies, setNewSpecies] = useState<PetSpecies>('dog');
  const [createError, setCreateError] = useState<string | null>(null);
  const [feedingPet, setFeedingPet] = useState<Pet | null>(null);
  const [inventory, setInventory] = useState<InventoryItem[]>([]);
  const [feedError, setFeedError] = useState<string | null>(null);

  useEffect(() => {
    call(() => client.pets())
      .then(setPets)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load pets'))
      .finally(() => setLoading(false));
  }, []);

  async function handleCreate(e: Event) {
    e.preventDefault();
    if (newName.trim() === '') return;
    setCreateError(null);
    try {
      const pet = await call(() => client.createPet(newName.trim(), newSpecies));
      setPets((prev) => [...prev, pet]);
      setNewName('');
    } catch (e) {
      setCreateError(e instanceof Error ? e.message : 'Failed to create pet');
    }
  }

  async function openFeed(pet: Pet) {
    setFeedError(null);
    const inv = await call(() => client.inventory()).catch(() => [] as InventoryItem[]);
    setInventory(inv);
    setFeedingPet(pet);
  }

  async function handleFeed(itemId: string) {
    if (feedingPet === null) return;
    const target = feedingPet;
    setFeedingPet(null);
    try {
      const updated = await call(() => client.feedPet(target.id, itemId));
      setPets((prev) => prev.map((p) => (p.id === updated.id ? updated : p)));
    } catch (e) {
      setFeedError(e instanceof Error ? e.message : 'Failed to feed pet');
    }
  }

  return (
    <>
      <Header />
      <main class="main">
        <h1>Your Pets</h1>

        {loading && <Notice type="loading" message="Loading pets…" />}
        {error !== null && <Notice type="error" message={error} />}
        {feedError !== null && <Notice type="error" message={feedError} />}

        {!loading && (
          <div class="pet-grid">
            {pets.map((pet) => (
              <div key={pet.id} class={`card pet-card${pet.alive ? '' : ' pet-card--dead'}`}>
                <div class="pet-emoji">{petEmoji(pet.species)}</div>
                <h3>{pet.name}</h3>
                <p class="pet-species">{pet.species}</p>
                {!pet.alive && <p class="pet-dead-label">Passed Away</p>}
                <div class="pet-stats">
                  <label>Hunger</label>
                  <StatBar value={pet.hunger} color="#e8853d" />
                  <label>Happiness</label>
                  <StatBar value={pet.happiness} color="#6baa75" />
                  <label>Health</label>
                  <StatBar value={pet.health} color="#5b9bd5" />
                </div>
                {pet.alive && (
                  <button class="btn" onClick={() => openFeed(pet)}>
                    Feed
                  </button>
                )}
              </div>
            ))}
            {pets.length === 0 && (
              <p style="color:#888;font-size:0.9rem;">
                No pets yet — adopt one below!
              </p>
            )}
          </div>
        )}

        <section class="new-pet-section">
          <h2>Adopt a Pet</h2>
          {createError !== null && <Notice type="error" message={createError} />}
          <form class="new-pet-form" onSubmit={handleCreate}>
            <input
              type="text"
              placeholder="Pet name"
              value={newName}
              onInput={(e) => setNewName((e.target as HTMLInputElement).value)}
              required
            />
            <select
              value={newSpecies}
              onChange={(e) => setNewSpecies((e.target as HTMLSelectElement).value as PetSpecies)}
            >
              {PET_SPECIES.map((s) => (
                <option key={s} value={s}>
                  {PET_EMOJI[s]} {s}
                </option>
              ))}
            </select>
            <button type="submit" class="btn btn--primary">
              Adopt
            </button>
          </form>
        </section>

        {feedingPet !== null && (
          <FeedModal
            pet={feedingPet}
            inventory={inventory}
            onFeed={handleFeed}
            onClose={() => setFeedingPet(null)}
          />
        )}
      </main>
    </>
  );
}
