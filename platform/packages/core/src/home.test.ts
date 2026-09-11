import { afterEach, describe, expect, it, vi } from 'vitest';
import { PawsClient } from './client.js';
import { ApiError } from './error.js';
import type { HomeCommand } from './types.js';

afterEach(() => vi.unstubAllGlobals());

describe('home action delivery', () => {
  it('retries uncertain delivery with the exact action ID and expected version', async () => {
    const fetchMock = vi.fn().mockRejectedValueOnce(new TypeError('connection lost')).mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({ home: { version: 4 } }) });
    vi.stubGlobal('fetch', fetchMock);
    const client = new PawsClient({ baseUrl: '', useCookies: true });
    const command: HomeCommand = { action: 'pet', petId: crypto.randomUUID(), actionId: crypto.randomUUID(), expectedVersion: 3 };
    await expect(client.actHome(command)).rejects.toThrow('connection lost');
    await expect(client.actHome(command)).resolves.toEqual({ home: { version: 4 } });
    expect(fetchMock.mock.calls[0]).toEqual(fetchMock.mock.calls[1]);
    expect(fetchMock.mock.calls[1]![0]).toBe('/api/v1/home/actions');
    expect(JSON.parse(fetchMock.mock.calls[1]![1].body)).toEqual(command);
    expect(fetchMock.mock.calls[1]![1].credentials).toBe('same-origin');
  });

  it('exposes version conflicts so the UI can refresh instead of silently resubmitting', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 409, json: async () => ({ error: 'stale_home' }) });
    vi.stubGlobal('fetch', fetchMock);
    const client = new PawsClient({ baseUrl: '' });
    await expect(client.actHome({ action: 'remove', position: 0, actionId: crypto.randomUUID(), expectedVersion: 0 })).rejects.toEqual(new ApiError(409, 'stale_home', 'HTTP 409: stale_home'));
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
