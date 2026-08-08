import type { ApiResponse } from '../types/verbum';
const fallbackRoot = '/wp-json/verbum/v1';
export async function apiGet<T>(path: string): Promise<T> { const config = window.VerbumStudioConfig; const response = await fetch(`${config?.apiRoot ?? fallbackRoot}${path}`, { credentials:'same-origin', headers:{ 'X-WP-Nonce': config?.nonce ?? '' } }); const payload = await response.json() as ApiResponse<T>; if (!payload.success) { throw new Error(payload.error.message || 'Não foi possível comunicar com a API.'); } return payload.data; }
