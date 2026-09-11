import { useEffect, useRef, useState } from 'preact/hooks';
import { ApiError, type HomeAction, type HomeCommand, type PetHome } from '@paws/core';
import { Header } from '../components/Header';
import { HomePet } from '../components/HomePet';
import { CompanionStory } from '../components/CompanionStory';
import { call, client } from '../lib/api';
import { petEmoji } from '../lib/pets';
import './home.css';
import './companion.css';

const messages: Record<string, string> = {
  stale_home: 'Your home changed in another window. We’ve refreshed it; please try again.',
  action_conflict: 'That request could not be safely repeated. Your home has been refreshed.',
  care_cooldown: 'A little breather. Your pet will be ready for more attention shortly.',
  home_rate_limited: 'Let’s slow down for a moment. Please try again in a minute.',
  item_not_owned: 'That item is no longer in your inventory.',
  all_copies_placed: 'Every copy of that item is already in the room.',
  position_occupied: 'There’s already something in that spot.',
  pet_dead: 'This pet can no longer take part in care activities.',
  adventure_already_started: 'You already have a companion for today’s adventure. Your home has been refreshed.',
  adventure_not_found: 'There isn’t an active adventure for this companion today.',
  adventure_not_ready: 'Share affection, feed your companion, and finish a new game before collecting the keepsake.',
  adventure_already_claimed: 'Today’s keepsake is already in your bag.',
  bandana_locked: 'Keep building your bond to unlock that bandana.',
};

