import type { ApiResponse } from '../types/verbum';

const fallbackRoot = '/wp-json/verbum/v1';

type RequestOptions = {
  method?: 'GET' | 'POST' | 'PATCH';
  body?: unknown;
};

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const config = window.VerbumStudioConfig;
  const response = await fetch(`${config?.apiRoot ?? fallbackRoot}${path}`, {
    method: options.method ?? 'GET',
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': config?.nonce ?? '',
      ...(options.body === undefined ? {} : { 'Content-Type': 'application/json' }),
    },
    ...(options.body === undefined ? {} : { body: JSON.stringify(options.body) }),
  });

  let payload: ApiResponse<T>;
  try {
    payload = (await response.json()) as ApiResponse<T>;
  } catch {
    throw new Error('A API retornou uma resposta inválida.');
  }

  if (!response.ok || !payload.success) {
    const message = payload && !payload.success ? payload.error.message : 'Não foi possível comunicar com a API.';
    throw new Error(message || 'Não foi possível comunicar com a API.');
  }

  return payload.data;
}

export const apiGet = <T>(path: string) => apiRequest<T>(path);
export const apiPost = <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: 'POST', body });
export const apiPatch = <T>(path: string, body?: unknown) => apiRequest<T>(path, { method: 'PATCH', body });
