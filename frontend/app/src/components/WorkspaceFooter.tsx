type SaveState = 'saved' | 'dirty' | 'saving' | 'error';

type Props = {
  canGoBack: boolean;
  onPrevious: () => void;
  onBackToLibrary: () => void;
  previousDisabled?: boolean;
  previousLabel?: string;
  hidePrevious?: boolean;
  saveState?: SaveState;
  saveDisabled?: boolean;
  hideSaveButton?: boolean;
  saveStateLabels?: Partial<Record<SaveState, string>>;
  continueDisabled?: boolean;
  continueLabel?: string;
  onSave?: () => void;
  onContinue?: () => void;
};

const stateLabels: Record<SaveState, string> = {
  saved: 'Salvo',
  dirty: 'Alterações não salvas',
  saving: 'Salvando…',
  error: 'Erro ao salvar',
};

export function WorkspaceFooter({
  canGoBack,
  onPrevious,
  onBackToLibrary,
  previousDisabled,
  previousLabel,
  hidePrevious = false,
  saveState = 'saved',
  saveDisabled = true,
  hideSaveButton = false,
  saveStateLabels,
  continueDisabled = true,
  continueLabel = 'Salvar e continuar ›',
  onSave,
  onContinue,
}: Props) {
  const isPreviousDisabled = previousDisabled ?? !canGoBack;
  const label = previousLabel ?? 'Etapa anterior';
  const visibleStateLabels = { ...stateLabels, ...saveStateLabels };
  return (
    <footer className={`verbum-workspace-footer${hidePrevious ? ' is-first-stage' : ''}`}>
      {hidePrevious ? (
        <span className="verbum-workspace-footer-spacer" aria-hidden="true" />
      ) : (
        <button
          type="button"
          className="verbum-workspace-previous"
          disabled={isPreviousDisabled}
          onClick={canGoBack ? onPrevious : onBackToLibrary}
        >
          ‹ {label}
        </button>
      )}
      <div className="verbum-workspace-save-actions">
        <span className={`verbum-save-state is-${saveState}`}>{visibleStateLabels[saveState]}</span>
        {!hideSaveButton && <button type="button" className="verbum-secondary-button" disabled={saveDisabled} onClick={onSave}>Salvar</button>}
        <button type="button" className="verbum-primary-button" disabled={continueDisabled} onClick={onContinue}>{continueLabel}</button>
      </div>
    </footer>
  );
}
