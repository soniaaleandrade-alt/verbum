import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type { PublicationAssistantResponse, PublicationMutationResponse, PublicationStage, PublicationState } from '../types/publication-stage';

export const getPublicationStage = (bookId: string) => apiGet<PublicationStage>(`/books/${bookId}/publication-stage`);
export const savePublicationStage = (bookId: string, state: PublicationState, flags: Record<string, boolean>, finalConfirmation: boolean) => apiPatch<PublicationMutationResponse>(`/books/${bookId}/publication-stage`, { state, flags, final_confirmation: finalConfirmation });
export const createPublicationChannel = (bookId: string, payload: Record<string, unknown>) => apiPost<PublicationMutationResponse>(`/books/${bookId}/publication-stage/channels`, payload);
export const updatePublicationChannel = (bookId: string, channelId: string, payload: Record<string, unknown>) => apiPatch<PublicationMutationResponse>(`/books/${bookId}/publication-stage/channels/${channelId}`, payload);
export const deletePublicationChannel = (bookId: string, channelId: string) => apiDelete<PublicationMutationResponse>(`/books/${bookId}/publication-stage/channels/${channelId}`);
export const createPublicationTask = (bookId: string, payload: Record<string, unknown>) => apiPost<PublicationMutationResponse>(`/books/${bookId}/publication-stage/tasks`, payload);
export const updatePublicationTask = (bookId: string, taskId: string, payload: Record<string, unknown>) => apiPatch<PublicationMutationResponse>(`/books/${bookId}/publication-stage/tasks/${taskId}`, payload);
export const deletePublicationTask = (bookId: string, taskId: string) => apiDelete<PublicationMutationResponse>(`/books/${bookId}/publication-stage/tasks/${taskId}`);
export const registerPublicationUpdate = (bookId: string, payload: Record<string, unknown>) => apiPost<PublicationMutationResponse>(`/books/${bookId}/publication-stage/updates`, payload);
export const assistPublicationStage = (bookId: string, action: string) => apiPost<PublicationAssistantResponse>(`/books/${bookId}/publication-stage/assist`, { action });
export const completePublicationStage = (bookId: string) => apiPost<PublicationMutationResponse>(`/books/${bookId}/publication-stage/complete`);
