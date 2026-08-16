import type { WorkStageKey, WorkWorkflowStep } from '../types/verbum';

type Props = {
  steps: WorkWorkflowStep[];
  selectedStage: WorkStageKey;
  onSelect: (stage: WorkStageKey) => void;
};

type VisualGroup = {
  label: string;
  stages: WorkStageKey[];
};

const visualGroups: VisualGroup[] = [
  { label: 'Identificação', stages: ['identification'] },
  { label: 'Fundação', stages: ['project'] },
  { label: 'Estrutura', stages: ['planning'] },
  { label: 'Capítulos', stages: ['development'] },
  { label: 'Revisão', stages: ['general_review', 'versions', 'audit'] },
  { label: 'Preparação Editorial', stages: ['editorial_desk', 'layout', 'legal'] },
  { label: 'Publicação', stages: ['publication'] },
];

export function workflowVisualLabel(stage: WorkStageKey): string {
  return visualGroups.find((group) => group.stages.includes(stage))?.label ?? 'Identificação';
}

export function WorkWorkflow({ steps, selectedStage, onSelect }: Props) {
  const visualSteps = visualGroups.map((group, index) => {
    const legacy = steps.filter((step) => group.stages.includes(step.key));
    const selected = group.stages.includes(selectedStage);
    const completed = legacy.length > 0 && legacy.every((step) => step.status === 'completed');
    const inProgress = selected || legacy.some((step) => step.status === 'in_progress');
    const available = legacy.some((step) => step.status !== 'locked');
    const status = completed ? 'completed' : inProgress ? 'in_progress' : available ? 'available' : 'locked';
    const selectedLegacy = legacy.find((step) => step.key === selectedStage);
    const target = selectedLegacy?.key ?? legacy.find((step) => step.status !== 'locked')?.key ?? legacy[0]?.key;

    return {
      label: group.label,
      order: index + 1,
      selected,
      status,
      target,
    };
  });

  return (
    <nav className="verbum-workflow verbum-workflow-visual" aria-label="Jornada editorial da obra">
      {visualSteps.map((step) => {
        const locked = step.status === 'locked' || !step.target;
        return (
          <button
            key={step.label}
            type="button"
            className={`verbum-workflow-step is-${step.status}${step.selected ? ' is-selected' : ''}`}
            disabled={locked}
            aria-current={step.selected ? 'step' : undefined}
            onClick={() => { if (step.target) onSelect(step.target); }}
          >
            <span className="verbum-workflow-marker" aria-hidden="true">
              {step.status === 'completed' ? '✓' : step.order}
            </span>
            <span>{step.label}</span>
          </button>
        );
      })}
    </nav>
  );
}
