import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from './error.js';
import { formatMinor } from './format.js';
import { PawsClient } from './client.js';

// ---------------------------------------------------------------------------
// formatMinor
// ---------------------------------------------------------------------------

describe('formatMinor – USD', () => {
  it('formats "0"', () => {
    expect(formatMinor('0', 'USD')).toBe('$0.00');
  });

  it('formats "5"', () => {
    expect(formatMinor('5', 'USD')).toBe('$0.05');
  });

  it('formats "99"', () => {
    expect(formatMinor('99', 'USD')).toBe('$0.99');
  });

  it('formats "100"', () => {
    expect(formatMinor('100', 'USD')).toBe('$1.00');
  });

  it('formats "1234"', () => {
    expect(formatMinor('1234', 'USD')).toBe('$12.34');
  });

  it('formats with thousands separator', () => {
    expect(formatMinor('100000', 'USD')).toBe('$1,000.00');
  });

  it('handles value beyond Number.MAX_SAFE_INTEGER', () => {
    // 999999999999999999 cents = $9,999,999,999,999,999.99
    expect(formatMinor('999999999999999999', 'USD')).toBe('$9,999,999,999,999,999.99');
  });
});

describe('formatMinor – PAWS', () => {
  it('formats "0"', () => {
    expect(formatMinor('0', 'PAWS')).toBe('0 PAWS');
  });

  it('formats "5"', () => {
    expect(formatMinor('5', 'PAWS')).toBe('5 PAWS');
  });

  it('formats "1000"', () => {
    expect(formatMinor('1000', 'PAWS')).toBe('1,000 PAWS');
  });

  it('handles huge value beyond Number.MAX_SAFE_INTEGER', () => {
    expect(formatMinor('1000000000000000000', 'PAWS')).toBe(
      '1,000,000,000,000,000,000 PAWS',
    );
  });
});

// ---------------------------------------------------------------------------
// ApiError mapping via stubbed fetch
// ---------------------------------------------------------------------------

