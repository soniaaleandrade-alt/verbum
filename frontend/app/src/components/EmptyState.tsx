type EmptyStateProps = {
  onCreate?: () => void;
};

export function EmptyState({ onCreate }: EmptyStateProps) {
  return (
    <div className="verbum-empty-state">
      <div className="verbum-empty-symbol" aria-hidden="true">V</div>
      <div>
        <h3>Sua próxima obra começa aqui</h3>
        <p>Crie uma obra para organizar planejamento, pesquisa, redação, revisão e publicação em um único fluxo.</p>
      </div>
      <button type="button" className="verbum-primary-button" onClick={onCreate}>Criar nova obra</button>
    </div>
  );
}
