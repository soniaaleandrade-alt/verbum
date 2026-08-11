import type { WorkWorkspaceData } from './verbum';

export type LegalOption = { key: string; label: string };
export type LegalVersion = { id: string; number: string; name: string; hash: string; chapterCount: number; wordCount: number; createdAt: string };
export type LegalRound = { id: string; number: number; versionId: string; versionHash: string; status: string; startedAt: string; updatedAt: string; completedAt: string; legalSnapshotHash: string };
export type LegalDocument = { id: string; name: string; category: string; categoryLabel: string; status: string; statusLabel: string; number: string; date: string; expiresAt: string; notes: string; fileUrl: string; createdAt: string; updatedAt: string };
export type ThirdPartyItem = { id: string; description: string; origin: string; holder: string; location: string; useType: string; authorizationRequired: boolean; status: string; statusLabel: string; licenseType: string; fileUrl: string; notes: string; createdAt: string; updatedAt: string };
export type LegalIssue = { id: string; type: string; typeLabel: string; priority: string; priorityLabel: string; description: string; responsible: string; dueAt: string; status: string; statusLabel: string; createdAt: string; updatedAt: string };
export type LegalProof = { id: string; number: number; label: string; fileUrl: string; notes: string; createdAt: string };
export type LegalHistory = { id: string; event: string; detail: string; createdAt: string };
export type IsbnRecord = { format: string; label: string; status: string; number: string; requestedAt: string; issuedAt: string; issuer: string; notes: string; structureValid: boolean };
export type LegalState = {
  identity: Record<string, unknown> & { title: string; subtitle: string; author: string; edition: string; year: string; place: string; publisherType: string; publisherName: string; language: string; format: string; pageCount: number; publicationFormats: string[] };
  isbn: Record<string, IsbnRecord>;
  cataloging: Record<string, unknown> & { status: string; professional: string; professionalRegistration: string; requestedAt: string; receivedAt: string; fileUrl: string; notes: string; insertedInLayout: boolean };
  copyright: Record<string, unknown>;
  authorRegistration: Record<string, unknown>;
  credits: Record<string, unknown>;
  ecclesial: Record<string, unknown> & { applicable: boolean };
  academic: Record<string, unknown> & { applicable: boolean };
  finalFiles: Record<string, string>;
  technical: Record<string, unknown>;
};
export type LegalStage = {
  bookId: string; title: string; version: LegalVersion; layout: { pageCount: number; proofId: string; format: string; bleedMm: number; completedAt: string };
  round: LegalRound; rounds: LegalRound[]; state: LegalState; documents: LegalDocument[]; thirdParty: ThirdPartyItem[]; issues: LegalIssue[]; proofs: LegalProof[]; history: LegalHistory[];
  flags: Record<string, boolean>; finalConfirmation: boolean; processStatuses: LegalOption[]; documentCategories: LegalOption[]; issueTypes: LegalOption[]; priorities: LegalOption[]; issueStatuses: LegalOption[]; thirdPartyStatuses: LegalOption[];
  authorizationBlockers: number; blockingIssueCount: number; openIssueCount: number; baselineValid: boolean; checklist: Array<{ key: string; label: string; completed: boolean; automatic: boolean }>;
  progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; alerts: string[];
};
export type LegalMutationResponse = { legalStage: LegalStage; workspace: WorkWorkspaceData };
export type LegalAssistantResponse = { suggestion: string; action: string };
