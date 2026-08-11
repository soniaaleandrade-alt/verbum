import { useEffect, useMemo, useState } from 'react';
import { DevelopmentStage } from '../components/DevelopmentStage';
import { GeneralReviewStage } from '../components/GeneralReviewStage';
import { IdentificationStage } from '../components/IdentificationStage';
import { PlanningStage } from '../components/PlanningStage';
import { ProjectStage } from '../components/ProjectStage';
import { WorkVersionsStage } from '../components/WorkVersionsStage';
import { WorkWorkflow } from '../components/WorkWorkflow';
import { WorkspaceFooter } from '../components/WorkspaceFooter';
import type { WorkStageKey, WorkWorkspaceData } from '../types/verbum';

const stageDescriptions: Record<WorkStageKey, string> = {
  identification: 'Dados fundamentais e identidade editorial da obra.',
  project: 'Fundação conceitual, objetivo, público e proposta da obra.',
  planning: 'Estrutura, organização editorial e índice provisório.',
  development: 'Gestão e desenvolvimento dos capítulos da obra.',
  general_review: 'Revisão integral da obra após o desenvolvimento dos capítulos.',
  versions: 'Controle das versões editoriais e histórico de alterações.',
  audit: 'Verificações editoriais, estruturais e de consistência.',
  editorial_desk: 'Organização final antes das etapas de publicação.',
  layout: 'Preparação visual e diagramação da obra.',
  legal: 'Organização dos trâmites legais e documentação editorial.',
  publication: 'Etapa final de publicação da obra.',
};

type Props = {
  workspace: WorkWorkspaceData;
  selectedStage: WorkStageKey;
  onStageChange: (stage: WorkStageKey) => void;
  onBackToLibrary: () => void;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onPersisted: () => void | Promise<void>;
};

export function WorkWorkspace({ workspace, selectedStage, onStageChange, onBackToLibrary, onWorkspaceChange, onPersisted }: Props) {
  const [dirty, setDirty] = useState(false);
  const selected = workspace.workflow.find((step) => step.key === selectedStage) ?? workspace.workflow[0];
  const accessibleSteps = useMemo(() => workspace.workflow.filter((step) => step.status !== 'locked'), [workspace.workflow]);
  const selectedAccessibleIndex = accessibleSteps.findIndex((step) => step.key === selected.key);

  useEffect(() => {
    const handler = (event: BeforeUnloadEvent) => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = '';
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [dirty]);

  function guarded(action: () => void) {
    if (dirty && !window.confirm('Existem alterações que ainda não foram salvas. Sair sem salvar?')) return;
    setDirty(false);
    action();
  }

  function previous() {
    if (selectedAccessibleIndex > 0) guarded(() => onStageChange(accessibleSteps[selectedAccessibleIndex - 1].key));
    else guarded(onBackToLibrary);
  }

  return (
    <div className="verbum-workspace">
      <div className="verbum-work-breadcrumb">Minhas Obras <span>›</span> {workspace.book.title || 'Obra sem título'} <span>›</span> {selected.label}</div>
      <WorkWorkflow steps={workspace.workflow} selectedStage={selected.key} onSelect={(stage) => guarded(() => onStageChange(stage))} />
      {selected.key === 'identification' ? (
        <IdentificationStage workspace={workspace} onWorkspaceChange={onWorkspaceChange} onStageChange={onStageChange} onBackToLibrary={() => guarded(onBackToLibrary)} onDirtyChange={setDirty} onPersisted={onPersisted} />
      ) : selected.key === 'project' ? (
        <ProjectStage workspace={workspace} onWorkspaceChange={onWorkspaceChange} onStageChange={(stage) => guarded(() => onStageChange(stage))} onDirtyChange={setDirty} onPersisted={onPersisted} />
      ) : selected.key === 'planning' ? (
        <PlanningStage workspace={workspace} onWorkspaceChange={onWorkspaceChange} onStageChange={(stage) => guarded(() => onStageChange(stage))} onDirtyChange={setDirty} onPersisted={onPersisted} />
      ) : selected.key === 'development' ? (
        <DevelopmentStage workspace={workspace} onWorkspaceChange={onWorkspaceChange} onStageChange={(stage) => guarded(() => onStageChange(stage))} onPersisted={onPersisted} />
      ) : selected.key === 'general_review' ? (
        <GeneralReviewStage workspace={workspace} onWorkspaceChange={onWorkspaceChange} onStageChange={(stage) => guarded(() => onStageChange(stage))} onPersisted={onPersisted} />
      ) : selected.key === 'versions' ? (
        <WorkVersionsStage workspace={workspace} onWorkspaceChange={onWorkspaceChange} onStageChange={(stage) => guarded(() => onStageChange(stage))} onPersisted={onPersisted} />
      ) : (
        <>
          <section className="verbum-stage-content">
            <div className="verbum-stage-placeholder">
              <span className="verbum-eyebrow">Etapa {selected.order} de {workspace.workflow.length}</span>
              <h2>{selected.label}</h2>
              <p>{stageDescriptions[selected.key]}</p>
              {selected.key === workspace.currentStage ? <div className="verbum-stage-notice is-current">Esta é a etapa atual da obra. O conteúdo funcional desta etapa será implementado no Sprint correspondente.</div> : <div className="verbum-stage-notice">Você está consultando uma etapa anterior já liberada no fluxo editorial.</div>}
            </div>
          </section>
          <WorkspaceFooter canGoBack={selectedAccessibleIndex > 0} onPrevious={previous} onBackToLibrary={() => guarded(onBackToLibrary)} />
        </>
      )}
    </div>
  );
}
