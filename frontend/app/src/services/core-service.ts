import { apiGet } from './api-client'; import type { CurrentUser, Health } from '../types/verbum';
export const getHealth = () => apiGet<Health>('/health'); export const getCurrentUser = () => apiGet<CurrentUser>('/me');
