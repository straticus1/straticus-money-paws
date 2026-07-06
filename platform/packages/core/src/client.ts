import { ApiError } from './error.js';
import type {
  AuthResponse,
  Balance,
  InventoryItem,
  Pet,
  StoreItem,
  User,
} from './types.js';

export interface PawsClientOptions {
  baseUrl: string;
  getToken: () => string | null;
}

export class PawsClient {
  private readonly baseUrl: string;
  private readonly getToken: () => string | null;

  constructor(opts: PawsClientOptions) {
    this.baseUrl = opts.baseUrl;
    this.getToken = opts.getToken;
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

    const res = await fetch(`${this.baseUrl}${path}`, {
      method,
      headers,
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

    return res.json() as Promise<T>;
  }

  async register(email: string, username: string, password: string): Promise<AuthResponse> {
    return this.request<AuthResponse>('POST', '/api/v1/register', {
      email,
      username,
      password,
    });
  }

  async login(email: string, password: string, totp?: string): Promise<AuthResponse> {
    const body: { email: string; password: string; totp?: string } = { email, password };
    if (totp !== undefined) body.totp = totp;
    return this.request<AuthResponse>('POST', '/api/v1/login', body);
  }

  async logout(): Promise<void> {
    return this.request<void>('POST', '/api/v1/logout');
  }

  async me(): Promise<User> {
    return this.request<User>('GET', '/api/v1/me');
  }

  async balances(): Promise<Balance[]> {
    return this.request<Balance[]>('GET', '/api/v1/balances');
  }

  async inventory(): Promise<InventoryItem[]> {
    return this.request<InventoryItem[]>('GET', '/api/v1/inventory');
  }

  async pets(): Promise<Pet[]> {
    return this.request<Pet[]>('GET', '/api/v1/pets');
  }

  async createPet(name: string, species: string): Promise<Pet> {
    return this.request<Pet>('POST', '/api/v1/pets', { name, species });
  }

  async pet(id: string): Promise<Pet> {
    return this.request<Pet>('GET', `/api/v1/pets/${id}`);
  }

  async feedPet(petId: string, itemId: string): Promise<Pet> {
    return this.request<Pet>('POST', `/api/v1/pets/${petId}/feed`, { itemId });
  }

  async storeItems(): Promise<StoreItem[]> {
    return this.request<StoreItem[]>('GET', '/api/v1/store/items');
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
