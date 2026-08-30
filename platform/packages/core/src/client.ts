import { ApiError } from './error.js';
import type {
  AuthResponse,
  AccountSettings,
  Balance,
  InventoryItem,
  LeaderboardEntry,
  LanternLinesAction,
  LanternLinesResponse,
  MidnightPantryAction,
  MidnightPantryResponse,
  PawMatchResponse,
  ParadePracticeAction,
  ParadePracticeResponse,
  Pet,
  PocketPostAction,
  PocketPostResponse,
  StoreItem,
  TrailTailsAction,
  TrailTailsResponse,
  Trophy,
  User,
} from './types.js';

export interface PawsClientOptions {
  baseUrl: string;
  getToken?: () => string | null;
  useCookies?: boolean;
}

export class PawsClient {
  private readonly baseUrl: string;
  private readonly getToken: () => string | null;
  private readonly useCookies: boolean;

  constructor(opts: PawsClientOptions) {
    this.baseUrl = opts.baseUrl;
    this.getToken = opts.getToken ?? (() => null);
    this.useCookies = opts.useCookies ?? false;
  }

  private async request<T>(
    method: string,
    path: string,
    body?: unknown,
    extraHeaders?: Record<string, string>,
  ): Promise<T> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(extraHeaders ?? {}),
    };

    const token = this.getToken();
    if (token !== null) {
      headers['Authorization'] = `Bearer ${token}`;
    }
    if (this.useCookies && (path === '/api/v1/auth/login' || path === '/api/v1/auth/register')) {
      headers['X-Paws-Session-Mode'] = 'cookie';
    }

    const res = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers,
      credentials: this.useCookies ? 'same-origin' : 'omit',
      ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });

    if (!res.ok) {
      let code = 'http_error';
      try {
        const json = (await res.json()) as Record<string, unknown>;
        if (typeof json['error'] === 'string') {
          code = json['error'];
        }
      } catch {
        // non-JSON body — keep code as http_error
      }
      throw new ApiError(res.status, code, `HTTP ${res.status}: ${code}`);
    }

    if (res.status === 204) {
      return undefined as T;
    }
    return res.json() as Promise<T>;
  }

  async register(email: string, username: string, password: string): Promise<AuthResponse> {
    return this.request<AuthResponse>('POST', '/api/v1/auth/register', {
      email,
      username,
      password,
    });
  }

  async login(email: string, password: string, totp?: string): Promise<AuthResponse> {
    const body: { email: string; password: string; totp?: string } = { email, password };
    if (totp !== undefined) body.totp = totp;
    return this.request<AuthResponse>('POST', '/api/v1/auth/login', body);
  }

  async logout(): Promise<void> {
    return this.request<void>('POST', '/api/v1/auth/logout');
  }

  async me(): Promise<User> {
    const response = await this.request<{ user: User }>('GET', '/api/v1/me');
    return response.user;
  }

  async balances(): Promise<Balance[]> {
    const response = await this.request<{ balances: Balance[] }>('GET', '/api/v1/me/balances');
    return response.balances;
  }

  async inventory(): Promise<InventoryItem[]> {
    const response = await this.request<{ items: InventoryItem[] }>('GET', '/api/v1/me/inventory');
    return response.items;
  }

  async settings(): Promise<AccountSettings> {
    const response = await this.request<{ settings: AccountSettings }>('GET', '/api/v1/me/settings');
    return response.settings;
  }

  async updateSettings(input: Pick<AccountSettings, 'leaderboardOptIn' | 'trophyShowcase'>): Promise<void> {
    await this.request('PUT', '/api/v1/me/settings', input);
  }

  async changePassword(currentPassword: string, newPassword: string): Promise<{ changed: true; token?: string }> {
    return this.request('POST', '/api/v1/me/password', { currentPassword, newPassword });
  }

  async trophies(): Promise<Trophy[]> {
    const response = await this.request<{ trophies: Trophy[] }>('GET', '/api/v1/me/trophies');
    return response.trophies;
  }

  async leaderboard(): Promise<{ entries: LeaderboardEntry[]; scoring: string }> {
    return this.request('GET', '/api/v1/leaderboard');
  }

  async pets(): Promise<Pet[]> {
    const response = await this.request<{ pets: Pet[] }>('GET', '/api/v1/pets');
    return response.pets;
  }

  async createPet(name: string, species: string): Promise<Pet> {
    const response = await this.request<{ pet: Pet }>('POST', '/api/v1/pets', { name, species });
    return response.pet;
  }

  async pet(id: string): Promise<Pet> {
    const response = await this.request<{ pet: Pet }>('GET', `/api/v1/pets/${id}`);
    return response.pet;
  }

  async feedPet(petId: string, itemId: string): Promise<Pet> {
    const response = await this.request<{ pet: Pet }>('POST', `/api/v1/pets/${petId}/feed`, {
      itemId,
    });
    return response.pet;
  }

  async storeItems(): Promise<StoreItem[]> {
    const response = await this.request<{ items: StoreItem[] }>('GET', '/api/v1/store/items');
    return response.items;
  }

  async startPawMatch(): Promise<PawMatchResponse> {
    return this.request<PawMatchResponse>('POST', '/api/v1/games/paw-match');
  }

  async pawMatch(id: string): Promise<PawMatchResponse> {
    return this.request<PawMatchResponse>('GET', `/api/v1/games/paw-match/${id}`);
  }

  async flipPawMatch(
    id: string,
    position: number,
    actionId: string = crypto.randomUUID(),
  ): Promise<PawMatchResponse> {
    return this.request<PawMatchResponse>('POST', `/api/v1/games/paw-match/${id}/flip`, {
      position,
      actionId,
    });
  }

  async startTrailTails(
    petId: string,
    actionId: string = crypto.randomUUID(),
  ): Promise<TrailTailsResponse> {
    return this.request<TrailTailsResponse>('POST', '/api/v1/games/trail-tails', {
      petId,
      actionId,
    });
  }

  async trailTails(id: string): Promise<TrailTailsResponse> {
    return this.request<TrailTailsResponse>('GET', `/api/v1/games/trail-tails/${id}`);
  }

  async actTrailTails(
    id: string,
    action: TrailTailsAction,
    actionId: string = crypto.randomUUID(),
  ): Promise<TrailTailsResponse> {
    return this.request<TrailTailsResponse>(
      'POST',
      `/api/v1/games/trail-tails/${id}/actions`,
      { ...action, actionId },
    );
  }

  async startMidnightPantry(
    petId: string,
    mode: 'daily' | 'practice' = 'daily',
    actionId: string = crypto.randomUUID(),
  ): Promise<MidnightPantryResponse> {
    return this.request<MidnightPantryResponse>('POST', '/api/v1/games/midnight-pantry', {
      petId,
      mode,
      actionId,
    });
  }

  async midnightPantry(id: string): Promise<MidnightPantryResponse> {
    return this.request<MidnightPantryResponse>('GET', `/api/v1/games/midnight-pantry/${id}`);
  }

  async actMidnightPantry(
    id: string,
    action: MidnightPantryAction,
    actionId: string = crypto.randomUUID(),
  ): Promise<MidnightPantryResponse> {
    return this.request<MidnightPantryResponse>(
      'POST',
      `/api/v1/games/midnight-pantry/${id}/actions`,
      { ...action, actionId },
    );
  }

  async startLanternLines(petId: string, mode: 'daily' | 'practice' = 'daily', actionId: string = crypto.randomUUID()): Promise<LanternLinesResponse> {
    return this.request<LanternLinesResponse>('POST', '/api/v1/games/lantern-lines', { petId, mode, actionId });
  }

  async lanternLines(id: string): Promise<LanternLinesResponse> {
    return this.request<LanternLinesResponse>('GET', `/api/v1/games/lantern-lines/${id}`);
  }

  async actLanternLines(id: string, action: LanternLinesAction, actionId: string = crypto.randomUUID()): Promise<LanternLinesResponse> {
    return this.request<LanternLinesResponse>('POST', `/api/v1/games/lantern-lines/${id}/actions`, { ...action, actionId });
  }

  async startPocketPost(petId: string, mode: 'daily' | 'practice' = 'daily', actionId: string = crypto.randomUUID()): Promise<PocketPostResponse> {
    return this.request<PocketPostResponse>('POST', '/api/v1/games/pocket-post', { petId, mode, actionId });
  }

  async pocketPost(id: string): Promise<PocketPostResponse> {
    return this.request<PocketPostResponse>('GET', `/api/v1/games/pocket-post/${id}`);
  }

  async actPocketPost(id: string, action: PocketPostAction, actionId: string = crypto.randomUUID()): Promise<PocketPostResponse> {
    return this.request<PocketPostResponse>('POST', `/api/v1/games/pocket-post/${id}/actions`, { ...action, actionId });
  }

  async startParadePractice(petId: string, mode: 'daily' | 'practice' = 'daily', actionId: string = crypto.randomUUID()): Promise<ParadePracticeResponse> {
    return this.request<ParadePracticeResponse>('POST', '/api/v1/games/parade-practice', { petId, mode, actionId });
  }

  async paradePractice(id: string): Promise<ParadePracticeResponse> {
    return this.request<ParadePracticeResponse>('GET', `/api/v1/games/parade-practice/${id}`);
  }

  async actParadePractice(id: string, action: ParadePracticeAction, actionId: string = crypto.randomUUID()): Promise<ParadePracticeResponse> {
    return this.request<ParadePracticeResponse>('POST', `/api/v1/games/parade-practice/${id}/actions`, { ...action, actionId });
  }

  async purchase(
    itemId: string,
    quantity: number,
    idempotencyKey?: string,
  ): Promise<unknown> {
    const key = idempotencyKey ?? crypto.randomUUID();
    return this.request<unknown>(
      'POST',
      '/api/v1/store/purchase',
      { itemId, quantity },
      { 'Idempotency-Key': key },
    );
  }
}
