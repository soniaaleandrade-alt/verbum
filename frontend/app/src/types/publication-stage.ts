import type { WorkWorkspaceData } from './verbum';

export type PublicationOption = { key: string; label: string };
export type PublicationChannel = { id: string; name: string; type: string; typeLabel: string; format: string; required: boolean; status: string; statusLabel: string; url: string; externalId: string; fileUrl: string; submittedAt: string; approvedAt: string; publishedAt: string; price: string; currency: string; notes: string; createdAt: string; updatedAt: string };
export type PublicationTask = { id: string; description: string; phase: string; phaseLabel: string; status: string; statusLabel: string; createdAt: string; updatedAt: string };
export type PublicationRecord = { id: string; channelId: string; channel: string; type: string; format: string; identifier: string; url: string; fileUrl: string; fileReferenceHash: string; editionHash: string; publishedAt: string; price: string; currency: string; createdAt: string };
export type PublicationUpdate = { id: string; type: string; typeLabel: string; description: string; version: string; fileUrl: string; publishedAt: string; createdAt: string };
export type PublicationState = {
  identity: Record<string, unknown>;
  metadata: { title: string; subtitle: string; author: string; shortDescription: string; description: string; keywords: string[]; primaryCategory: string; secondaryCategory: string; language: string; edition: string; year: string; publisher: string };
  package: { finalFileUrl: string; coverUrl: string; digitalFileUrl: string; digitalCoverUrl: string; legalSnapshotHash: string; isbn: Record<string, Record<string, unknown>> };
  pricing: Record<string, { label: string; price: string; currency: string; unitCost: string; channelFeePercent: string; promotionalPrice: string; promotionStart: string; promotionEnd: string }>;
  launch: { mode: string; plannedDate: string; actualDate: string; time: string; notes: string };
  materials: { cover3dUrl: string; coverImageUrl: string; bannerUrl: string; releaseUrl: string; socialText: string; buyLink: string };
  release: { title: string; summary: string; presentation: string; aboutAuthor: string; highlights: string; publicationInfo: string };
};
export type PublicationStage = {
  bookId: string; title: string; legal: { snapshotHash: string; version: Record<string, unknown>; layout: Record<string, unknown>; identity: Record<string, unknown>; finalFiles: Record<string, unknown>; isbn: Record<string, unknown>; completedAt: string };
  round: { id: string; number: number; status: string; startedAt: string; updatedAt: string; completedAt: string; publishedAt: string; editionHash: string };
  rounds: Array<Record<string, unknown>>; state: PublicationState; channels: PublicationChannel[]; tasks: PublicationTask[]; history: Array<{ id: string; event: string; detail: string; createdAt: string }>;
  records: PublicationRecord[]; updates: PublicationUpdate[]; flags: Record<string, boolean>; finalConfirmation: boolean;
  channelTypes: PublicationOption[]; channelStatuses: PublicationOption[]; taskPhases: PublicationOption[]; taskStatuses: PublicationOption[]; updateTypes: PublicationOption[];
  requiredChannelCount: number; resolvedRequiredCount: number; publishedChannelCount: number; formatCount: number; baselineValid: boolean; consistencyWarnings: string[];
  checklist: Array<{ key: string; label: string; completed: boolean; automatic: boolean }>;
  progress: number; completedCount: number; total: number; ready: boolean; completed: boolean; publishedAt: string; editionHash: string;
};
export type PublicationMutationResponse = { publicationStage: PublicationStage; workspace: WorkWorkspaceData };
export type PublicationAssistantResponse = { suggestion: string; action: string };
