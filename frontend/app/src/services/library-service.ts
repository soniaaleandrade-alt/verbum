import { apiDelete, apiGet, apiPatch, apiPost } from './api-client';
import type {
  GeneralReviewAssistantResponse,
  GeneralReviewInput,
  GeneralReviewIssueInput,
  GeneralReviewMutationResponse,
  GeneralReviewProgress,
  GeneralReviewReading,
} from '../types/general-review';
import type {
  ChapterPreparationInput,
  ChapterPreparationMutationResponse,
  ChapterPreparationProgress,
  ChapterResearchMutationResponse,
  ChapterResearchProgress,
  ChapterResearchSourceInput,
  ChapterResearchStateInput,
  ChapterRevisionAssistantResponse,
  ChapterRevisionInput,
  ChapterRevisionIssueInput,
  ChapterRevisionMutationResponse,
  ChapterRevisionProgress,
  ChapterWritingAssistantResponse,
  ChapterWritingInput,
  ChapterWritingMutationResponse,
  ChapterWritingProgress,
  CreateBookInput,
  CreateProjectInput,
  DevelopmentChapter,
  IdentificationInput,
  LibraryData,
  VerbumBook,
  VerbumProject,
  WorkDevelopmentMutationResponse,
  WorkDevelopmentProgress,
  WorkPlanningInput,
  WorkPlanningMutationResponse,
  WorkPlanningProgress,
  WorkProjectInput,
  WorkProjectMutationResponse,
  WorkProjectProgress,
  WorkWorkspaceData,
} from '../types/verbum';