describe('PawsClient – ApiError mapping', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
  });

  function mockFetch(
    status: number,
    jsonFn: () => Promise<unknown> | never,
  ): void {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: false,
      status,
      json: jsonFn,
    } as unknown as Response);
  }

  it('extracts error code from JSON body', async () => {
    mockFetch(401, async () => ({ error: 'unauthorized' }));
    const client = new PawsClient({ baseUrl: '', getToken: () => null });

    let caught: unknown;
    try {
      await client.me();
    } catch (e) {
      caught = e;
    }

    expect(caught).toBeInstanceOf(ApiError);
    const err = caught as ApiError;
    expect(err.status).toBe(401);
    expect(err.code).toBe('unauthorized');
  });

  it('uses "http_error" when body has no error field', async () => {
    mockFetch(500, async () => ({ message: 'something broke' }));
    const client = new PawsClient({ baseUrl: '', getToken: () => null });

    let caught: unknown;
    try {
      await client.me();
    } catch (e) {
      caught = e;
    }

    expect(caught).toBeInstanceOf(ApiError);
    const err = caught as ApiError;
    expect(err.status).toBe(500);
    expect(err.code).toBe('http_error');
  });

  it('uses "http_error" when body is not valid JSON', async () => {
    mockFetch(503, () => {
      throw new SyntaxError('not json');
    });
    const client = new PawsClient({ baseUrl: '', getToken: () => null });

    let caught: unknown;
    try {
      await client.me();
    } catch (e) {
      caught = e;
    }

    expect(caught).toBeInstanceOf(ApiError);
    const err = caught as ApiError;
    expect(err.status).toBe(503);
    expect(err.code).toBe('http_error');
  });

  it('sends Authorization header when token is present', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ id: '1', email: 'a@b.com', username: 'a', createdAt: '' }),
    } as unknown as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => 'tok123' });
    await client.me();

    const [, init] = vi.mocked(fetch).mock.calls[0]!;
    const headers = (init as RequestInit).headers as Record<string, string>;
    expect(headers['Authorization']).toBe('Bearer tok123');
  });

  it('omits Authorization header when token is null', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ id: '1', email: 'a@b.com', username: 'a', createdAt: '' }),
    } as unknown as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => null });
    await client.me();

    const [, init] = vi.mocked(fetch).mock.calls[0]!;
    const headers = (init as RequestInit).headers as Record<string, string>;
    expect(headers['Authorization']).toBeUndefined();
  });

  it('uses the API auth route prefix for register, login, and logout', async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ token: 'register-token', user: {} }),
      } as unknown as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ token: 'login-token', user: {} }),
      } as unknown as Response)
      .mockResolvedValueOnce({
        ok: true,
        status: 204,
      } as unknown as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => 'token' });
    await client.register('admin@paws.local', 'admin', 'password');
    await client.login('admin@paws.local', 'password');
    await client.logout();

    expect(vi.mocked(fetch).mock.calls.map(([url]) => url)).toEqual([
      '/api/v1/auth/register',
      '/api/v1/auth/login',
      '/api/v1/auth/logout',
    ]);
  });

  it('unwraps collection responses from the API contract', async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ pets: [{ id: 'pet-1' }] }),
      } as unknown as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ balances: [{ currency: 'PAWS', amountMinor: '5' }] }),
      } as unknown as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ items: [{ itemId: 'item-1' }] }),
      } as unknown as Response)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ items: [{ id: 'store-1' }] }),
      } as unknown as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => 'token' });
    expect(await client.pets()).toEqual([{ id: 'pet-1' }]);
    expect(await client.balances()).toEqual([{ currency: 'PAWS', amountMinor: '5' }]);
    expect(await client.inventory()).toEqual([{ itemId: 'item-1' }]);
    expect(await client.storeItems()).toEqual([{ id: 'store-1' }]);
  });

  it('sends Paw Match moves only to the server-authoritative game endpoint', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ game: { id: 'game-1' }, outcome: 'first_pick' }),
    } as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => 'token' });
    await client.flipPawMatch('game-1', 4, '123e4567-e89b-12d3-a456-426614174000');

    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    expect(url).toBe('/api/v1/games/paw-match/game-1/flip');
    expect(JSON.parse((init as RequestInit).body as string)).toEqual({
      position: 4,
      actionId: '123e4567-e89b-12d3-a456-426614174000',
    });
  });

  it('sends Trail Tails actions without client-owned game or reward state', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ game: { id: 'trail-1' }, outcome: 'moved' }),
    } as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => 'token' });
    await client.actTrailTails(
      'trail-1',
      { action: 'move', direction: 'north' },
      '123e4567-e89b-12d3-a456-426614174000',
    );

    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    expect(url).toBe('/api/v1/games/trail-tails/trail-1/actions');
    expect(JSON.parse((init as RequestInit).body as string)).toEqual({
      action: 'move',
      direction: 'north',
      actionId: '123e4567-e89b-12d3-a456-426614174000',
    });
  });

  it('sends Midnight Pantry placements without correctness or economic fields', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({ game: { id: 'pantry-1' }, outcome: 'placed' }),
    } as Response);

    const client = new PawsClient({ baseUrl: '', getToken: () => 'token' });
    await client.actMidnightPantry(
      'pantry-1',
      { action: 'place', guestId: 'moth', slot: 1, ingredientId: 'moonberry' },
      '123e4567-e89b-12d3-a456-426614174000',
    );

    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    expect(url).toBe('/api/v1/games/midnight-pantry/pantry-1/actions');
    expect(JSON.parse((init as RequestInit).body as string)).toEqual({
      action: 'place',
      guestId: 'moth',
      slot: 1,
      ingredientId: 'moonberry',
      actionId: '123e4567-e89b-12d3-a456-426614174000',
    });
  });

  it.each([
    ['lantern-lines', 'actLanternLines', { action: 'rotate', position: 2, direction: 'clockwise' }],
    ['pocket-post', 'actPocketPost', { action: 'move', direction: 'north' }],
    ['parade-practice', 'actParadePractice', { action: 'run', commands: ['forward', 'turn-left'] }],
  ] as const)('sends strict %s actions without client-owned results', async (slug, method, action) => {
    vi.mocked(fetch).mockResolvedValueOnce({ ok: true, json: async () => ({ game: { id: 'new-1' } }) } as Response);
    const client = new PawsClient({ baseUrl: '', getToken: () => 'token' });
    await (client[method] as (id: string, action: never, actionId: string) => Promise<unknown>)('new-1', action as never, '123e4567-e89b-12d3-a456-426614174000');
    const [url, init] = vi.mocked(fetch).mock.calls[0]!;
    expect(url).toBe(`/api/v1/games/${slug}/new-1/actions`);
    expect(JSON.parse((init as RequestInit).body as string)).toEqual({ ...action, actionId: '123e4567-e89b-12d3-a456-426614174000' });
  });
});
