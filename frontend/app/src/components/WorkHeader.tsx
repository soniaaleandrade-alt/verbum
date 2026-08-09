import type { WorkWorkspaceData } from '../types/verbum';

type Props = {
  workspace: WorkWorkspaceData;
  onBack: () => void;
};

function relativeDate(value: string) {
  if (!value) return '—';
  const date = new Date(value);
  const today = new Date();
  const diff = Math.floor((today.setHours(0,0,0,0) - new Date(date).setHours(0,0,0,0)) / 86400000);
  if (diff === 0) return 'hoje';
  if (diff === 1) return 'ontem';
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

export function WorkHeader({ workspace, onBack }: Props) {
  const { book, metrics, currentStage, workflow } = workspace;
  const currentLabel = workflow.find((step) => step.key === currentStage)?.label ?? 'Identificação';
  return (
    <header className="verbum-work-header">
      <button type="button" className="verbum-work-back" onClick={onBack}>‹ Obras</button>
      <div className="verbum-work-summary">
        <div className="verbum-work-cover" style={{ backgroundColor: book.color || '#7a3042' }}>
          {book.coverUrl ? <img src={book.coverUrl} alt="" /> : <span>V</span>}
        </div>
        <div className="verbum-work-title">
          <h1 title={book.title}>{book.title}</h1>
          <p title={book.subtitle || ''}>{book.subtitle || 'Sem subtítulo'}</p>
        </div>
        <div className="verbum-work-metrics">
          <div><span>ETAPA ATUAL</span><strong>{currentLabel}</strong></div>
          <div><span>IMO</span><strong>{metrics.imo == null ? '—' : `${metrics.imo}%`}</strong></div>
          <div><span>RME</span><strong>{metrics.rme == null ? '—' : `${metrics.rme}%`}</strong></div>
          <div><span>PROGRESSO</span><strong>{metrics.progress}%</strong></div>
          <div><span>CAPÍTULOS</span><strong>{metrics.chapters}</strong></div>
          <div><span>PALAVRAS</span><strong>{metrics.words.toLocaleString('pt-BR')}</strong></div>
          <div><span>ÚLTIMA EDIÇÃO</span><strong>{relativeDate(metrics.lastEdited)}</strong></div>
        </div>
      </div>
    </header>
  );
}
