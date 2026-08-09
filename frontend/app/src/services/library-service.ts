import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type {
  CreateBookInput,
  CreateProjectInput,
  IdentificationInput,
  LibraryData,
  VerbumBook,
  VerbumProject,
  WorkWorkspaceData,
} from '../types/verbum';

export const getLibrary = () => apiGet<LibraryData>('/library');
export const getWorkWorkspace = (id: string) => apiGet<WorkWorkspaceData>(`/books/${id}/workspace`);
export const saveIdentification = (id: string, input: IdentificationInput) => apiPatch<WorkWorkspaceData>(`/books/${id}/identification`, input);
export const completeIdentification = (id: string) => apiPost<WorkWorkspaceData>(`/books/${id}/identification/complete`);
export const uploadBookCover = (id: string, file: File) => {
  const body = new FormData();
  body.append('cover', file);
  return apiPost<WorkWorkspaceData>(`/books/${id}/cover`, body);
};
export const removeBookCover = (id: string) => apiDelete<WorkWorkspaceData>(`/books/${id}/cover`);
export const createProject = (input: CreateProjectInput) => apiPost<VerbumProject>('/projects', input);
export const updateProject = (id: string, input: CreateProjectInput) => apiPatch<VerbumProject>(`/projects/${id}`, input);
export const archiveProject = (id: string) => apiPost<VerbumProject>(`/projects/${id}/archive`);
export const createBook = (input: CreateBookInput) => apiPost<VerbumBook>('/books', input);
export const updateBook = (id: string, input: Partial<CreateBookInput>) => apiPatch<VerbumBook>(`/books/${id}`, input);
export const archiveBook = (id: string) => apiPost<VerbumBook>(`/books/${id}/archive`);
