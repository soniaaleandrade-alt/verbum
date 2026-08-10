export type ApiSuccess<T> = { success: true; data: T };
export type ApiError = { success: false; error: { code: string; message: string } };
export type ApiResponse<T> = ApiSuccess<T> | ApiError;

export type Health = { status: string; version: string };
export type CurrentUser = { id: string; name: string; email: string; avatarUrl?: string };

export type ProjectStatus = 'active' | 'archived';
export type BookStatus = 'active' | 'archived';
export type WorkflowStageStatus = 'locked' | 'available' | 'in_progress' | 'completed';
export type WorkStageKey = 'identification' | 'project' | 'planning' | 'development' | 'general_review' | 'versions' | 'audit' | 'editorial_desk' | 'layout' | 'legal' | 'publication';
export type ChapterStageKey = 'preparation' | 'research' | 'writing' | 'revision';

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

export type ChapterWorkflowStep = { key: ChapterStageKey; label: string; status: WorkflowStageStatus; order: number };
export type DevelopmentChapter = { id: string; bookId: string; planningItemId: string; number: number; title: string; stage: ChapterStageKey; stageLabel: string; progress: number; completed: boolean; completedStages: ChapterStageKey[]; workflow: ChapterWorkflowStep[]; wordCount: number; lastEdited: string; previousId?: string | null; nextId?: string | null; position?: number; totalChapters?: number };
export type DevelopmentSummary = { total: number; completed: number; preparation: number; research: number; writing: number; revision: number; progress: number; words: number };
export type DevelopmentOutlineItem = { type: 'part' | 'chapter' | 'subchapter'; title?: string; part?: string; chapterId?: string | null; chapter?: DevelopmentChapter };
export type WorkDevelopmentProgress = { summary: DevelopmentSummary; chapters: DevelopmentChapter[]; outline: DevelopmentOutlineItem[]; ready: boolean; completed: boolean };
export type WorkDevelopmentMutationResponse = { developmentStage: WorkDevelopmentProgress; workspace: WorkWorkspaceData };

export type ChapterPreparationStructureItem = { id: string; text: string; order: number };
export type ChapterPreparationSourceOption = { key: string; label: string };
export type ChapterPreparationValues = { subtitle: string; objective: string; centralQuestion: string; purpose: string; thesis: string; mainMessage: string; guidingPhrase: string; keywords: string[]; structureItems: ChapterPreparationStructureItem[]; sourceCategories: string[]; spiritualIntention: string; virtue: string; writingPrayer: string; notes: string };
export type ChapterPreparationProgress = { chapterId: string; title: string; progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; checklist: StageChecklistItem[]; values: ChapterPreparationValues; sourceOptions: ChapterPreparationSourceOption[]; completedAt: string };
export type ChapterPreparationInput = { subtitle: string; objective: string; central_question: string; purpose: string; thesis: string; main_message: string; guiding_phrase: string; keywords: string[]; structure_items: Array<{ id?: string; text: string; order: number }>; source_categories: string[]; spiritual_intention: string; virtue: string; writing_prayer: string; notes: string };
export type ChapterPreparationMutationResponse = { preparation: ChapterPreparationProgress; chapter: DevelopmentChapter; developmentStage: WorkDevelopmentProgress };

export type ResearchCategory = 'scripture' | 'catechism' | 'magisterium' | 'saints' | 'church_fathers' | 'books' | 'articles' | 'historical_documents' | 'other';
export type ChapterResearchCategoryOption = { key: ResearchCategory; label: string; selectedInPreparation: boolean; reviewed: boolean; count: number };
export type ChapterResearchIdea = { id: string; title: string; description: string; tags: string[]; structureItemId: string };
export type ChapterResearchSource = { id: string; category: ResearchCategory; categoryLabel: string; title: string; author: string; reference: string; excerpt: string; notes: string; application: string; tags: string[]; url: string; structureItemId: string; highlighted: boolean; selectedForWriting: boolean; status: 'research' | 'selected' | 'used'; details: Record<string, string>; createdAt: string; updatedAt: string };
export type ChapterResearchDirection = { objective: string; centralQuestion: string; thesis: string; keywords: string[]; sourceCategories: ResearchCategory[]; structureItems: ChapterPreparationStructureItem[] };
export type ChapterResearchCounts = { total: number; selectedForWriting: number; highlighted: number; scripture: number; catechism: number; magisterium: number; saints: number; church_fathers: number; books: number; articles: number; historical_documents: number; other: number };
export type ChapterResearchProgress = { chapterId: string; title: string; preparationCompleted: boolean; preparation: ChapterResearchDirection; sources: ChapterResearchSource[]; ideas: ChapterResearchIdea[]; categoryOptions: ChapterResearchCategoryOption[]; reviewedCategories: ResearchCategory[]; directionReviewed: boolean; counts: ChapterResearchCounts; progress: number; completedCount: number; total: number; checklist: StageChecklistItem[]; ready: boolean; completed: boolean; completedAt: string };
export type ChapterResearchSourceInput = { category: ResearchCategory; title: string; author: string; reference: string; excerpt: string; notes: string; application: string; tags: string[]; url: string; structure_item_id: string; highlighted: boolean; selected_for_writing: boolean; details: Record<string, string> };
export type ChapterResearchStateInput = { direction_reviewed: boolean; reviewed_categories: ResearchCategory[]; ideas: ChapterResearchIdea[] };
export type ChapterResearchMutationResponse = { research: ChapterResearchProgress; chapter: DevelopmentChapter; developmentStage: WorkDevelopmentProgress; source?: ChapterResearchSource };

