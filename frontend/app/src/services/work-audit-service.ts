import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type { AuditAssistantResponse, AuditFindingInput, AuditMutationResponse, AuditFlags, WorkAuditStage } from '../types/work-audit';

export const getWorkAudit = (bookId: string) => apiGet<WorkAuditStage>(`/books/${bookId}/audit-stage`);
export const saveWorkAuditState = (bookId: string, flags: AuditFlags, finalConfirmation: boolean) => apiPatch<AuditMutationResponse>(`/books/${bookId}/audit-stage`, { flags, final_confirmation: finalConfirmation });
export const createAuditFinding = (bookId: string, input: AuditFindingInput) => apiPost<AuditMutationResponse>(`/books/${bookId}/audit-stage/findings`, input);
export const updateAuditFinding = (bookId: string, findingId: string, input: AuditFindingInput) => apiPatch<AuditMutationResponse>(`/books/${bookId}/audit-stage/findings/${findingId}`, input);
export const deleteAuditFinding = (bookId: string, findingId: string) => apiDelete<AuditMutationResponse>(`/books/${bookId}/audit-stage/findings/${findingId}`);
export const generateAuditReport = (bookId: string) => apiPost<AuditMutationResponse>(`/books/${bookId}/audit-stage/report`);
export const assistWorkAudit = (bookId: string, action: string) => apiPost<AuditAssistantResponse>(`/books/${bookId}/audit-stage/assist`, { action });
export const completeWorkAudit = (bookId: string) => apiPost<AuditMutationResponse>(`/books/${bookId}/audit-stage/complete`);
