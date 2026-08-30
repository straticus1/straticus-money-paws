import { useEffect, useRef, useState } from 'preact/hooks';
import type { PawMatchResponse } from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';

function message(response: PawMatchResponse): string | null {
  if (response.outcome === 'match') return 'Match! Keep going.';
  if (response.outcome === 'miss') return 'Not a match—remember where they were.';
  if (response.outcome === 'completed') return response.game.rewardMinor === '0' ? 'Board complete! This round was just for fun.' : `Board complete! ${response.game.rewardMinor} PAWS were posted.`;
  return null;
}

export function PawMatch() {
  const [response, setResponse] = useState<PawMatchResponse | null>(null); const [busy, setBusy] = useState(false);
  const [loading, setLoading] = useState(true); const [error, setError] = useState<string | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  useEffect(() => { call(() => client.startPawMatch()).then(setResponse).catch((e) => setError(e instanceof Error ? e.message : 'Unable to start')).finally(() => setLoading(false)); return () => { if (timer.current) clearTimeout(timer.current); }; }, []);
  async function flip(position: number) { const game = response?.game; if (!game || busy || game.status !== 'active') return; setBusy(true); setError(null); try { const next = await call(() => client.flipPawMatch(game.id, position)); setResponse(next); if (next.outcome === 'miss') timer.current = setTimeout(() => { call(() => client.pawMatch(game.id)).then(setResponse).finally(() => setBusy(false)); }, 900); else setBusy(false); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Move failed'); setBusy(false); } }
  async function newGame() { setLoading(true); try { setResponse(await call(() => client.startPawMatch())); } finally { setLoading(false); } }
  const game = response?.game; const note = response ? message(response) : null;
  return <><Header /><main class="main game-page"><a href="#/games" class="game-back">← all games</a><div class="game-hero"><div><p class="game-eyebrow">Free to play · PAWS rewards</p><h1>🐾 Paw Match</h1><p>Find all six pairs. Fewer moves earn a larger reward.</p></div>{game && <div class="game-score"><span><strong>{game.moves}</strong> moves</span><span><strong>{game.matchedPairs}</strong>/6 pairs</span></div>}</div>{loading && <Notice type="loading" message="Shuffling the paws…" />}{error && <Notice type="error" message={error} />}{note && <Notice type="info" message={note} />}{game && <section class="paw-board" aria-label="Paw Match board">{game.cards.map((card) => <button key={card.position} type="button" class={`paw-card${card.symbol ? ' paw-card--visible' : ''}${card.matched ? ' paw-card--matched' : ''}`} disabled={busy || card.matched || game.status !== 'active' || game.firstPosition === card.position} onClick={() => flip(card.position)} aria-label={card.symbol ? `Card ${card.position + 1}: ${card.symbol}` : `Flip card ${card.position + 1}`}><span aria-hidden="true">{card.symbol ?? '?'}</span></button>)}</section>}{game?.status === 'completed' && <div class="game-complete card"><div><h2>Round complete</h2><p>{game.moves} moves · {game.rewardMinor} PAWS</p></div><button class="btn btn--primary" onClick={newGame}>Play again</button></div>}</main></>;
}