export const getLibrary = () => apiGet<LibraryData>('/library');
export const getWorkWorkspace = (id: string) => apiGet<WorkWorkspaceData>(`/books/${id}/workspace`);
export const saveIdentification = (id: string, input: IdentificationInput) => apiPatch<WorkWorkspaceData>(`/books/${id}/identification`, input);
export const completeIdentification = (id: string) => apiPost<WorkWorkspaceData>(`/books/${id}/identification/complete`);
export const getWorkProject = (id: string) => apiGet<WorkProjectProgress>(`/books/${id}/project-stage`);
export const saveWorkProject = (id: string, input: WorkProjectInput) => apiPatch<WorkProjectMutationResponse>(`/books/${id}/project-stage`, input);
export const completeWorkProject = (id: string) => apiPost<WorkProjectMutationResponse>(`/books/${id}/project-stage/complete`);
export const getWorkPlanning = (id: string) => apiGet<WorkPlanningProgress>(`/books/${id}/planning-stage`);
export const saveWorkPlanning = (id: string, input: WorkPlanningInput) => apiPatch<WorkPlanningMutationResponse>(`/books/${id}/planning-stage`, input);
export const generatePlanningChapters = (id: string) => apiPost<WorkPlanningMutationResponse>(`/books/${id}/planning-stage/generate-chapters`);
export const completeWorkPlanning = (id: string) => apiPost<WorkPlanningMutationResponse>(`/books/${id}/planning-stage/complete`);
export const getWorkDevelopment = (id: string) => apiGet<WorkDevelopmentProgress>(`/books/${id}/development-stage`);
export const getDevelopmentChapter = (id: string, chapterId: string) => apiGet<DevelopmentChapter>(`/books/${id}/chapters/${chapterId}`);
export const completeWorkDevelopment = (id: string) => apiPost<WorkDevelopmentMutationResponse>(`/books/${id}/development-stage/complete`);
export const getChapterPreparation = (id: string, chapterId: string) => apiGet<ChapterPreparationProgress>(`/books/${id}/chapters/${chapterId}/preparation`);
export const saveChapterPreparation = (id: string, chapterId: string, input: ChapterPreparationInput) => apiPatch<ChapterPreparationMutationResponse>(`/books/${id}/chapters/${chapterId}/preparation`, input);
export const completeChapterPreparation = (id: string, chapterId: string) => apiPost<ChapterPreparationMutationResponse>(`/books/${id}/chapters/${chapterId}/preparation/complete`);
export const getChapterResearch = (id: string, chapterId: string) => apiGet<ChapterResearchProgress>(`/books/${id}/chapters/${chapterId}/research`);
export const saveChapterResearchState = (id: string, chapterId: string, input: ChapterResearchStateInput) => apiPatch<ChapterResearchMutationResponse>(`/books/${id}/chapters/${chapterId}/research`, input);
export const createChapterResearchSource = (id: string, chapterId: string, input: ChapterResearchSourceInput) => apiPost<ChapterResearchMutationResponse>(`/books/${id}/chapters/${chapterId}/research/sources`, input);
export const updateChapterResearchSource = (id: string, chapterId: string, sourceId: string, input: Partial<ChapterResearchSourceInput>) => apiPatch<ChapterResearchMutationResponse>(`/books/${id}/chapters/${chapterId}/research/sources/${sourceId}`, input);
export const deleteChapterResearchSource = (id: string, chapterId: string, sourceId: string) => apiDelete<ChapterResearchMutationResponse>(`/books/${id}/chapters/${chapterId}/research/sources/${sourceId}`);
export const completeChapterResearch = (id: string, chapterId: string) => apiPost<ChapterResearchMutationResponse>(`/books/${id}/chapters/${chapterId}/research/complete`);
export const getChapterWriting = (id: string, chapterId: string) => apiGet<ChapterWritingProgress>(`/books/${id}/chapters/${chapterId}/writing`);
export const saveChapterWriting = (id: string, chapterId: string, input: ChapterWritingInput) => apiPatch<ChapterWritingMutationResponse>(`/books/${id}/chapters/${chapterId}/writing`, input);
export const completeChapterWriting = (id: string, chapterId: string) => apiPost<ChapterWritingMutationResponse>(`/books/${id}/chapters/${chapterId}/writing/complete`);
export const assistChapterWriting = (id: string, chapterId: string, action: string, text: string) => apiPost<ChapterWritingAssistantResponse>(`/books/${id}/chapters/${chapterId}/writing/assist`, { action, text });
export const getChapterRevision = (id: string, chapterId: string) => apiGet<ChapterRevisionProgress>(`/books/${id}/chapters/${chapterId}/revision`);
export const saveChapterRevision = (id: string, chapterId: string, input: ChapterRevisionInput) => apiPatch<ChapterRevisionMutationResponse>(`/books/${id}/chapters/${chapterId}/revision`, input);
export const createChapterRevisionIssue = (id: string, chapterId: string, input: ChapterRevisionIssueInput) => apiPost<ChapterRevisionMutationResponse>(`/books/${id}/chapters/${chapterId}/revision/issues`, input);
export const updateChapterRevisionIssue = (id: string, chapterId: string, issueId: string, input: ChapterRevisionIssueInput) => apiPatch<ChapterRevisionMutationResponse>(`/books/${id}/chapters/${chapterId}/revision/issues/${issueId}`, input);
export const deleteChapterRevisionIssue = (id: string, chapterId: string, issueId: string) => apiDelete<ChapterRevisionMutationResponse>(`/books/${id}/chapters/${chapterId}/revision/issues/${issueId}`);
export const completeChapterRevision = (id: string, chapterId: string) => apiPost<ChapterRevisionMutationResponse>(`/books/${id}/chapters/${chapterId}/revision/complete`);
export const assistChapterRevision = (id: string, chapterId: string, action: string, text: string) => apiPost<ChapterRevisionAssistantResponse>(`/books/${id}/chapters/${chapterId}/revision/assist`, { action, text });
export const getGeneralReview = (id: string) => apiGet<GeneralReviewProgress>(`/books/${id}/general-review`);
export const saveGeneralReview = (id: string, input: GeneralReviewInput) => apiPatch<GeneralReviewMutationResponse>(`/books/${id}/general-review`, input);
export const getGeneralReviewReading = (id: string) => apiGet<GeneralReviewReading>(`/books/${id}/general-review/reading`);
export const createGeneralReviewIssue = (id: string, input: GeneralReviewIssueInput) => apiPost<GeneralReviewMutationResponse>(`/books/${id}/general-review/issues`, input);
export const updateGeneralReviewIssue = (id: string, issueId: string, input: GeneralReviewIssueInput) => apiPatch<GeneralReviewMutationResponse>(`/books/${id}/general-review/issues/${issueId}`, input);
export const deleteGeneralReviewIssue = (id: string, issueId: string) => apiDelete<GeneralReviewMutationResponse>(`/books/${id}/general-review/issues/${issueId}`);
export const completeGeneralReview = (id: string) => apiPost<GeneralReviewMutationResponse>(`/books/${id}/general-review/complete`);
export const assistGeneralReview = (id: string, action: string) => apiPost<GeneralReviewAssistantResponse>(`/books/${id}/general-review/assist`, { action });
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
