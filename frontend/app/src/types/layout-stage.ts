import type { WorkWorkspaceData } from './verbum';

export type LayoutIssue = { id: string; category: string; categoryLabel: string; description: string; status: 'open' | 'resolved'; statusLabel: string; createdAt: string; updatedAt: string };
export type LayoutProof = { id: string; number: number; label: string; createdAt: string; pageCount: number; note: string; url: string; watermark: string };
export type LayoutConfig = {
  preset: string;
  format: { name: string; widthMm: number; heightMm: number; bleedMm: number };
  margins: { topMm: number; bottomMm: number; innerMm: number; outerMm: number; mirrored: boolean };
  typography: { bodyFont: string; bodySizePt: number; lineHeight: number; color: string; align: string; headingFont: string };
  paragraph: { firstLineCm: number; spaceBeforePt: number; spaceAfterPt: number; noIndentAfterHeading: boolean; hyphenation: boolean; widowsOrphans: boolean };
  masterPage: { evenHeader: string; oddHeader: string; footer: string; pageNumberPosition: string; hideOnChapterOpening: boolean; frontMatterRoman: boolean };
  chapter: { start: string; titleAlign: string; topSpaceMm: number; showNumber: boolean; dropCaps: boolean; dropCapLines: number };
  quotes: { font: string; sizePt: number; indentCm: number; align: string };
  religiousStyles: Record<string, boolean>;
  notes: { mode: string };
  toc: { parts: boolean; chapters: boolean; subchapters: boolean; levels: number };
  preview: { spread: boolean };
  cover: { status: string };
};
export type LayoutStage = {
  bookId: string; title: string;
  version: { id: string; number: string; name: string; hash: string; chapterCount: number; wordCount: number; createdAt: string };
  round: { id: string; number: number; versionId: string; versionNumber: string; status: string; startedAt: string; completedAt: string };
  rounds: Array<{ id: string; number: number; versionId: string; versionNumber: string; status: string; startedAt: string; completedAt: string }>;
  config: LayoutConfig;
  editorial: { identity: Record<string, unknown>; edition: Record<string, unknown>; elements: Array<Record<string, unknown>>; elementOrder: string[]; layoutBrief: Record<string, unknown>; coverBrief: Record<string, unknown> };
  issueCategories: Array<{ key: string; label: string }>; issueStatuses: Array<{ key: string; label: string }>;
  issues: LayoutIssue[]; openIssueCount: number; proofs: LayoutProof[]; pageCount: number; chapterCount: number;
  flags: Record<string, boolean>; finalConfirmation: boolean; baselineValid: boolean;
  checklist: Array<{ key: string; label: string; completed: boolean; automatic: boolean }>;
  progress: number; completedCount: number; total: number; ready: boolean; completed: boolean;
};
export type LayoutPreviewPage = { number: number; kind: string; title: string; content: string; chapterId?: string; chapterNumber?: number };
export type LayoutPreview = { pages: LayoutPreviewPage[]; pageCount: number; version: LayoutStage['version']; config: LayoutConfig };
export type LayoutMutationResponse = { layoutStage: LayoutStage; workspace: WorkWorkspaceData };
export type LayoutAssistantResponse = { suggestion: string; action: string };
