import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type { EditorialAdjustmentInput, EditorialAssistantResponse, EditorialDeskStage, EditorialFields, EditorialFlags, EditorialMutationResponse } from '../types/editorial-desk';

export const getEditorialDesk = (bookId: string) => apiGet<EditorialDeskStage>(`/books/${bookId}/editorial-desk`);
export const saveEditorialDesk = (bookId: string, fields: EditorialFields, flags: EditorialFlags, assessments: EditorialDeskStage['assessments'], finalConfirmation: boolean) => apiPatch<EditorialMutationResponse>(`/books/${bookId}/editorial-desk`, { fields, flags, assessments, final_confirmation: finalConfirmation });
export const createEditorialAdjustment = (bookId: string, input: EditorialAdjustmentInput) => apiPost<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/adjustments`, input);
export const updateEditorialAdjustment = (bookId: string, adjustmentId: string, input: EditorialAdjustmentInput) => apiPatch<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/adjustments/${adjustmentId}`, input);
export const deleteEditorialAdjustment = (bookId: string, adjustmentId: string) => apiDelete<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/adjustments/${adjustmentId}`);
export const assistEditorialDesk = (bookId: string, action: string) => apiPost<EditorialAssistantResponse>(`/books/${bookId}/editorial-desk/assist`, { action });
export const completeEditorialDesk = (bookId: string) => apiPost<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/complete`);
