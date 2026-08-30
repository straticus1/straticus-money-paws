import { useEffect, useState } from 'preact/hooks';
import type {
  MidnightPantryAction,
  MidnightPantryResponse,
  PantryGuestId,
  PantryIngredient,
  PantryIngredientId,
  Pet,
} from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';
import { petEmoji } from '../lib/pets';

function ingredientById(
  ingredients: PantryIngredient[],
  id: PantryIngredientId | null,
): PantryIngredient | undefined {
  return id === null ? undefined : ingredients.find((ingredient) => ingredient.id === id);
}

export function MidnightPantry() {
  const [pets, setPets] = useState<Pet[]>([]);
  const [selectedPetId, setSelectedPetId] = useState('');
  const [selectedIngredientId, setSelectedIngredientId] = useState<PantryIngredientId | null>(null);
  const [response, setResponse] = useState<MidnightPantryResponse | null>(null);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    call(() => client.pets())
      .then((items) => {
        const available = items.filter((pet) => pet.alive);
        setPets(available);
        setSelectedPetId(available[0]?.id ?? '');
      })
      .catch((reason) => setError(reason instanceof Error ? reason.message : 'Unable to open the pantry'))
      .finally(() => setLoading(false));
  }, []);

  async function begin(mode: 'daily' | 'practice') {
    if (selectedPetId === '' || busy) return;
    setBusy(true);
    setError(null);
    setSelectedIngredientId(null);
    try {
      setResponse(await call(() => client.startMidnightPantry(selectedPetId, mode)));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'The pantry door would not open');
    } finally {
      setBusy(false);
    }
  }

  async function takeAction(action: MidnightPantryAction) {
    const game = response?.game;
    if (game === undefined || game.status !== 'active' || busy) return;
    setBusy(true);
    setError(null);
    try {
      setResponse(await call(() => client.actMidnightPantry(game.id, action)));
      setSelectedIngredientId(null);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'The order could not be changed');
    } finally {
      setBusy(false);
    }
  }

  function useSlot(guestId: PantryGuestId, slot: 0 | 1) {
    const game = response?.game;
    if (game === undefined || game.lockedGuests.includes(guestId)) return;
    const current = game.placements[guestId][slot];
    if (current !== null) {
      void takeAction({ action: 'remove', guestId, slot });
    } else if (selectedIngredientId !== null) {
      void takeAction({ action: 'place', guestId, slot, ingredientId: selectedIngredientId });
    }
  }

  const game = response?.game;

  return (
    <>
      <Header />
      <main class="pantry-page">
        <header class="pantry-masthead">
          <a class="pantry-back" href="#/games">← all games</a>
          <div class="pantry-sign">
            <span class="pantry-sign__moon" aria-hidden="true">☾</span>
            <p>Open nightly · bell service</p>
            <h1>Midnight Pantry</h1>
            <span>Small bowls. Particular guests. One very late shift.</span>
          </div>
        </header>

        {loading && <Notice type="loading" message="Lighting the shop lamps…" />}
        {error !== null && <Notice type="error" message={error} />}
        {response?.message !== undefined && <Notice type="info" message={response.message} />}

        {game === undefined && !loading && (
          <section class="pantry-opening" aria-labelledby="pantry-host-title">
            <div class="pantry-opening__story">
              <span class="pantry-overline">Tonight's proprietor</span>
              <h2 id="pantry-host-title">Who takes the late shift?</h2>
              <p>Read each guest's clues, divide six ingredients between three bowls, then ring for service.</p>
              <ol>
                <li>Every ingredient is used exactly once.</li>
                <li>A correct order locks that guest's bowl.</li>
                <li>Wrong orders ring the bell; solve cleanly for three stars.</li>
              </ol>
            </div>
            <div class="pantry-hosts">
              {pets.length === 0 ? (
                <div class="pantry-no-pets">
                  <span aria-hidden="true">🌙</span>
                  <p>A living pet is needed to tend the counter.</p>
                  <a href="#/" class="pantry-button">Visit your pets</a>
                </div>
              ) : (
                <>
                  <div class="pantry-pet-list">
                    {pets.map((pet) => (
                      <label class={`pantry-pet${selectedPetId === pet.id ? ' pantry-pet--selected' : ''}`} key={pet.id}>
                        <input
                          type="radio"
                          name="pantry-pet"
                          checked={selectedPetId === pet.id}
                          onChange={() => setSelectedPetId(pet.id)}
                        />
                        <span aria-hidden="true">{petEmoji(pet.species)}</span>
                        <span><strong>{pet.name}</strong><small>{pet.species}</small></span>
                      </label>
                    ))}
                  </div>
                  <div class="pantry-start-buttons">
                    <button class="pantry-button" type="button" onClick={() => begin('daily')} disabled={busy}>
                      {busy ? 'Unlocking…' : 'Begin tonight’s service'}
                    </button>
                    <button class="pantry-button pantry-button--quiet" type="button" onClick={() => begin('practice')} disabled={busy}>
                      Practice without rewards
                    </button>
                  </div>
                </>
              )}
            </div>
          </section>
        )}

        {game !== undefined && (
          <div class="pantry-game">
            <section class="pantry-counter" aria-label="Guest orders">
              <div class="pantry-counter__heading">
                <div>
                  <span class="pantry-overline">{game.mode} service · hosted by {game.pet.name}</span>
                  <h2>Three orders before moonset</h2>
                </div>
                <div class="pantry-bells" aria-label={`${game.bellRings} of ${game.bellLimit} bells used`}>
                  <small>bell rings</small>
                  <strong>{game.bellRings}<i>/ {game.bellLimit}</i></strong>
                </div>
              </div>

              <div class="pantry-guests">
                {game.guests.map((guest) => {
                  const locked = game.lockedGuests.includes(guest.id);
                  return (
                    <article class={`pantry-guest${locked ? ' pantry-guest--served' : ''}`} key={guest.id}>
                      <div class="pantry-guest__identity">
                        <span class="pantry-guest__portrait" aria-hidden="true">{guest.portrait}</span>
                        <div><small>{guest.species}</small><h3>{guest.name}</h3></div>
                        {locked && <span class="pantry-served-stamp">served</span>}
                      </div>
                      <ul class="pantry-ticket">
                        {guest.clues.map((clue) => <li key={clue}>{clue}</li>)}
                      </ul>
                      <div class="pantry-bowl" aria-label={`${guest.name}'s bowl`}>
                        {([0, 1] as const).map((slot) => {
                          const ingredient = ingredientById(game.ingredients, game.placements[guest.id][slot]);
                          return (
                            <button
                              type="button"
                              class={`pantry-bowl__slot${ingredient === undefined ? '' : ' is-filled'}`}
                              disabled={busy || locked || (ingredient === undefined && selectedIngredientId === null)}
                              onClick={() => useSlot(guest.id, slot)}
                              aria-label={ingredient === undefined
                                ? `Put selected ingredient in ${guest.name}'s bowl`
                                : `Remove ${ingredient.name} from ${guest.name}'s bowl`}
                            >
                              {ingredient === undefined
                                ? <span aria-hidden="true">+</span>
                                : <><span aria-hidden="true">{ingredient.icon}</span><small>{ingredient.name}</small></>}
                            </button>
                          );
                        })}
                      </div>
                      <button
                        type="button"
                        class="pantry-serve"
                        disabled={busy || locked || game.placements[guest.id].includes(null)}
                        onClick={() => takeAction({ action: 'serve', guestId: guest.id })}
                      >
                        {locked ? '✓ Order served' : '🔔 Ring for service'}
                      </button>
                    </article>
                  );
                })}
              </div>
            </section>

            <aside class="pantry-shelf" aria-label="Ingredient shelf">
              <div class="pantry-shelf__title">
                <span aria-hidden="true">✦</span>
                <div><small>mise en place</small><h2>Night shelf</h2></div>
              </div>
              <p class="pantry-shelf__hint">
                {selectedIngredientId === null ? 'Choose an ingredient, then an empty bowl slot.' : 'Now choose an empty bowl slot.'}
              </p>
              <div class="pantry-ingredients">
                {game.ingredients.map((ingredient) => {
                  const available = game.availableIngredientIds.includes(ingredient.id);
                  const selected = selectedIngredientId === ingredient.id;
                  return (
                    <button
                      type="button"
                      class={`pantry-ingredient${selected ? ' is-selected' : ''}`}
                      key={ingredient.id}
                      disabled={busy || !available || game.status !== 'active'}
                      aria-pressed={selected}
                      onClick={() => setSelectedIngredientId(selected ? null : ingredient.id)}
                    >
                      <span class="pantry-ingredient__icon" aria-hidden="true">{ingredient.icon}</span>
                      <span><strong>{ingredient.name}</strong><small>{ingredient.temperature} · {ingredient.texture} · {ingredient.family}</small></span>
                    </button>
                  );
                })}
              </div>

              {game.status === 'active' ? (
                <button class="pantry-abandon" type="button" disabled={busy} onClick={() => takeAction({ action: 'abandon' })}>
                  Close up for the night
                </button>
              ) : (
                <div class="pantry-receipt" aria-live="polite">
                  <small>service receipt</small>
                  <span class="pantry-receipt__stars" aria-label={`${game.stars} stars`}>
                    {'★'.repeat(game.stars)}{'☆'.repeat(3 - game.stars)}
                  </span>
                  <h3>{game.status === 'completed' ? 'The guests leave glowing.' : 'The shutters are closed.'}</h3>
                  <dl>
                    <div><dt>Bells</dt><dd>{game.bellRings}</dd></div>
                    <div><dt>PAWS</dt><dd>{game.rewardMinor}</dd></div>
                    <div><dt>Journal</dt><dd>{game.journalCredited ? 'stamped' : '—'}</dd></div>
                  </dl>
                  <button class="pantry-button" type="button" onClick={() => begin(game.mode)} disabled={busy}>
                    Open another shift
                  </button>
                </div>
              )}
              <p class="pantry-security">The server keeps the recipe, validates every bowl, and calculates rewards. USD and crypto never enter the game.</p>
            </aside>
          </div>
        )}
      </main>
    </>
  );
}
