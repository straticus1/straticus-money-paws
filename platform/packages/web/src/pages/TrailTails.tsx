import { useEffect, useState } from 'preact/hooks';
import type {
  Pet,
  TrailDirection,
  TrailTailsAction,
  TrailTailsResponse,
  TrailTile,
} from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';
import { petEmoji } from '../lib/pets';

const directionGlyph: Record<TrailDirection, string> = {
  north: '↑',
  east: '→',
  south: '↓',
  west: '←',
};

const terrainDetails = {
  meadow: { icon: '✿', label: 'Meadow' },
  creek: { icon: '≈', label: 'Creek' },
  brambles: { icon: '⌇', label: 'Brambles' },
  lookout: { icon: '△', label: 'Lookout' },
} as const;

function tileLabel(tile: TrailTile, current: boolean): string {
  if (!tile.discovered) return `Unexplored tile ${tile.position + 1}`;
  const terrain = tile.terrain === undefined ? 'trail' : terrainDetails[tile.terrain].label;
  const objective = tile.objective === undefined ? '' : `, ${tile.objective}`;
  return `${terrain}${objective}${current ? ', your current position' : ''}`;
}

export function TrailTails() {
  const [pets, setPets] = useState<Pet[]>([]);
  const [selectedPetId, setSelectedPetId] = useState('');
  const [response, setResponse] = useState<TrailTailsResponse | null>(null);
  const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [dashMode, setDashMode] = useState(false);

  useEffect(() => {
    call(() => client.pets())
      .then((items) => {
        const available = items.filter((pet) => pet.alive);
        setPets(available);
        setSelectedPetId(available[0]?.id ?? '');
      })
      .catch((reason) => setError(reason instanceof Error ? reason.message : 'Unable to load pets'))
      .finally(() => setLoading(false));
  }, []);

  async function begin() {
    if (selectedPetId === '' || busy) return;
    setBusy(true);
    setError(null);
    try {
      setResponse(await call(() => client.startTrailTails(selectedPetId)));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Unable to start the trail');
    } finally {
      setBusy(false);
    }
  }

  async function takeAction(action: TrailTailsAction) {
    const game = response?.game;
    if (game === undefined || game.status !== 'active' || busy) return;
    setBusy(true);
    setError(null);
    try {
      setResponse(await call(() => client.actTrailTails(game.id, action)));
      setDashMode(false);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'The trail did not accept that move');
    } finally {
      setBusy(false);
    }
  }

  function travel(direction: TrailDirection) {
    void takeAction({ action: dashMode ? 'dash' : 'move', direction });
  }

  useEffect(() => {
    function handleKey(event: KeyboardEvent) {
      if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement) return;
      const direction: Partial<Record<string, TrailDirection>> = {
        ArrowUp: 'north',
        ArrowRight: 'east',
        ArrowDown: 'south',
        ArrowLeft: 'west',
      };
      const selected = direction[event.key];
      if (selected !== undefined && response?.game.status === 'active') {
        event.preventDefault();
        travel(selected);
      }
    }
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [response, busy, dashMode]);

  const game = response?.game;

  return (
    <>
      <Header />
      <main class="trail-page">
        <header class="trail-masthead">
          <a class="trail-back" href="#/games">← all games</a>
          <p class="trail-kicker">A pocket expedition for you and your pet</p>
          <h1>Trail Tails</h1>
          <p class="trail-deck">Find the lost keepsake. Help a friend if you dare. Make it home together.</p>
        </header>

        {loading && <Notice type="loading" message="Opening the trail journal…" />}
        {error !== null && <Notice type="error" message={error} />}
        {response?.message !== undefined && <Notice type="info" message={response.message} />}

        {game === undefined && !loading && (
          <section class="trail-departure" aria-labelledby="choose-companion">
            <div class="trail-departure__copy">
              <span class="trail-chapter">Chapter one</span>
              <h2 id="choose-companion">Choose your trail companion</h2>
              <p>Every living pet can explore. Care stats never change your odds or your reward.</p>
            </div>
            {pets.length === 0 ? (
              <div class="trail-empty">
                <span aria-hidden="true">🪹</span>
                <p>You need a living pet before setting out.</p>
                <a class="btn btn--primary" href="#/">Visit your pets</a>
              </div>
            ) : (
              <div class="trail-companions">
                {pets.map((pet) => (
                  <label class={`trail-companion${selectedPetId === pet.id ? ' trail-companion--selected' : ''}`}>
                    <input
                      type="radio"
                      name="trail-pet"
                      value={pet.id}
                      checked={selectedPetId === pet.id}
                      onChange={() => setSelectedPetId(pet.id)}
                    />
                    <span class="trail-companion__portrait" aria-hidden="true">{petEmoji(pet.species)}</span>
                    <span><strong>{pet.name}</strong><small>{pet.species}</small></span>
                  </label>
                ))}
                <button type="button" class="trail-begin" onClick={begin} disabled={busy}>
                  {busy ? 'Packing…' : 'Begin the expedition →'}
                </button>
              </div>
            )}
          </section>
        )}

        {game !== undefined && (
          <div class="trail-layout">
            <section class="trail-map-wrap" aria-label="Trail map">
              <div class="trail-map-heading">
                <div>
                  <span class="trail-chapter">Field map · turn {game.turns}</span>
                  <h2>{game.pet.name}'s trail</h2>
                </div>
                <div class="trail-energy" aria-label={`${game.energy} energy remaining`}>
                  <span>energy</span>
                  <strong>{game.energy}</strong>
                </div>
              </div>
              <div class="trail-map">
                {game.tiles.map((tile) => {
                  const current = tile.position === game.position;
                  const details = tile.terrain === undefined ? undefined : terrainDetails[tile.terrain];
                  return (
                    <div
                      key={tile.position}
                      class={`trail-tile ${tile.discovered ? `trail-tile--${tile.terrain}` : 'trail-tile--hidden'}${current ? ' trail-tile--current' : ''}`}
                      aria-label={tileLabel(tile, current)}
                    >
                      {tile.discovered ? (
                        <>
                          <span class="trail-tile__terrain" aria-hidden="true">{details?.icon}</span>
                          {tile.objective === 'home' && <span class="trail-tile__objective" title="Home">⌂</span>}
                          {tile.objective === 'keepsake' && <span class="trail-tile__objective" title="Keepsake">✦</span>}
                          {tile.objective === 'rescue' && <span class="trail-tile__objective" title="Lost friend">♡</span>}
                          {current && <span class="trail-pet-marker" title={game.pet.name}>{petEmoji(game.pet.species)}</span>}
                        </>
                      ) : <span class="trail-tile__unknown" aria-hidden="true">?</span>}
                    </div>
                  );
                })}
              </div>
              <div class="trail-legend" aria-label="Map legend">
                <span><b>✿</b> meadow</span><span><b>≈</b> creek</span>
                <span><b>⌇</b> brambles</span><span><b>△</b> lookout</span>
              </div>
            </section>

            <aside class="trail-journal">
              <div class="trail-journal__pet">
                <span aria-hidden="true">{petEmoji(game.pet.species)}</span>
                <div><small>exploring with</small><strong>{game.pet.name}</strong></div>
              </div>

              <section class="trail-objectives" aria-labelledby="trail-objectives-title">
                <h3 id="trail-objectives-title">Trail notes</h3>
                <p class={game.keepsakeFound ? 'is-complete' : ''}>
                  <span>{game.keepsakeFound ? '✓' : '○'}</span> Recover the lost keepsake
                </p>
                <p class={game.rescueFound ? 'is-complete' : ''}>
                  <span>{game.rescueFound ? '✓' : '○'}</span> Help the lost trail friend
                </p>
                <p class={game.status === 'completed' ? 'is-complete' : ''}>
                  <span>{game.status === 'completed' ? '✓' : '○'}</span> Return home safely
                </p>
              </section>

              {game.status === 'active' ? (
                <section class="trail-controls" aria-labelledby="trail-controls-title">
                  <h3 id="trail-controls-title">Choose the next step</h3>
                  <div class="trail-directions">
                    {(['north', 'west', 'south', 'east'] as TrailDirection[]).map((direction) => (
                      <button
                        type="button"
                        class={`trail-direction trail-direction--${direction}`}
                        disabled={busy || !game.legalDirections.includes(direction)}
                        onClick={() => travel(direction)}
                        aria-label={`${dashMode ? 'Dash' : 'Move'} ${direction}`}
                      >
                        {directionGlyph[direction]}
                      </button>
                    ))}
                  </div>
                  <p class="trail-control-hint">Arrow keys work too.</p>
                  <div class="trail-kit">
                    <button type="button" disabled={busy || game.abilities.sniff === 0} onClick={() => takeAction({ action: 'sniff' })}>
                      <span>👃</span><strong>Sniff</strong><small>{game.abilities.sniff} left</small>
                    </button>
                    <button
                      type="button"
                      class={dashMode ? 'is-active' : ''}
                      disabled={busy || game.abilities.dash === 0}
                      onClick={() => setDashMode((active) => !active)}
                      aria-pressed={dashMode}
                    >
                      <span>💨</span><strong>Dash</strong><small>{game.abilities.dash} left</small>
                    </button>
                    <button type="button" disabled={busy || game.abilities.rest === 0} onClick={() => takeAction({ action: 'rest' })}>
                      <span>🍃</span><strong>Rest</strong><small>{game.abilities.rest} left</small>
                    </button>
                  </div>
                  {dashMode && <p class="trail-dash-note">Dash packed—choose a revealed meadow next.</p>}
                </section>
              ) : (
                <section class="trail-finale" aria-live="polite">
                  <span class="trail-finale__stars" aria-label={`${game.stars} stars`}>
                    {'★'.repeat(game.stars)}{'☆'.repeat(3 - game.stars)}
                  </span>
                  <h3>{game.status === 'completed' ? 'Home before sundown' : 'Safe, but out of energy'}</h3>
                  <p>{game.rewardMinor} PAWS · {game.turns} turns</p>
                  <button type="button" class="trail-begin" onClick={begin} disabled={busy}>Walk another trail →</button>
                </section>
              )}

              <p class="trail-safety">Free to play. The server owns the hidden map and reward. USD and crypto never enter the trail.</p>
            </aside>
          </div>
        )}
      </main>
    </>
  );
}
