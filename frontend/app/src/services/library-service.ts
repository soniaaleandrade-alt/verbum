import { apiGet, apiPatch, apiPost } from './api-client';
import type { CreateBookInput, CreateProjectInput, LibraryData, VerbumBook, VerbumProject } from '../types/verbum';

export const getLibrary = () => apiGet<LibraryData>('/library');
export const createProject = (input: CreateProjectInput) => apiPost<VerbumProject>('/projects', input);
export const updateProject = (id: string, input: CreateProjectInput) => apiPatch<VerbumProject>(`/projects/${id}`, input);
export const archiveProject = (id: string) => apiPost<VerbumProject>(`/projects/${id}/archive`);
export const createBook = (input: CreateBookInput) => apiPost<VerbumBook>('/books', input);
export const updateBook = (id: string, input: Partial<CreateBookInput>) => apiPatch<VerbumBook>(`/books/${id}`, input);
export const archiveBook = (id: string) => apiPost<VerbumBook>(`/books/${id}/archive`);
