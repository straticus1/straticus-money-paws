export interface User {
  id: string;
  email: string;
  username: string;
  createdAt: string;
}

export interface Pet {
  id: string;
  name: string;
  species: string;
  hunger: number;
  happiness: number;
  health: number;
  alive: boolean;
}

export interface PetEffect {
  hungerRestore: number;
  happinessBoost: number;
  durationHours: number;
  emoji: string;
  ageRestricted: boolean;
}

export interface StoreItem {
  id: string;
  name: string;
  description: string;
  category: string;
  priceMinor: string;
  currency: 'USD' | 'PAWS';
  effect: PetEffect;
}

export interface Balance {
  currency: 'USD' | 'PAWS';
  amountMinor: string;
}

export interface InventoryItem {
  id: string;
  itemId: string;
  name: string;
  quantity: number;
  emoji?: string;
}

export interface AuthResponse {
  token: string;
  user: User;
}
