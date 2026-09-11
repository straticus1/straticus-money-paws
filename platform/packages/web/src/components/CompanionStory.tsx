import type { Companion, DailyAdventure, HomeAction, Pet } from '@paws/core';

const personalityCopy = {
  curious: 'Every corner hides a new discovery.',
  gentle: 'Happiest when the world slows down a little.',
  playful: 'Always ready for one more little adventure.',
};

export function CompanionStory({ pet, adventure, adventurePet, disabled, onAction, onChoosePet }: {
  pet: Pet & { companion: Companion };
  adventure: DailyAdventure;
  adventurePet: Pet | undefined;
  disabled: boolean;
  onAction: (action: HomeAction) => void;
  onChoosePet: (id: string) => void;
}) {
  const companion = pet.companion;
  const bond = companion.bond;
  const ready = adventure.affection && adventure.fed && adventure.game;
  const canClaim = ready && !adventure.claimed && !!adventurePet?.alive;
  return <section class="companion-story" aria-label="Your growing friendship">
    <article class="companion-identity">
      <p class="home-kicker">ONE OF A KIND</p>
      <h2>A {companion.personality} little soul.</h2>
      <p class="companion-personality">{personalityCopy[companion.personality]}</p>
      <div class="companion-traits"><span>{companion.appearance.coat} coat</span><span>{companion.appearance.marking}</span></div>
      <dl class="companion-favorites"><div><dt>Favorite snack</dt><dd>{companion.favorites.food}</dd></div><div><dt>Favorite toy</dt><dd>{companion.favorites.toy}</dd></div><div><dt>Favorite outing</dt><dd><a class="companion-favorite-link" href={`#/games/${companion.favorites.game.replaceAll("_", "-")}`}>{companion.favorites.gameName} →</a></dd></div></dl>
      <div class="companion-bond"><span>YOUR BOND <b>{bond.xp} XP</b></span><h3>{bond.level}</h3><progress aria-label="Bond progress to next level" value={bond.nextLevelAt === null ? 1 : bond.xp - bond.levelStart} max={bond.nextLevelAt === null ? 1 : bond.nextLevelAt - bond.levelStart} />
        <p>{bond.nextLevelAt === null ? 'A friendship worth keeping.' : `${bond.nextLevelAt - bond.xp} bond to the next chapter.`}</p>
      </div>
      <fieldset class="companion-bandanas" disabled={disabled || !pet.alive}><legend>Friendship bandanas</legend>{companion.unlocks.map((unlock) => <button key={unlock.key} class={`bandana-swatch bandana-swatch--${unlock.key}`} disabled={!unlock.unlocked} aria-pressed={companion.appearance.bandana === unlock.key} aria-label={`${unlock.key} bandana${unlock.unlocked ? '' : `, unlocks at ${unlock.xp} bond`}`} title={unlock.unlocked ? unlock.key : `${unlock.xp} bond to unlock`} onClick={() => onAction({ action: 'equip_bandana', petId: pet.id, bandana: unlock.key })}><span aria-hidden="true">{unlock.unlocked ? '◆' : '◇'}</span><small>{unlock.unlocked ? unlock.key : `${unlock.xp} XP`}</small></button>)}</fieldset>
      <p class="companion-expression-note">{companion.expressions.hearts ? 'Unlocked: a happy smile and heart greetings.' : companion.expressions.smile ? 'Happy smile unlocked. Heart greetings arrive at 60 bond.' : 'A happy smile unlocks at 20 bond.'}</p>
    </article>
    <article class="companion-adventure">
      <div class="companion-adventure-top"><p class="home-kicker">TODAY’S LITTLE ADVENTURE</p><time dateTime={adventure.date}>{new Date(`${adventure.date}T12:00:00Z`).toLocaleDateString(undefined, { month: 'short', day: 'numeric', timeZone: 'UTC' })}</time></div>
      <h2>A day to remember.</h2>
      <div class="companion-keepsake"><span aria-hidden="true">{adventure.keepsake.emoji}</span><div><small>A KEEPSAKE FOR YOUR ROOM</small><h3>{adventure.keepsake.name}</h3><p>{adventure.keepsake.description}</p></div></div>
      {!adventure.petId ? <><p>Choose {pet.name} for today’s outing. Share a little care, finish a new game, and bring a memory home.</p><button class="home-primary" disabled={disabled || !pet.alive} onClick={() => onAction({ action: 'begin_adventure', petId: pet.id })}>Spend the day with {pet.name} →</button></> : <>
        <p class="companion-day-partner">Today’s companion: <strong>{adventurePet?.name ?? 'Your pet'}</strong>{adventure.petId !== pet.id && <button disabled={disabled} onClick={() => onChoosePet(adventure.petId!)}>Spend time together</button>}</p>
        <ol class="companion-objectives">{[
          { name: 'A little affection', done: adventure.affection, detail: 'Give your companion some love at home.' },
          { name: 'A meal together', done: adventure.fed, detail: 'Feed your companion from your bag.' },
          { name: 'Out into the world', done: adventure.game, detail: adventure.gameName ? `${adventure.gameName} · remembered` : 'Start and finish a new game after beginning this adventure.' },
        ].map((objective, index) => <li key={objective.name} class={objective.done ? 'is-complete' : ''}><span aria-hidden="true">{objective.done ? '✓' : `0${index + 1}`}</span><div><strong>{objective.name}{objective.done && <span class="companion-sr"> — complete</span>}</strong><p>{objective.detail}</p></div></li>)}</ol>
        {!adventure.game && <><a class="home-text-link" href="#/games/paw-match">Play Paw Match together →</a><p class="companion-rule">All six games count, including practice. In pet-hosted games, choose {adventurePet?.name ?? 'today’s companion'} before starting.</p></>}
        <button class="home-primary" disabled={disabled || !canClaim} onClick={() => adventure.petId && onAction({ action: 'claim_adventure', petId: adventure.petId })}>{adventure.claimed ? '✓ Keepsake tucked away' : ready ? 'Bring your keepsake home · +10 bond' : 'A few more moments to share'}</button>
      </>}
      <p class="companion-rule">One adventure per account each UTC day. New outings open at {new Date(adventure.resetsAt).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', timeZoneName: 'short' })}. No missed-day penalty.</p>
      <div class="companion-daily-credit"><strong>{pet.name}’s care today</strong><span>{companion.dailyCare.affection ? '✓' : '○'} affection · {companion.dailyCare.feed ? '✓' : '○'} meal · {companion.dailyCare.play ? '✓' : '○'} play</span><small>Each activity earns bond once per pet per UTC day. Favorites change reactions, never reward amounts.</small></div>
    </article>
    <article class="companion-journal"><p class="home-kicker">THE FIELD JOURNAL</p><h2>Our little history.</h2><p>Small moments, kept safe.</p><ol>{companion.journal.map((entry) => <li key={entry.id}><span class="companion-memory-mark" aria-hidden="true">{entry.kind === 'keepsake' ? '✿' : entry.kind === 'game' ? '⌁' : entry.kind === 'discovery' ? '✦' : '♡'}</span><div><time dateTime={entry.createdAt}>{new Date(entry.createdAt).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}</time><h3>{entry.title}</h3><p>{entry.detail}</p>{entry.xp > 0 && <small>+{entry.xp} bond</small>}</div></li>)}</ol><p class="companion-rule">Your 12 most recent memories. Earlier moments remain recorded.</p></article>
  </section>;
}
