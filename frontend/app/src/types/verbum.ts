export type ApiSuccess<T> = { success: true; data: T };
export type ApiError = { success: false; error: { code: string; message: string } };
export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export type Health = { status: string; version: string };
export type CurrentUser = { id: string; name: string; email: string };

export type ProjectStatus = 'active' | 'archived';
export type BookStatus = 'active' | 'archived';

export type VerbumProject = {
  id: string;
  name: string;
  description: string;
  status: ProjectStatus;
  createdAt: string;
  updatedAt: string;
};

export type VerbumBook = {
  id: string;
  projectId: string;
  title: string;
  subtitle?: string;
  series?: string;
  category?: string;
  genre?: string;
  audience?: string;
  ageRange?: string;
  language?: string;
  country?: string;
  authorName?: string;
  coauthorName?: string;
  mainObjective?: string;
  readerProblem?: string;
  readerTransformation?: string;
  proposalSummary?: string;
  keyword?: string;
  plannedChapters?: string | number;
  wordGoal?: string | number;
  targetDate?: string;
  workflowStatus?: string;
  tags?: string[];
  collection?: string;
  priority?: string;
  coverUrl?: string;
  color?: string;
  icon?: string;
  notes?: string;
  status: BookStatus;
  stage: string;
  createdAt: string;
  updatedAt: string;
};

export type LibraryData = {
  projects: VerbumProject[];
  books: VerbumBook[];
};

export type CreateProjectInput = {
  name: string;
  description?: string;
};

export type CreateBookInput = {
  project_id: number;
  title: string;
  subtitle?: string;
  series?: string;
  category?: string;
  genre?: string;
  audience?: string;
  age_range?: string;
  language?: string;
  country?: string;
  author_name?: string;
  coauthor_name?: string;
  main_objective?: string;
  reader_problem?: string;
  reader_transformation?: string;
  proposal_summary?: string;
  keyword?: string;
  planned_chapters?: number;
  word_goal?: number;
  target_date?: string;
  workflow_status?: string;
  tags?: string[];
  collection?: string;
  priority?: string;
  cover_url?: string;
  color?: string;
  icon?: string;
  notes?: string;
};

declare global {
  interface Window {
    VerbumStudioConfig?: { apiRoot: string; nonce: string; version: string };
  }
}
