type Props = {
  canGoBack: boolean;
  onPrevious: () => void;
  onBackToLibrary: () => void;
};

export function WorkspaceFooter({ canGoBack, onPrevious, onBackToLibrary }: Props) {
  return (
    <footer className="verbum-workspace-footer">
      <button type="button" className="verbum-workspace-previous" onClick={canGoBack ? onPrevious : onBackToLibrary}>
        ‹ {canGoBack ? 'Etapa anterior' : 'Voltar para Obras'}
      </button>
      <div className="verbum-workspace-save-actions">
        <span className="verbum-save-state is-saved">Salvo</span>
        <button type="button" className="verbum-secondary-button" disabled>Salvar</button>
        <button type="button" className="verbum-primary-button" disabled>Salvar e continuar ›</button>
      </div>
    </footer>
  );
}
