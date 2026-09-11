import { render } from 'preact';
import { useState } from 'preact/hooks';
import { HomePet } from './components/HomePet';
import { ItemArt } from './components/ItemArt';
import { itemArtwork, petArtwork, petArtDescriptions } from './lib/art';
import roomBackdrop from './assets/home/clover-room-v1.webp';
import './pages/home.css';
import './pages/companion.css';
import './art-preview.css';
import './pages/home-accessibility.css';

/** Development-only visual harness: no API calls, account data or game rewards. */
function ArtPreview() {
  const [species, setSpecies] = useState('dog');
  const [painted, setPainted] = useState(true);
  return <main class="pet-home art-preview">
    <header class="home-heading"><div><p class="home-kicker">PAWS · ART STUDIO</p><h1>A world<br /><em>taking shape.</em></h1></div></header>
    <p class="art-preview-note">Local art preview. No account or game progress is changed. Choose a pet below; its description will be read aloud by your screen reader.</p>
    <div class="home-layout">
      <section class="home-scene-panel" aria-label="Room artwork preview">
        <div class="home-scene-toolbar"><span>THE CLOVER ROOM</span><label>Pet artwork <select value={painted ? 'painted' : 'classic'} onChange={(event) => setPainted(event.currentTarget.value === 'painted')}><option value="painted">Painted</option><option value="classic">Classic</option></select></label></div>
        <div class="home-room">
          <img class="home-room-backdrop" src={roomBackdrop} alt="" draggable={false} />
          <div class="home-resident" role="img" aria-label={`${species} artwork`}><HomePet species={species} painted={painted} /></div>
          <div class="home-placement-grid" aria-label="Item artwork samples">{Array.from(itemArtwork.keys()).map((name) => <div class="home-floor-cell is-furnished" key={name} title={name}><ItemArt name={name} /><small>{name}</small></div>)}</div>
        </div>
        <p class="home-scene-caption" role="status" aria-atomic="true">{painted && petArtwork.has(species) ? petArtDescriptions.get(species) : `Showing the classic ${species} artwork.`}</p>
        <details class="home-room-description" open><summary>Room description</summary><p>A sunny cottage with honey-colored wooden floors, an arched garden window on the left, and sage-green shelves on the right. Your chosen pet sits in the center. The moon plush, wooden acorn, and kibble bowl sit separately on the floor.</p></details>
      </section>
      <aside class="home-companion"><p class="home-kicker">MEET THE STARTER CAST</p><div class="art-species-picker" role="group" aria-label="Choose a pet to preview">{['dog', 'cat', 'fox', 'rabbit', 'bird'].map((name) => <button key={name} class="home-primary" aria-pressed={species === name} onClick={() => setSpecies(name)}>{name}</button>)}</div><p class="art-preview-note">Dog, cat, fox and rabbit have painted artwork. Bird demonstrates the classic fallback. Classic style retains each companion’s individual coat, markings and smile details.</p></aside>
    </div>
    <section class="home-decorate"><p class="home-kicker">LITTLE THINGS TO COLLECT</p><h2>From the General Store.</h2><div class="home-item-shelf">{Array.from(itemArtwork.keys()).map((name) => <article key={name}><ItemArt name={name} /><b>{name}</b></article>)}</div></section>
    <section class="home-decorate"><p class="home-kicker">THE PAINTED PETS</p><h2>Four little personalities.</h2><div class="art-proof-grid">{Array.from(petArtwork).map(([name, source]) => <figure key={name}><img src={source} alt="" loading="lazy" /><figcaption>{petArtDescriptions.get(name)}</figcaption></figure>)}</div></section>
  </main>;
}

// Vite serves this page in development; it is not a production build entry.
if (import.meta.env.DEV) render(<ArtPreview />, document.getElementById('app')!);