export function Home() {
  const [home, setHome] = useState<PetHome | null>(null);
  const [petId, setPetId] = useState('');
  const [busy, setBusy] = useState(true);
  const [error, setError] = useState('');
  const [notice, setNotice] = useState('A quiet corner, just for you and your pets.');
  const [decorating, setDecorating] = useState(false);
  const [tool, setTool] = useState('');
  const [foodId, setFoodId] = useState('');
  const [toyId, setToyId] = useState('');
  const [reaction, setReaction] = useState('idle');
  const [pending, setPending] = useState<HomeCommand | null>(null);
  const [careWait, setCareWait] = useState(0);
  const locked = useRef(true);
  const careReady = useRef(0);

  function accept(next: PetHome) {
    setHome(next);
    // Only a display countdown. The server enforces the actual cooldown.
    careReady.current = Date.now() + Math.max(0, Date.parse(next.nextCareAt) - Date.parse(next.serverNow));
    setCareWait(Math.ceil((careReady.current - Date.now()) / 1000));
  }

  async function refresh() {
    const response = await call(() => client.home());
    accept(response.home);
  }

  useEffect(() => {
    void refresh().catch(() => setError('Your home could not be opened. Please try again.')).finally(() => { setBusy(false); locked.current = false; });
    const timer = window.setInterval(() => setCareWait(Math.max(0, Math.ceil((careReady.current - Date.now()) / 1000))), 1000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    if (reaction === 'idle') return;
    const timer = window.setTimeout(() => setReaction('idle'), 2200);
    return () => window.clearTimeout(timer);
  }, [reaction]);

  const pet = home?.pets.find((p) => p.id === petId) ?? home?.pets.find((p) => p.alive) ?? home?.pets[0];
  const foods = home?.items.filter((i) => ['food', 'treat'].includes(i.category)) ?? [];
  const toys = home?.items.filter((i) => i.category === 'toy') ?? [];
  const food = foods.find((i) => i.itemId === foodId) ?? foods[0];
  const toy = toys.find((i) => i.itemId === toyId) ?? toys[0];
  const disabled = busy || pending !== null;
  const careDisabled = disabled || !pet?.alive || careWait > 0;

  async function send(command: HomeCommand) {
    if (locked.current) return;
    locked.current = true;
    setBusy(true);
    setError('');
    setPending(command);
    try {
      const response = await call(() => client.actHome(command));
      accept(response.home);
      setPending(null);
      setReaction(command.action);
      setNotice(response.message ?? (command.action === 'place' ? 'Looking more like home.' : command.action === 'remove' ? 'Put away safely. It’s still yours.' : 'Another little moment together.'));
      // A replay may contain an older snapshot; fetch current room state afterward.
      try { await refresh(); } catch { setError('Your action was saved. Refresh to see any newer changes when you reconnect.'); }
    } catch (cause) {
      if (cause instanceof ApiError && cause.status < 500) {
        setPending(null);
        setError(messages[cause.code] ?? 'That action wasn’t accepted. Please try again.');
        try { await refresh(); } catch { setError('Could not refresh your home. Reconnect before trying again.'); setHome(null); }
      } else {
        // Retain the exact command, including action ID, for uncertain delivery.
        setError('The connection was interrupted. Retry the same action to safely check whether it was saved.');
      }
    } finally {
      locked.current = false;
      setBusy(false);
    }
  }

  function act(action: HomeAction) {
    if (!home || disabled) return;
    void send({ ...action, actionId: crypto.randomUUID(), expectedVersion: home.version });
  }

  async function reopen() {
    if (locked.current) return;
    locked.current = true;
    setBusy(true);
    setError('');
    try { await refresh(); } catch { setError('Your home is still unavailable. Please try again shortly.'); }
    finally { locked.current = false; setBusy(false); }
  }

  return <><Header /><main class="pet-home">
    <header class="home-heading"><div><p class="home-kicker">THE CLOVER ROOM · YOUR OWN LITTLE WORLD</p><h1>Make yourself<br /><em>at home.</em></h1></div><p>A soft place to land.<br />A familiar face to come home to.</p></header>
    {error && <div class="home-alert" role="alert"><p>{error}</p>{pending ? <button disabled={busy} onClick={() => void send(pending)}>Retry saved action</button> : <button disabled={busy} onClick={() => void reopen()}>Refresh home</button>}</div>}
    {!home ? <p role="status">{busy ? 'Opening the cottage door…' : 'Your room will be here when you reconnect.'}</p> : <>
      <div class="home-layout"><section class="home-scene-panel" aria-label="Your pet’s room">
        <div class="home-scene-toolbar"><span><i /> HOME SWEET HOME</span><button disabled={disabled} aria-pressed={decorating} onClick={() => setDecorating(!decorating)}>{decorating ? 'Done decorating' : 'Arrange room'} <span aria-hidden="true">↗</span></button></div>
        <div class={`home-room${decorating ? ' is-decorating' : ''}`}>
          <div class="home-wall" aria-hidden="true"><div class="home-window"><span class="home-sun" /><span class="home-hills" /><i /><b /></div><div class="home-picture">a place<br />to belong<span>✿</span></div><div class="home-wall-lamp" /></div>
          <div class="home-floor" aria-hidden="true" />
          {pet ? <button class={`home-resident home-personality--${pet.companion?.personality ?? 'curious'} home-resident--${reaction}${!pet.alive ? ' home-resident--remembered' : ''}`} aria-label={`Pet ${pet.name}`} disabled={careDisabled || decorating} onClick={() => act({ action: 'pet', petId: pet.id })}><HomePet species={pet.species} companion={pet.companion} />{['pet', 'play', 'feed'].includes(reaction) && <span class="home-affection" aria-hidden="true">{reaction === 'feed' ? '♡ yum' : pet.companion?.expressions.hearts ? '♥ ♡ ♥' : '♡'}</span>}</button> : <div class="home-empty-pet"><span>♡</span><p>Every home needs a friend.</p><a href="#/pets">Adopt your first pet →</a></div>}
          <div class="home-placement-grid" aria-label="Furniture positions">
            {Array.from({ length: 20 }, (_, position) => {
              const placed = home.placements.find((p) => p.position === position);
              const item = home.items.find((i) => i.itemId === placed?.itemId);
              const removable = decorating && tool === 'remove' && !!placed;
              const addable = decorating && tool !== '' && tool !== 'remove' && !placed;
              const playable = !decorating && item?.category === 'toy' && !careDisabled;
              const label = `Row ${Math.floor(position / 5) + 1}, column ${position % 5 + 1}${item ? `: ${item.name}` : ': empty'}`;
              return <button key={position} class={`home-floor-cell${placed ? ' is-furnished' : ''}`} disabled={disabled || !(removable || addable || playable)} aria-label={`${removable ? 'Put away' : addable ? 'Place item at' : playable ? 'Play with' : 'View'} ${label}`} onClick={() => {
                if (removable) act({ action: 'remove', position });
                else if (addable) act({ action: 'place', itemId: tool, position });
                else if (playable && item && pet) act({ action: 'play', itemId: item.itemId, petId: pet.id });
              }}><span aria-hidden="true">{item?.effect.emoji ?? (placed ? '▣' : '+')}</span>{item && <small>{item.name}</small>}</button>;
            })}
          </div>
        </div>
        <div class="home-scene-caption" role="status" aria-live="polite"><span aria-hidden="true">✦</span> {busy ? 'Saving your moment…' : notice}</div>
      </section>
      <aside class="home-companion">
        <p class="home-kicker">YOUR LITTLE COMPANION</p>
        {pet ? <><label class="home-pet-select">Spending time with<select value={pet.id} disabled={disabled} onChange={(e) => setPetId(e.currentTarget.value)}>{home.pets.map((p) => <option key={p.id} value={p.id}>{p.name} · {p.species}{p.alive ? '' : ' · remembered'}</option>)}</select></label><h2>{pet.name}<span aria-hidden="true">{petEmoji(pet.species)}</span></h2><p class="home-pet-mood">{!pet.alive ? 'Always part of the family.' : pet.happiness >= 80 ? 'Quite happy to see you.' : 'A little attention would be lovely.'}</p>
          <div class="home-vitals">{[['Fullness', pet.hunger], ['Happiness', pet.happiness], ['Health', pet.health]].map(([label, value]) => <label key={label}><span>{label}<b>{value}/100</b></span><meter min={0} max={100} value={Number(value)}>{value}</meter></label>)}</div>
          <button class="home-primary" disabled={careDisabled} onClick={() => act({ action: 'pet', petId: pet.id })}>♡ Give some love</button>
          <div class="home-care-line"><label>Something tasty<select aria-label="Food or treat" value={food?.itemId ?? ''} disabled={disabled || !foods.length} onChange={(e) => setFoodId(e.currentTarget.value)}>{!foods.length && <option value="">No food in your bag</option>}{foods.map((i) => <option key={i.itemId} value={i.itemId}>{i.name} ×{i.quantity}</option>)}</select></label><button disabled={careDisabled || !food} onClick={() => food && act({ action: 'feed', petId: pet.id, itemId: food.itemId })}>Feed</button></div>
          <div class="home-care-line"><label>A little playtime<select aria-label="Toy" value={toy?.itemId ?? ''} disabled={disabled || !toys.length} onChange={(e) => setToyId(e.currentTarget.value)}>{!toys.length && <option value="">No toys in your bag</option>}{toys.map((i) => <option key={i.itemId} value={i.itemId}>{i.name}</option>)}</select></label><button disabled={careDisabled || !toy} onClick={() => toy && act({ action: 'play', petId: pet.id, itemId: toy.itemId })}>Play</button></div>
          <p class="home-care-note">{careWait > 0 ? `A little breather · ready in ${careWait}s` : 'Choose a moment to share. Toys stay in your bag.'}</p>
        </> : <><h2>Room for love.</h2><p>Adopt a pet to start making memories together.</p><a class="home-primary" href="#/pets">Meet your first friend →</a></>}
        <a class="home-text-link" href="#/pets">Manage your pets →</a>
      </aside></div>
      {pet?.companion && home.adventure && <CompanionStory pet={pet} adventure={home.adventure} adventurePet={home.pets.find((p) => p.id === home.adventure.petId)} disabled={disabled} onAction={act} onChoosePet={setPetId} />}
      {decorating && <section class="home-decorate" aria-label="Furniture bag"><div><p class="home-kicker">MAKE IT YOURS</p><h2>Little things. Big personality.</h2><p>Choose an item, then an empty floor spot. Putting it away keeps it in your inventory.</p></div><div class="home-item-shelf"><button disabled={disabled} aria-pressed={tool === 'remove'} onClick={() => setTool('remove')}><span>↶</span><b>Put away</b><small>Choose a placed item</small></button>{home.items.filter((i) => i.placeable).map((item) => {
        const available = item.quantity - home.placements.filter((p) => p.itemId === item.itemId).length;
        return <button key={item.itemId} disabled={disabled || available <= 0} aria-pressed={tool === item.itemId} onClick={() => setTool(item.itemId)}><span aria-hidden="true">{item.effect.emoji ?? '▣'}</span><b>{item.name}</b><small>{available} ready to place</small></button>;
      })}<a href="#/store"><span>＋</span><b>Find something lovely</b><small>Visit the General Store</small></a></div></section>}
      <section class="home-outings" aria-label="Things to do"><div><p class="home-kicker">BEYOND THE FRONT DOOR</p><h2>A little adventure awaits.</h2></div><a href="#/games"><span aria-hidden="true">⌁</span><div><h3>Out & about</h3><p>Six small worlds to explore together.</p></div><b>↗</b></a><a href="#/store"><span aria-hidden="true">✿</span><div><h3>The General Store</h3><p>Good things for your favorite place.</p></div><b>↗</b></a></section>
    </>}
  </main></>;
}
