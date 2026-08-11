import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type {
  CreateWorkVersionInput,
  WorkVersionCompareResponse,
  WorkVersionDetailResponse,
  WorkVersionMutationResponse,
  WorkVersionsFlags,
  WorkVersionsStage,
} from '../types/work-versions';

export const getWorkVersions = (bookId: string) => apiGet<WorkVersionsStage>(`/books/${bookId}/versions-stage`);
export const saveWorkVersionsState = (bookId: string, flags: WorkVersionsFlags) => apiPatch<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage`, { flags });
export const createWorkVersion = (bookId: string, input: CreateWorkVersionInput) => apiPost<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/versions`, input);
export const getWorkVersion = (bookId: string, versionId: string) => apiGet<WorkVersionDetailResponse>(`/books/${bookId}/versions-stage/versions/${versionId}`);
export const updateWorkVersion = (bookId: string, versionId: string, input: { name?: string; notes?: string; protected?: boolean }) => apiPatch<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/versions/${versionId}`, input);
export const deleteWorkVersion = (bookId: string, versionId: string) => apiDelete<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/versions/${versionId}`);
export const duplicateWorkVersion = (bookId: string, versionId: string, input: { name?: string; notes?: string } = {}) => apiPost<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/versions/${versionId}/duplicate`, input);
export const restoreWorkVersion = (bookId: string, versionId: string) => apiPost<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/versions/${versionId}/restore`);
export const selectAuditWorkVersion = (bookId: string, versionId: string) => apiPost<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/versions/${versionId}/audit-baseline`);
export const compareWorkVersions = (bookId: string, fromId: string, toId: string) => apiPost<WorkVersionCompareResponse>(`/books/${bookId}/versions-stage/compare`, { from_id: fromId, to_id: toId });
export const completeWorkVersions = (bookId: string) => apiPost<WorkVersionMutationResponse>(`/books/${bookId}/versions-stage/complete`);
