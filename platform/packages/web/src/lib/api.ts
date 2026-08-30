import { ApiError, PawsClient } from '@paws/core';

export function navigate(path: string): void {
  window.location.hash = `#${path}`;
}

export const client = new PawsClient({
  baseUrl: '',
  useCookies: true,
});

/** Wraps an API call; on 401 redirects to login. The session is HttpOnly. */
export async function call<T>(fn: () => Promise<T>): Promise<T> {
  try {
    return await fn();
  } catch (e) {
    if (e instanceof ApiError && e.status === 401 && e.code === 'unauthorized') {
      navigate('/login');
    }
    throw e;
  }
}
