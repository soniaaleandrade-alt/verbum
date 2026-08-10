import type { WorkWorkspaceData } from './verbum';

export type WorkVersionType = 'milestone' | 'manual_backup' | 'before_change' | 'after_change' | 'review' | 'audit' | 'layout' | 'publication' | 'before_restore' | 'duplicate' | 'other';
export type WorkVersionSummary = {
  id: string;
  number: string;
  name: string;
  type: WorkVersionType;
  typeLabel: string;
  notes: string;
  protected: boolean;
  origin: string;
  createdAt: string;
  createdBy: string;
  chapterCount: number;
  wordCount: number;
  characterCount: number;
  hash: string;
  auditBaseline: boolean;
};
export type WorkVersionChapter = { id: string; planningItemId: string; number: number; title: string; content: string; wordCount: number };
export type WorkVersionSnapshot = {
  metadata: { title?: string; subtitle?: string };
  structure: Array<Record<string, unknown>>;
  frontMatter: Record<string, string>;
  chapters: WorkVersionChapter[];
};
export type WorkVersionsFlags = { history_checked?: boolean; changes_evaluated?: boolean; strategy_defined?: boolean; backup_checked?: boolean; current_validated?: boolean };
export type WorkVersionsChecklistItem = { key: string; label: string; completed: boolean; automatic?: boolean };
export type WorkVersionsStage = {
  bookId: string;
  title: string;
  types: Array<{ key: WorkVersionType; label: string }>;
  versions: WorkVersionSummary[];
  currentHash: string;
  latestVersionId: string;
  latestVersionNumber: string;
  auditBaselineId: string;
  unversioned: { hasChanges: boolean; wordDelta: number; changedChapters: number; lastVersionHash: string };
  integrityErrors: string[];
  flags: WorkVersionsFlags;
  checklist: WorkVersionsChecklistItem[];
  progress: number;
  completedCount: number;
  total: number;
  ready: boolean;
  completed: boolean;
  completedAt: string;
  lastComparisonAt: string;
};
export type WorkVersionMutationResponse = { versionsStage: WorkVersionsStage; workspace: WorkWorkspaceData };
export type WorkVersionDetailResponse = { version: WorkVersionSummary; snapshot: WorkVersionSnapshot };
export type WorkVersionComparison = {
  summary: { changedChapters: number; addedChapters: number; removedChapters: number; renamedChapters: number; movedChapters: number; wordDelta: number; structureChanged: boolean; frontMatterChanged: boolean };
  chapters: Array<{ id: string; status: 'added' | 'removed' | 'changed' | 'unchanged'; title: string; previousTitle?: string; renamed?: boolean; moved?: boolean; paragraphs: { added: string[]; removed: string[] } }>;
};
export type WorkVersionCompareResponse = { from: WorkVersionSummary; to: WorkVersionSummary; comparison: WorkVersionComparison };
export type CreateWorkVersionInput = { name: string; type: WorkVersionType; notes: string; protected: boolean; major: boolean; force?: boolean; audit_baseline?: boolean };
