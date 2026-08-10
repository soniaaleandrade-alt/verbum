import type { LibraryData, VerbumBook, WorkStageKey } from '../types/verbum';

type DashboardProps = {
  userName: string;
  library: LibraryData;
  onOpenLibrary: () => void;
  onOpenBook?: (book: VerbumBook) => void;
};

const stages: Array<{ key: WorkStageKey; label: string }> = [
  { key: 'identification', label: 'Identificação' },
  { key: 'project', label: 'Projeto da Obra' },
  { key: 'planning', label: 'Planejamento' },
  { key: 'development', label: 'Desenvolvimento' },
  { key: 'general_review', label: 'Revisão Geral' },
  { key: 'versions', label: 'Controle de Versões' },
  { key: 'audit', label: 'Auditoria' },
  { key: 'editorial_desk', label: 'Mesa Editorial' },
  { key: 'layout', label: 'Diagramação' },
  { key: 'legal', label: 'Trâmites Legais' },
  { key: 'publication', label: 'Publicação' },
];

function stageIndex(book: VerbumBook) {
  const index = stages.findIndex((stage) => stage.key === book.stage);
  return index >= 0 ? index : 0;
}

function stageLabel(book: VerbumBook) {
  return stages[stageIndex(book)]?.label ?? 'Identificação';
}

function structuralProgress(book: VerbumBook) {
  if (book.stage === 'publication' && book.workflowStatus === 'Concluída') return 100;
  return Math.round((stageIndex(book) / stages.length) * 100);
}

function blockProgress(book: VerbumBook | undefined, start: number, end: number) {
  if (!book) return 0;
  const size = end - start + 1;
  const completed = Math.max(0, Math.min(size, stageIndex(book) - start));
  if (start === 10 && book.stage === 'publication' && book.workflowStatus === 'Concluída') return 100;
  return Math.round((completed / size) * 100);
}

function nextAction(book: VerbumBook | undefined) {
  if (!book) return 'Criar a primeira obra';
  const actions: Record<string, string> = {
    identification: 'Concluir a Identificação da Obra',
    project: 'Concluir o Projeto da Obra',
    planning: 'Estruturar o Planejamento da Obra',
    development: 'Continuar o desenvolvimento dos capítulos',
    general_review: 'Concluir a Revisão Geral',
    versions: 'Organizar o Controle de Versões',
    audit: 'Executar a Auditoria editorial',
    editorial_desk: 'Preparar a Mesa Editorial',
    layout: 'Avançar com a Diagramação',
    legal: 'Concluir os Trâmites Legais',
    publication: 'Preparar a Publicação',
  };
  return actions[String(book.stage)] ?? 'Continuar a obra';
}

function initials(name: string) {
  return name.trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('') || 'V';
}

function updatedLabel(value?: string) {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleDateString('pt-BR');
}

