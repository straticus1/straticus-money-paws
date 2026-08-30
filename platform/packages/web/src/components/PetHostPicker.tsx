import type { Pet } from '@paws/core';
import { petEmoji } from '../lib/pets';

export function PetHostPicker({ pets, selectedId, onSelect }: { pets: Pet[]; selectedId: string; onSelect: (id: string) => void }) {
  if (pets.length === 0) return <p class="host-picker__empty">No living pets yet. <a href="#/">Adopt a pet first.</a></p>;
  return <div class="host-picker" role="radiogroup" aria-label="Choose a pet">{pets.map((pet) => <label class={`host-picker__pet${selectedId === pet.id ? ' is-selected' : ''}`} key={pet.id}><input type="radio" name="game-host" value={pet.id} checked={selectedId === pet.id} onChange={() => onSelect(pet.id)} /><span aria-hidden="true">{petEmoji(pet.species)}</span><span><strong>{pet.name}</strong><small>{pet.species}</small></span></label>)}</div>;
}
