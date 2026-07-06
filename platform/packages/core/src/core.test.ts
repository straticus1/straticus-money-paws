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
});