export function Dashboard({ userName, library, onOpenLibrary, onOpenBook }: DashboardProps) {
  const activeBooks = library.books.filter((book) => book.status === 'active');
  const latestBooks = [...activeBooks].sort((a, b) => String(b.updatedAt || '').localeCompare(String(a.updatedAt || ''))).slice(0, 2);
  const currentBook = latestBooks[0];
  const published = library.books.filter((book) => book.stage === 'publication' && book.workflowStatus === 'Concluída').length;
  const structuralAverage = activeBooks.length
    ? Math.round(activeBooks.reduce((total, book) => total + structuralProgress(book), 0) / activeBooks.length)
    : 0;
  const chapters = activeBooks.reduce((total, book) => total + Number(book.plannedChapters || 0), 0);
  const macro = [
    { label: 'Preparação', value: blockProgress(currentBook, 0, 2), color: '#2786e8' },
    { label: 'Produção', value: blockProgress(currentBook, 3, 3), color: '#14b8a6' },
    { label: 'Validação', value: blockProgress(currentBook, 4, 6), color: '#ef4444' },
    { label: 'Editorial', value: blockProgress(currentBook, 7, 9), color: '#8b38d1' },
    { label: 'Publicação', value: blockProgress(currentBook, 10, 10), color: '#22c55e' },
  ];
  const donut = macro.map((item) => item.color).join(',');

  return (
    <div className="verbum-official-dashboard">
      <section className="verbum-dashboard-hero" aria-label="Verbum Studio">
        <div className="verbum-dashboard-hero-copy">
          <span className="verbum-dashboard-hero-mark">▣</span>
          <div>
            <strong>VERBUM STUDIO</strong>
            <small>Sistema Operacional para Escritores</small>
            <em>Do primeiro pensamento à publicação.</em>
          </div>
        </div>
        <div className="verbum-dashboard-hero-art" aria-hidden="true"><span>V</span><span>✦</span></div>
      </section>

      <section className="verbum-dashboard-greeting">
        <div><h1>Olá, {userName}</h1><p>Visão geral do seu estúdio editorial</p></div>
        <div className="verbum-dashboard-top-actions">
          <button type="button" className="verbum-dashboard-link" disabled={activeBooks.length < 2}>Comparar</button>
          <button type="button" className="verbum-dashboard-new" onClick={onOpenLibrary}>+ Nova Obra</button>
        </div>
      </section>

      <section className="verbum-dashboard-shortcuts" aria-label="Acessos principais">
        <button type="button" className="verbum-dashboard-shortcut is-library" onClick={onOpenLibrary}><span>▣</span><div><strong>Biblioteca</strong><small>Gerencie todas as suas obras.</small></div></button>
        <button type="button" className="verbum-dashboard-shortcut is-reports" disabled><span>▥</span><div><strong>Relatórios</strong><small>Acompanhe sua produtividade.</small></div></button>
        <button type="button" className="verbum-dashboard-shortcut is-calendar" disabled><span>□</span><div><strong>Calendário Editorial</strong><small>Planeje sua jornada de escrita.</small></div></button>
      </section>

      <section className="verbum-dashboard-board">
        <article className="verbum-dashboard-card verbum-dashboard-statistics">
          <h2><span>▥</span> Estatísticas</h2>
          <div className="verbum-dashboard-stat-row"><div><strong>{activeBooks.length}</strong><span>Obras</span></div><div><strong>{published}</strong><span>Publicadas</span></div><div><strong>—</strong><span>IMO médio</span></div></div>
          <p className="verbum-dashboard-muted">IMO será calculado quando os indicadores de maturidade forem implementados.</p>
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-latest">
          <div className="verbum-dashboard-card-title"><h2><span>▣</span> Últimas Obras</h2><button type="button" onClick={onOpenLibrary}>Ver todas</button></div>
          {latestBooks.length ? latestBooks.map((book) => (
            <button key={book.id} type="button" className="verbum-dashboard-book-row" onClick={() => onOpenBook ? onOpenBook(book) : onOpenLibrary()}>
              <span className="verbum-dashboard-book-color" style={{ background: book.color || '#7a3042' }} />
              <span className="verbum-dashboard-book-copy"><strong>{book.title}</strong><small>{stageLabel(book)}</small></span>
              <b>{structuralProgress(book)}%</b>
            </button>
          )) : <p className="verbum-dashboard-empty-copy">Nenhuma obra cadastrada.</p>}
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-next">
          <h2><span>◎</span> Próxima Ação</h2>
          {currentBook ? <>
            <span className="verbum-dashboard-kicker">LIVRO</span><strong className="verbum-dashboard-current-title">{currentBook.title}</strong>
            <span className="verbum-dashboard-kicker">ETAPA ATUAL</span><p>{stageLabel(currentBook)}</p>
            <div className="verbum-dashboard-next-box"><span>PRÓXIMA AÇÃO</span><strong>{nextAction(currentBook)}</strong></div>
            <small>Fluxo editorial da etapa atual</small>
            <button type="button" onClick={() => onOpenBook ? onOpenBook(currentBook) : onOpenLibrary()}>Continuar →</button>
          </> : <><p>Crie uma obra para iniciar seu fluxo editorial.</p><button type="button" onClick={onOpenLibrary}>Nova obra →</button></>}
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-maturity">
          <div className="verbum-dashboard-card-title"><h2><span>◔</span> Índice de Maturidade da Obra</h2></div>
          <div className="verbum-dashboard-maturity-value"><strong>—</strong><span>Aguardando cálculo do IMO</span></div>
          <div className="verbum-dashboard-progress-line"><span style={{ width: `${structuralProgress(currentBook || ({} as VerbumBook))}%` }} /></div>
          <p>{currentBook ? `A obra está na etapa ${stageLabel(currentBook)}.` : 'Cadastre uma obra para iniciar.'}</p>
          <small>Última atualização: {updatedLabel(currentBook?.updatedAt)}</small>
          <div className="verbum-dashboard-next-inline"><span>Próximo passo</span><strong>{nextAction(currentBook)}</strong></div>
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-radar">
          <h2><span>◎</span> Radar de Maturidade</h2>
          <div className="verbum-radar-placeholder" aria-label="Radar aguardando indicadores"><div className="verbum-radar-ring r1"/><div className="verbum-radar-ring r2"/><div className="verbum-radar-ring r3"/><span>IMO/RME</span></div>
          <p>O radar será alimentado pelos indicadores editoriais das próximas etapas.</p>
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-overall">
          <h2><span>◔</span> Progresso Geral</h2>
          <div className="verbum-dashboard-donut" style={{ background: `conic-gradient(${donut})` }}><span><strong>{structuralAverage}%</strong><small>estrutural</small></span></div>
          <div className="verbum-dashboard-legend">{macro.map((item) => <div key={item.label}><i style={{ background: item.color }}/><span>{item.label}</span><strong>{item.value}%</strong></div>)}</div>
          <div className="verbum-dashboard-overall-footer"><div><strong>{chapters}</strong><span>capítulos previstos</span></div><div><strong>{activeBooks.length}</strong><span>obras ativas</span></div></div>
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-quick">
          <h2><span>✧</span> Acesso Rápido</h2>
          <div className="verbum-dashboard-quick-grid">
            {['Calendário Editorial','Cronograma','Metas de Escrita','Relatórios','Backup','Exportação'].map((label) => <button type="button" disabled key={label}><span>□</span>{label}</button>)}
          </div>
        </article>

        <article className="verbum-dashboard-card verbum-dashboard-trend">
          <h2><span>↗</span> Tendência</h2>
          <span>Progresso estrutural médio do estúdio</span><strong>{structuralAverage}%</strong>
          <div className="verbum-dashboard-trend-rule" />
          <div><p><span>CAPÍTULOS</span><b>{chapters}</b></p><p><span>OBRAS ATIVAS</span><b>{activeBooks.length}</b></p></div>
        </article>
      </section>
      <span className="verbum-dashboard-user-badge" aria-hidden="true">{initials(userName)}</span>
    </div>
  );
}