export type ChapterWritingSection = { id: string; title: string; content: string; order: number; sourceStructureItemId: string };
export type ChapterWritingNote = { id: string; text: string; createdAt: string };
export type ChapterWritingSource = { id: string; category: ResearchCategory; title: string; author: string; reference: string; excerpt: string; application: string; structureItemId: string; highlighted: boolean; selectedForWriting: boolean; used: boolean };
export type ChapterWritingIdea = ChapterResearchIdea & { used: boolean };
export type ChapterWritingVersion = { id: string; savedAt: string; kind: 'autosave' | 'manual' | 'completion' | string; wordCount: number; characterCount: number };
export type ChapterWritingFlags = { thesis_covered?: boolean; sources_used?: boolean; citations_verified?: boolean; author_reviewed?: boolean; goal_analyzed?: boolean; ready_for_revision?: boolean };
export type ChapterWritingProgress = {
  chapterId: string; title: string; researchCompleted: boolean; preparation: { objective: string; centralQuestion: string; thesis: string; keywords: string[]; structureItems: ChapterPreparationStructureItem[] };
  introduction: string; sections: ChapterWritingSection[]; conclusion: string; wordGoal: number; wordCount: number; characterCount: number; goalProgress: number; timeSeconds: number; startedAt: string;
  notes: ChapterWritingNote[]; comments: ChapterWritingNote[]; flags: ChapterWritingFlags; sources: ChapterWritingSource[]; ideas: ChapterWritingIdea[]; usedIdeaIds: string[]; versions: ChapterWritingVersion[];
  progress: number; completedCount: number; total: number; checklist: StageChecklistItem[]; ready: boolean; completed: boolean; completedAt: string; lastSavedAt: string;
};
export type ChapterWritingInput = { introduction: string; sections: ChapterWritingSection[]; conclusion: string; word_goal: number; notes: ChapterWritingNote[]; comments: ChapterWritingNote[]; flags: ChapterWritingFlags; used_source_ids: string[]; used_idea_ids: string[]; session_seconds: number; save_mode: 'autosave' | 'manual' };
export type ChapterWritingMutationResponse = { writing: ChapterWritingProgress; chapter: DevelopmentChapter; developmentStage: WorkDevelopmentProgress };
export type ChapterWritingAssistantResponse = { suggestion: string; action: string };

export type WorkWorkspaceData = { book: VerbumBook; project: VerbumProject; currentStage: WorkStageKey; workflow: WorkWorkflowStep[]; identification: IdentificationProgress; metrics: WorkMetrics };
export type LibraryData = { projects: VerbumProject[]; books: VerbumBook[] };
export type CreateProjectInput = { name: string; description?: string };
export type CreateBookInput = { project_id: number; title: string; subtitle?: string; series?: string; category?: string; genre?: string; audience?: string; age_range?: string; language?: string; country?: string; author_name?: string; coauthor_name?: string; main_objective?: string; reader_problem?: string; reader_transformation?: string; proposal_summary?: string; synopsis?: string; keyword?: string; keywords?: string[]; planned_chapters?: number; word_goal?: number; target_date?: string; workflow_status?: string; tags?: string[]; collection?: string; priority?: string; cover_id?: number; cover_url?: string; color?: string; icon?: string; notes?: string };
export type IdentificationInput = { title: string; subtitle: string; workflow_status: string; genre: string; language: string; audience: string; synopsis: string; keywords: string[]; color: string };
export type WorkProjectInput = { general_objective: string; purpose: string; audience: string; benefits: string; transformation: string; central_message: string; differentials: string; value_proposition: string; keyword: string; motivation: string; verse: string; guiding_phrase: string; specific_objectives: Array<{ id?: string; text: string; order: number }> };

declare global { interface Window { VerbumStudioConfig?: { apiRoot: string; nonce: string; version: string; logoutUrl?: string } } }
