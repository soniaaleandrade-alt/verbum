import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type { LayoutAssistantResponse, LayoutConfig, LayoutMutationResponse, LayoutPreview, LayoutStage } from '../types/layout-stage';

export const getLayoutStage = (bookId: string) => apiGet<LayoutStage>(`/books/${bookId}/layout-stage`);
export const saveLayoutStage = (bookId: string, config: LayoutConfig, flags: Record<string, boolean>, finalConfirmation: boolean) => apiPatch<LayoutMutationResponse>(`/books/${bookId}/layout-stage`, { config, flags, final_confirmation: finalConfirmation });
export const getLayoutPreview = (bookId: string) => apiGet<LayoutPreview>(`/books/${bookId}/layout-stage/preview`);
export const createLayoutIssue = (bookId: string, category: string, description: string) => apiPost<LayoutMutationResponse>(`/books/${bookId}/layout-stage/issues`, { category, description });
export const updateLayoutIssue = (bookId: string, issueId: string, status: string) => apiPatch<LayoutMutationResponse>(`/books/${bookId}/layout-stage/issues/${issueId}`, { status });
export const deleteLayoutIssue = (bookId: string, issueId: string) => apiDelete<LayoutMutationResponse>(`/books/${bookId}/layout-stage/issues/${issueId}`);
export const generateLayoutProof = (bookId: string, note = '') => apiPost<LayoutMutationResponse>(`/books/${bookId}/layout-stage/proofs`, { note });
export const assistLayoutStage = (bookId: string, action: string) => apiPost<LayoutAssistantResponse>(`/books/${bookId}/layout-stage/assist`, { action });
export const completeLayoutStage = (bookId: string) => apiPost<LayoutMutationResponse>(`/books/${bookId}/layout-stage/complete`);
