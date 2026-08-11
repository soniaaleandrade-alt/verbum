import type { StageChecklistItem, WorkWorkspaceData } from './verbum';

export type AuditSeverity = 'info' | 'warning' | 'pending' | 'critical';
export type AuditFindingStatus = 'open' | 'reviewing' | 'resolved' | 'ignored';
export type AuditResult = 'in_progress' | 'requires_corrections' | 'approved';

export type AuditOption = { key: string; label: string };
export type AuditVersion = { id: string; number: string; name: string; hash: string; createdAt: string; chapterCount: number; wordCount: number; protected: boolean };
export type AuditRound = { id: string; number: number; versionId: string; versionNumber: string; versionName: string; versionHash: string; startedAt: string; completedAt: string; status: string; result: AuditResult };
export type AuditFinding = {
  id: string; category: string; categoryLabel: string; severity: AuditSeverity; severityLabel: string; description: string; recommendation: string;
  chapterId: string; chapterTitle: string; status: AuditFindingStatus; statusLabel: string; justification: string; origin: 'automatic' | 'manual' | string; createdAt: string; resolvedAt: string;
};
export type AuditSource = { id: string; chapterId: string; chapterTitle: string; category: string; title: string; author: string; reference: string; used: boolean; verified: boolean };
export type AuditElement = { key: string; label: string; required: boolean; present: boolean; status: 'present' | 'required_missing' | 'not_used' | string };
export type AuditSummary = { total: number; conforming: number; info: number; warnings: number; pending: number; critical: number; openCritical: number; openPending: number; ignored: number; resolved: number };
export type AuditFlags = Record<string, boolean>;
export type AuditReport = { generatedAt?: string; workTitle?: string; versionNumber?: string; versionName?: string; versionHash?: string; roundNumber?: number; summary?: AuditSummary; result?: string; resultLabel?: string; findings?: Array<Record<string, string>> };
export type WorkAuditStage = {
  bookId: string; title: string; round: AuditRound; rounds: AuditRound[]; version: AuditVersion; workChangedAfterBaseline: boolean; currentWorkHash: string;
  categories: AuditOption[]; severities: AuditOption[]; statuses: AuditOption[]; findings: AuditFinding[]; summary: AuditSummary; sources: AuditSource[];
  terminology: Array<{ term: string; note: string }>; elements: AuditElement[]; flags: AuditFlags; finalConfirmation: boolean; reportGenerated: boolean; report: AuditReport;
  checklist: Array<StageChecklistItem & { automatic?: boolean }>; progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; result: AuditResult; resultLabel: string;
};
export type AuditMutationResponse = { auditStage: WorkAuditStage; workspace: WorkWorkspaceData };
export type AuditFindingInput = { category?: string; severity?: AuditSeverity; description?: string; recommendation?: string; chapter_id?: string; status?: AuditFindingStatus; justification?: string };
export type AuditAssistantResponse = { suggestion: string; action: string };
