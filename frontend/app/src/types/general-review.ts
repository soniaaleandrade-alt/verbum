import type { StageChecklistItem, WorkWorkspaceData } from './verbum';

export type GeneralReviewEvaluationStatus = '' | 'yes' | 'partial' | 'adjust';
export type GeneralReviewIssueType = 'coherence' | 'repetition' | 'gap' | 'continuity' | 'structure' | 'language' | 'reference' | 'introduction' | 'conclusion' | 'editorial' | 'other';
export type GeneralReviewPriority = 'low' | 'medium' | 'high' | 'critical';
export type GeneralReviewTransitionStatus = 'unreviewed' | 'good' | 'needs_work' | 'missing';

export type GeneralReviewChapter = {
  id: string;
  planningItemId: string;
  number: number;
  title: string;
  wordCount: number;
  completed: boolean;
  lastEdited: string;
  alteredAfterRevision: boolean;
  changedDuringGeneralReview: boolean;
};

export type GeneralReviewOutlineItem = { type: 'part' | 'chapter' | 'subchapter'; title?: string; chapter?: GeneralReviewChapter };
export type GeneralReviewDirection = {
  generalObjective: string;
  purpose: string;
  audience: string;
  centralMessage: string;
  transformation: string;
  differentials: string;
  centralQuestion: string;
  mainThesis: string;
};
export type GeneralReviewEvaluations = {
  objective: GeneralReviewEvaluationStatus;
  central_message: GeneralReviewEvaluationStatus;
  audience: GeneralReviewEvaluationStatus;
  transformation: GeneralReviewEvaluationStatus;
  central_question: GeneralReviewEvaluationStatus;
  main_thesis: GeneralReviewEvaluationStatus;
};
export type GeneralReviewTransition = {
  key: string;
  fromChapterId: string;
  fromTitle: string;
  toChapterId: string;
  toTitle: string;
  status: GeneralReviewTransitionStatus;
  note: string;
};
export type GeneralReviewTerm = { id: string; term: string; note: string };
export type GeneralReviewFrontMatter = { preface: string; presentation: string; authorNote: string; introduction: string; conclusion: string };
export type GeneralReviewIssue = {
  id: string;
  type: GeneralReviewIssueType;
  typeLabel: string;
  description: string;
  chapterId: string;
  priority: GeneralReviewPriority;
  priorityLabel: string;
  status: 'pending' | 'resolved';
  createdAt: string;
  resolvedAt: string;
};
export type GeneralReviewFlags = {
  objective_checked?: boolean;
  central_question_answered?: boolean;
  thesis_developed?: boolean;
  structure_reviewed?: boolean;
  continuity_reviewed?: boolean;
  repetitions_reviewed?: boolean;
  gaps_reviewed?: boolean;
  language_reviewed?: boolean;
  references_checked?: boolean;
  front_back_matter_reviewed?: boolean;
};
export type GeneralReviewChecklistItem = StageChecklistItem & { automatic?: boolean };
export type GeneralReviewSnapshot = { id: string; savedAt: string; kind: string; wordCount: number; chapterCount: number };
export type GeneralReviewProgress = {
  bookId: string;
  title: string;
  summary: { chapters: number; completedChapters: number; words: number; pendingIssues: number; pendingCriticalIssues: number; progress: number };
  chapters: GeneralReviewChapter[];
  outline: GeneralReviewOutlineItem[];
  direction: GeneralReviewDirection;
  evaluations: GeneralReviewEvaluations;
  transitions: GeneralReviewTransition[];
  terms: GeneralReviewTerm[];
  frontMatter: GeneralReviewFrontMatter;
  issues: GeneralReviewIssue[];
  issueTypes: Array<{ key: GeneralReviewIssueType; label: string }>;
  priorities: Array<{ key: GeneralReviewPriority; label: string }>;
  flags: GeneralReviewFlags;
  checklist: GeneralReviewChecklistItem[];
  completedCount: number;
  total: number;
  progress: number;
  finalConfirmation: boolean;
  ready: boolean;
  completed: boolean;
  startedAt: string;
  completedAt: string;
  lastSavedAt: string;
  alteredAfterCompletion: boolean;
  snapshots: GeneralReviewSnapshot[];
};
export type GeneralReviewInput = {
  flags: GeneralReviewFlags;
  evaluations: GeneralReviewEvaluations;
  transitions: GeneralReviewTransition[];
  terms: GeneralReviewTerm[];
  front_matter: GeneralReviewFrontMatter;
  final_confirmation: boolean;
  save_mode: 'autosave' | 'manual';
};
export type GeneralReviewIssueInput = { type?: GeneralReviewIssueType; description?: string; chapter_id?: string; priority?: GeneralReviewPriority; status?: 'pending' | 'resolved' };
export type GeneralReviewMutationResponse = { generalReview: GeneralReviewProgress; workspace: WorkWorkspaceData };
export type GeneralReviewReading = { chapters: Array<{ id: string; planningItemId: string; number: number; title: string; content: string; wordCount: number }>; outline: Array<{ type: 'part' | 'chapter' | 'subchapter'; title?: string; chapter?: { id: string; number: number; title: string } }> };
export type GeneralReviewAssistantResponse = { suggestion: string; action: string };
