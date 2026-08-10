export type ApiSuccess<T> = { success: true; data: T };
export type ApiError = { success: false; error: { code: string; message: string } };
export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export type Health = { status: string; version: string };
export type CurrentUser = { id: string; name: string; email: string; avatarUrl?: string };

export type ProjectStatus = 'active' | 'archived';
export type BookStatus = 'active' | 'archived';
export type WorkflowStageStatus = 'locked' | 'available' | 'in_progress' | 'completed';
export type WorkStageKey = 'identification' | 'project' | 'planning' | 'development' | 'general_review' | 'versions' | 'audit' | 'editorial_desk' | 'layout' | 'legal' | 'publication';

export type VerbumProject = { id: string; name: string; description: string; status: ProjectStatus; createdAt: string; updatedAt: string };
export type VerbumBook = {
  id: string; projectId: string; title: string; subtitle?: string; series?: string; category?: string; genre?: string; audience?: string; ageRange?: string; language?: string; country?: string; authorName?: string; coauthorName?: string; mainObjective?: string; readerProblem?: string; readerTransformation?: string; proposalSummary?: string; synopsis?: string; keyword?: string; keywords?: string[]; plannedChapters?: string | number; wordGoal?: string | number; targetDate?: string; workflowStatus?: string; tags?: string[]; collection?: string; priority?: string; coverId?: string | number; coverUrl?: string; color?: string; icon?: string; notes?: string; status: BookStatus; stage: WorkStageKey | string; createdAt: string; updatedAt: string;
};

export type WorkWorkflowStep = { key: WorkStageKey; label: string; status: WorkflowStageStatus; order: number };
export type WorkMetrics = { imo: number | null; rme: number | null; progress: number; chapters: number; words: number; lastEdited: string };
export type StageChecklistItem = { key: string; label: string; completed: boolean };
export type IdentificationProgress = { progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; checklist: StageChecklistItem[] };
export type WorkProjectObjective = { id: string; text: string; order: number };
export type WorkProjectValues = { generalObjective: string; specificObjectives: WorkProjectObjective[]; purpose: string; audience: string; benefits: string; transformation: string; centralMessage: string; differentials: string; valueProposition: string; keyword: string; motivation: string; verse: string; guidingPhrase: string };
export type WorkProjectProgress = { progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; checklist: StageChecklistItem[]; values: WorkProjectValues };
export type WorkProjectMutationResponse = { projectStage: WorkProjectProgress; workspace: WorkWorkspaceData };

export type PlanningStructureType = 'part' | 'chapter' | 'subchapter';
export type PlanningStructureItem = { id: string; type: PlanningStructureType; title: string; parentId?: string; order: number };
export type WorkPlanningValues = { centralQuestion: string; mainThesis: string; overview: string; methodology: string; presentationForm: string; approach: string; generalStructure: string; editorialNotes: string; writingStrategy: string; initialSchedule: string; targetChapters: number; targetWords: number; targetPages: number; structureItems: PlanningStructureItem[] };
export type WorkPlanningCounts = { parts: number; chapters: number; subchapters: number };
export type WorkPlanningProgress = { progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; checklist: StageChecklistItem[]; values: WorkPlanningValues; counts: WorkPlanningCounts; generatedChapterIds: string[]; chaptersGenerated: boolean };
export type WorkPlanningInput = { central_question: string; main_thesis: string; overview: string; methodology: string; presentation_form: string; approach: string; general_structure: string; editorial_notes: string; writing_strategy: string; initial_schedule: string; target_chapters: number; target_words: number; target_pages: number; structure_items: Array<{ id?: string; type: PlanningStructureType; title: string; parentId?: string; order: number }> };
export type WorkPlanningMutationResponse = { planningStage: WorkPlanningProgress; workspace: WorkWorkspaceData };

export type WorkWorkspaceData = { book: VerbumBook; project: VerbumProject; currentStage: WorkStageKey; workflow: WorkWorkflowStep[]; identification: IdentificationProgress; metrics: WorkMetrics };
export type LibraryData = { projects: VerbumProject[]; books: VerbumBook[] };
export type CreateProjectInput = { name: string; description?: string };
export type CreateBookInput = { project_id: number; title: string; subtitle?: string; series?: string; category?: string; genre?: string; audience?: string; age_range?: string; language?: string; country?: string; author_name?: string; coauthor_name?: string; main_objective?: string; reader_problem?: string; reader_transformation?: string; proposal_summary?: string; synopsis?: string; keyword?: string; keywords?: string[]; planned_chapters?: number; word_goal?: number; target_date?: string; workflow_status?: string; tags?: string[]; collection?: string; priority?: string; cover_id?: number; cover_url?: string; color?: string; icon?: string; notes?: string };
export type IdentificationInput = { title: string; subtitle: string; workflow_status: string; genre: string; language: string; audience: string; synopsis: string; keywords: string[]; color: string };
export type WorkProjectInput = { general_objective: string; purpose: string; audience: string; benefits: string; transformation: string; central_message: string; differentials: string; value_proposition: string; keyword: string; motivation: string; verse: string; guiding_phrase: string; specific_objectives: Array<{ id?: string; text: string; order: number }> };

declare global { interface Window { VerbumStudioConfig?: { apiRoot: string; nonce: string; version: string; logoutUrl?: string } } }
