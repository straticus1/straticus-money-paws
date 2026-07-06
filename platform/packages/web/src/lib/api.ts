import { ApiError, PawsClient } from '@paws/core';

const TOKEN_KEY = 'paws_token';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string): void {
  localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken(): void {
  localStorage.removeItem(TOKEN_KEY);
}

export function navigate(path: string): void {
  window.location.hash = `#${path}`;
}

export const client = new PawsClient({
  baseUrl: '',
  getToken,
});

/** Wraps an API call; on 401 clears the token and redirects to login. */
export async function call<T>(fn: () => Promise<T>): Promise<T> {
  try {
    return await fn();
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) {
      clearToken();
      navigate('/login');
    }
    throw e;
  }
}
