import { Header } from '../components/Header';

const games = [
  { slug: 'paw-match', mark: '🐾', title: 'Paw Match', skill: 'Memory', copy: 'Find six pairs in a warm little card cabinet.', className: 'match' },
  { slug: 'trail-tails', mark: '🧭', title: 'Trail Tails', skill: 'Exploration', copy: 'Bring home a keepsake from a pocket wilderness.', className: 'trail' },
  { slug: 'midnight-pantry', mark: '☾', title: 'Midnight Pantry', skill: 'Deduction', copy: 'Read peculiar clues and serve three perfect bowls.', className: 'pantry' },
  { slug: 'lantern-lines', mark: '⌁', title: 'Lantern Lines', skill: 'Networks', copy: 'Turn glass paths until every hillside window glows.', className: 'lantern' },
  { slug: 'pocket-post', mark: '✉', title: 'Pocket Post', skill: 'Foresight', copy: 'Push the morning parcels onto their dispatch mats.', className: 'post' },
  { slug: 'parade-practice', mark: '♫', title: 'Parade Practice', skill: 'Planning', copy: 'Build a command-card routine for the parade leader.', className: 'parade' },
] as const;

export function Games() {
  return <><Header /><main class="games-library"><header class="games-library__header"><p>paws.money field collection · volume one</p><h1>Six small worlds.<br />One favorite pet.</h1><span>Every game is free to play, server-scored, and safe after the daily PAWS cap.</span></header><section class="games-grid" aria-label="Game collection">{games.map((game, index) => <a href={`#/games/${game.slug}`} class={`game-tile game-tile--${game.className}`} key={game.slug}><span class="game-tile__number">0{index + 1}</span><span class="game-tile__mark" aria-hidden="true">{game.mark}</span><small>{game.skill}</small><h2>{game.title}</h2><p>{game.copy}</p><strong>Open game →</strong></a>)}</section><p class="games-library__footnote">Rewards are internal PAWS only. No game accepts USD, crypto, deposits, wagers, or browser-supplied scores.</p></main></>;
}
