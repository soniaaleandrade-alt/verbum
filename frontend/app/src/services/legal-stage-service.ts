import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type { LegalAssistantResponse, LegalMutationResponse, LegalStage, LegalState } from '../types/legal-stage';

export const getLegalStage = (bookId: string) => apiGet<LegalStage>(`/books/${bookId}/legal-stage`);
export const saveLegalStage = (bookId: string, state: LegalState, flags: Record<string, boolean>, finalConfirmation: boolean) => apiPatch<LegalMutationResponse>(`/books/${bookId}/legal-stage`, { state, flags, final_confirmation: finalConfirmation });
export const createLegalDocument = (bookId: string, payload: Record<string, unknown>) => apiPost<LegalMutationResponse>(`/books/${bookId}/legal-stage/documents`, payload);
export const updateLegalDocument = (bookId: string, documentId: string, payload: Record<string, unknown>) => apiPatch<LegalMutationResponse>(`/books/${bookId}/legal-stage/documents/${documentId}`, payload);
export const deleteLegalDocument = (bookId: string, documentId: string) => apiDelete<LegalMutationResponse>(`/books/${bookId}/legal-stage/documents/${documentId}`);
export const createThirdPartyItem = (bookId: string, payload: Record<string, unknown>) => apiPost<LegalMutationResponse>(`/books/${bookId}/legal-stage/third-party`, payload);
export const updateThirdPartyItem = (bookId: string, itemId: string, payload: Record<string, unknown>) => apiPatch<LegalMutationResponse>(`/books/${bookId}/legal-stage/third-party/${itemId}`, payload);
export const deleteThirdPartyItem = (bookId: string, itemId: string) => apiDelete<LegalMutationResponse>(`/books/${bookId}/legal-stage/third-party/${itemId}`);
export const createLegalIssue = (bookId: string, payload: Record<string, unknown>) => apiPost<LegalMutationResponse>(`/books/${bookId}/legal-stage/issues`, payload);
export const updateLegalIssue = (bookId: string, issueId: string, payload: Record<string, unknown>) => apiPatch<LegalMutationResponse>(`/books/${bookId}/legal-stage/issues/${issueId}`, payload);
export const deleteLegalIssue = (bookId: string, issueId: string) => apiDelete<LegalMutationResponse>(`/books/${bookId}/legal-stage/issues/${issueId}`);
export const registerLegalProof = (bookId: string, fileUrl: string, notes = '') => apiPost<LegalMutationResponse>(`/books/${bookId}/legal-stage/proofs`, { file_url: fileUrl, notes });
export const assistLegalStage = (bookId: string, action: string) => apiPost<LegalAssistantResponse>(`/books/${bookId}/legal-stage/assist`, { action });
export const completeLegalStage = (bookId: string) => apiPost<LegalMutationResponse>(`/books/${bookId}/legal-stage/complete`);
