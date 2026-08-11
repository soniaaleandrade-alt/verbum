import type { WorkWorkspaceData } from './verbum';

export type EditorialAssessmentStatus = 'pending' | 'approved' | 'adjust';
export type EditorialAdjustmentType = 'editorial' | 'content';
export type EditorialPriority = 'low' | 'medium' | 'high' | 'critical';
export type EditorialAdjustmentStatus = 'open' | 'in_progress' | 'resolved' | 'dismissed';

export type EditorialOption = { key: string; label: string };
export type EditorialChecklistItem = { key: string; label: string; completed: boolean; automatic: boolean };
export type EditorialRound = { id: string; number: number; versionId: string; versionNumber: string; versionName: string; versionHash: string; status: string; startedAt: string; updatedAt: string; completedAt: string };
export type EditorialVersion = { id: string; number: string; name: string; hash: string; chapterCount: number; wordCount: number; createdAt: string; auditApprovedAt?: string };

export type EditorialElement = { key: string; label: string; include: boolean };
export type EditorialFields = {
  identity: { titleFinal: string; subtitleFinal: string; titleOptions: string[]; authorDisplay: string; genre: string; subgenre: string; category: string; language: string; audience: string; shortDescription: string; synopsisShort: string; synopsisFull: string };
  positioning: { need: string; proposal: string; audience: string; differential: string; perception: string; references: string };
  backCover: { headline: string; text: string; highlight: string; authorShort: string };
  authorProfile: { displayName: string; fullName: string; shortBio: string; longBio: string; site: string; social: string };
  elements: EditorialElement[];
  elementOrder: string[];
  edition: { formats: string[]; edition: string; year: string; place: string; publisherType: string; publisherName: string; trimSize: string };
  layoutBrief: { style: string; dropCaps: boolean; chapterOpening: string; quoteHighlights: boolean; footnotes: boolean; images: boolean; boxes: boolean; specialElements: string; notes: string };
  coverBrief: { concept: string; feeling: string; includeElements: string; avoidElements: string; palette: string; visualReferences: string; coverPhrase: string };
  opinion: { summary: string; strengths: string; attention: string; recommendations: string; risks: string; conclusion: string };
  religious: { nature: string; bible: boolean; catechism: boolean; magisterium: boolean; specializedReview: boolean };
};

export type EditorialAssessment = { key: string; label: string; status: EditorialAssessmentStatus; note: string };
export type EditorialAdjustment = { id: string; type: EditorialAdjustmentType; typeLabel: string; priority: EditorialPriority; priorityLabel: string; description: string; chapterId: string; chapterTitle: string; responsible: string; status: EditorialAdjustmentStatus; statusLabel: string; justification: string; createdAt: string; updatedAt: string };
export type EditorialFlags = Record<string, boolean>;

export type EditorialDeskStage = {
  bookId: string;
  title: string;
  version: EditorialVersion;
  round: EditorialRound;
  rounds: EditorialRound[];
  fields: EditorialFields;
  assessments: Record<string, EditorialAssessment>;
  assessmentCriteria: EditorialOption[];
  adjustments: EditorialAdjustment[];
  adjustmentTypes: EditorialOption[];
  priorities: EditorialOption[];
  adjustmentStatuses: EditorialOption[];
  openBlockingCount: number;
  requiresNewAudit: boolean;
  auditStillValid: boolean;
  flags: EditorialFlags;
  finalConfirmation: boolean;
  checklist: EditorialChecklistItem[];
  progress: number;
  completedCount: number;
  total: number;
  ready: boolean;
  completed: boolean;
  status: string;
  statusLabel: string;
};

export type EditorialMutationResponse = { editorialDesk: EditorialDeskStage; workspace: WorkWorkspaceData };
export type EditorialAssistantResponse = { suggestion: string; action: string };
export type EditorialAdjustmentInput = Partial<Pick<EditorialAdjustment, 'type' | 'priority' | 'description' | 'responsible' | 'status' | 'justification'>> & { chapter_id?: string };
