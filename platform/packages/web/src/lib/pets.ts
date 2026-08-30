export const PET_SPECIES = [
  'dog', 'cat', 'bird', 'rabbit', 'horse', 'fox', 'turtle', 'hamster',
  'guinea pig', 'ferret', 'hedgehog', 'frog', 'fish',
] as const;

export type PetSpecies = (typeof PET_SPECIES)[number];

export const PET_EMOJI: Record<PetSpecies, string> = {
  dog: '🐕', cat: '🐈', bird: '🐦', rabbit: '🐇', horse: '🐎', fox: '🦊',
  turtle: '🐢', hamster: '🐹', 'guinea pig': '🐹', ferret: '🦦',
  hedgehog: '🦔', frog: '🐸', fish: '🐟',
};

export function petEmoji(species: string): string {
  const normalized = species.toLowerCase();
  const exact = PET_SPECIES.find((candidate) => candidate === normalized);
  if (exact !== undefined) return PET_EMOJI[exact];
  if (normalized.includes('bunny')) return PET_EMOJI.rabbit;
  return '🐾';
}
