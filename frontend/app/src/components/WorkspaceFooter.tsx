type SaveState = 'saved' | 'dirty' | 'saving' | 'error';

type Props = {
  canGoBack: boolean;
  onPrevious: () => void;
  onBackToLibrary: () => void;
  saveState?: SaveState;
  saveDisabled?: boolean;
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
  saveState = 'saved',
  saveDisabled = true,
  continueDisabled = true,
  continueLabel = 'Salvar e continuar ›',
  onSave,
  onContinue,
}: Props) {
  return (
    <footer className="verbum-workspace-footer">
      <button type="button" className="verbum-workspace-previous" onClick={canGoBack ? onPrevious : onBackToLibrary}>
        ‹ {canGoBack ? 'Etapa anterior' : 'Voltar para Obras'}
      </button>
      <div className="verbum-workspace-save-actions">
        <span className={`verbum-save-state is-${saveState}`}>{stateLabels[saveState]}</span>
        <button type="button" className="verbum-secondary-button" disabled={saveDisabled} onClick={onSave}>Salvar</button>
        <button type="button" className="verbum-primary-button" disabled={continueDisabled} onClick={onContinue}>{continueLabel}</button>
      </div>
    </footer>
  );
}
