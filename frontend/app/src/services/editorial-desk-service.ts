import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type { EditorialAdjustmentInput, EditorialAssistantResponse, EditorialDeskStage, EditorialFields, EditorialFlags, EditorialMutationResponse } from '../types/editorial-desk';

type RawStage = EditorialDeskStage & { fields: EditorialFields & Record<string, unknown> };

function normalizeStage(stage: RawStage): EditorialDeskStage {
  const raw = stage.fields as EditorialFields & Record<string, unknown>;
  return {
    ...stage,
    fields: {
      ...raw,
      backCover: (raw.backCover || raw.backcover) as EditorialFields['backCover'],
      authorProfile: (raw.authorProfile || raw.authorprofile) as EditorialFields['authorProfile'],
      elementOrder: (raw.elementOrder || raw.elementorder || []) as string[],
      layoutBrief: (raw.layoutBrief || raw.layoutbrief) as EditorialFields['layoutBrief'],
      coverBrief: (raw.coverBrief || raw.coverbrief) as EditorialFields['coverBrief'],
    },
  };
}

function normalizeMutation(response: EditorialMutationResponse): EditorialMutationResponse {
  return { ...response, editorialDesk: normalizeStage(response.editorialDesk as RawStage) };
}

export const getEditorialDesk = (bookId: string) => apiGet<RawStage>(`/books/${bookId}/editorial-desk`).then(normalizeStage);
export const saveEditorialDesk = (bookId: string, fields: EditorialFields, flags: EditorialFlags, assessments: EditorialDeskStage['assessments'], finalConfirmation: boolean) => apiPatch<EditorialMutationResponse>(`/books/${bookId}/editorial-desk`, { fields, flags, assessments, final_confirmation: finalConfirmation }).then(normalizeMutation);
export const createEditorialAdjustment = (bookId: string, input: EditorialAdjustmentInput) => apiPost<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/adjustments`, input).then(normalizeMutation);
export const updateEditorialAdjustment = (bookId: string, adjustmentId: string, input: EditorialAdjustmentInput) => apiPatch<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/adjustments/${adjustmentId}`, input).then(normalizeMutation);
export const deleteEditorialAdjustment = (bookId: string, adjustmentId: string) => apiDelete<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/adjustments/${adjustmentId}`).then(normalizeMutation);
export const assistEditorialDesk = (bookId: string, action: string) => apiPost<EditorialAssistantResponse>(`/books/${bookId}/editorial-desk/assist`, { action });
export const completeEditorialDesk = (bookId: string) => apiPost<EditorialMutationResponse>(`/books/${bookId}/editorial-desk/complete`).then(normalizeMutation);
