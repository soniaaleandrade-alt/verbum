import type { WorkStageKey, WorkWorkflowStep } from '../types/verbum';

type Props = {
  steps: WorkWorkflowStep[];
  selectedStage: WorkStageKey;
  onSelect: (stage: WorkStageKey) => void;
};

export function WorkWorkflow({ steps, selectedStage, onSelect }: Props) {
  return (
    <nav className="verbum-workflow" aria-label="Fluxo editorial da obra">
      {steps.map((step) => {
        const selected = step.key === selectedStage;
        const locked = step.status === 'locked';
        return (
          <button
            key={step.key}
            type="button"
            className={`verbum-workflow-step is-${step.status}${selected ? ' is-selected' : ''}`}
            disabled={locked}
            aria-current={selected ? 'step' : undefined}
            onClick={() => onSelect(step.key)}
          >
            <span className="verbum-workflow-marker" aria-hidden="true">
              {step.status === 'completed' ? '✓' : locked ? '⌑' : step.order}
            </span>
            <span>{step.label}</span>
          </button>
        );
      })}
    </nav>
  );
}
