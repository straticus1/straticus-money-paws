import { useEffect, useState } from 'preact/hooks';
import type { LeaderboardEntry, Trophy } from '@paws/core';
import { Header } from '../components/Header';
import { Notice } from '../components/Notice';
import { call, client } from '../lib/api';

export function Honors() {
  const [trophies, setTrophies] = useState<Trophy[]>([]);
  const [leaders, setLeaders] = useState<LeaderboardEntry[]>([]);
  const [scoring, setScoring] = useState('');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([
      call(() => client.trophies()),
      client.leaderboard(),
    ]).then(([earned, board]) => {
      setTrophies(earned);
      setLeaders(board.entries);
      setScoring(board.scoring);
    }).catch((cause) => setError(cause instanceof Error ? cause.message : 'The honors board is unavailable.'));
  }, []);

  return <>
    <Header />
    <main class="main honors-page">
      <section class="honors-hero">
        <p class="eyebrow">THE NEIGHBORHOOD CLUBHOUSE</p>
        <h1>Honors Wall</h1>
        <p>Small victories, carefully recorded. Every pin comes from a result verified by the game server.</p>
      </section>
      {error && <Notice type="error" message={error} />}
      <section class="trophy-board" aria-labelledby="trophy-heading">
        <div class="section-heading">
          <div><p class="eyebrow">YOUR COLLECTION</p><h2 id="trophy-heading">Field Pins</h2></div>
          <span>{trophies.filter((trophy) => trophy.earned).length}/{trophies.length} earned</span>
        </div>
        <div class="trophy-grid">
          {trophies.map((trophy) => <article class={`trophy-pin${trophy.earned ? ' trophy-pin--earned' : ''}`} key={trophy.key}>
            <span class="trophy-pin__icon" aria-hidden="true">{trophy.earned ? trophy.icon : '✦'}</span>
            <div><h3>{trophy.name}</h3><p>{trophy.description}</p>
              <small>{trophy.earned ? `Earned ${new Date(trophy.earnedAt!).toLocaleDateString()}` : 'Still out there'}</small>
            </div>
          </article>)}
        </div>
      </section>
      <section class="leaderboard-ledger" aria-labelledby="leaderboard-heading">
        <div class="section-heading">
          <div><p class="eyebrow">OPT-IN RANKINGS</p><h2 id="leaderboard-heading">Trail Register</h2></div>
          <span title={scoring}>Verified play only</span>
        </div>
        {leaders.length === 0 ? <p class="empty-ledger">No neighbors have pinned their name to the register yet. You can opt in from Settings.</p> :
          <ol class="leader-list">
            {leaders.map((entry) => <li key={entry.username}>
              <span class="leader-rank">{String(entry.rank).padStart(2, '0')}</span>
              <strong>{entry.username}</strong>
              <span>{entry.completions} finishes</span><span>{entry.stars} stars</span>
              <b>{entry.score}</b>
            </li>)}
          </ol>}
        <p class="privacy-note">Wallet balances, email addresses, and reward amounts never appear here.</p>
      </section>
    </main>
  </>;
}
