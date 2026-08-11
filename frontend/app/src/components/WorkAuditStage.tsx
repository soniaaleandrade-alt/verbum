import { useEffect, useMemo, useState } from 'react';
import {
  assistWorkAudit,
  completeWorkAudit,
  createAuditFinding,
  deleteAuditFinding,
  generateAuditReport,
  getWorkAudit,
  saveWorkAuditState,
  updateAuditFinding,
} from '../services/work-audit-service';
import type { WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import type { AuditFinding, AuditFindingStatus, AuditFlags, AuditSeverity, WorkAuditStage as AuditStageData } from '../types/work-audit';
import { WorkspaceFooter } from './WorkspaceFooter';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onPersisted: () => void | Promise<void>;
};

export function WorkAuditStage({ workspace, onWorkspaceChange, onStageChange, onPersisted }: Props) {
  const [data, setData] = useState<AuditStageData | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [category, setCategory] = useState('all');
  const [severity, setSeverity] = useState('all');
  const [status, setStatus] = useState('all');
  const [newDescription, setNewDescription] = useState('');
  const [newCategory, setNewCategory] = useState('editorial');
  const [newSeverity, setNewSeverity] = useState<AuditSeverity>('warning');
  const [assistant, setAssistant] = useState('');

  useEffect(() => {
    let alive = true;
    getWorkAudit(workspace.book.id).then((result) => alive && setData(result)).catch((cause) => alive && setError(cause instanceof Error ? cause.message : 'Não foi possível carregar a Auditoria da Obra.'));
    return () => { alive = false; };
  }, [workspace.book.id]);

  const visibleFindings = useMemo(() => {
    if (!data) return [];
    return data.findings.filter((finding) => (category === 'all' || finding.category === category) && (severity === 'all' || finding.severity === severity) && (status === 'all' || finding.status === status));
  }, [data, category, severity, status]);

  async function mutate(action: () => Promise<{ auditStage: AuditStageData; workspace: WorkWorkspaceData }>) {
    if (busy) return;
    setBusy(true); setError('');
    try {
      const result = await action();
      setData(result.auditStage);
      onWorkspaceChange(result.workspace);
      await onPersisted();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a operação.');
    } finally { setBusy(false); }
  }

  async function toggleFlag(key: string, checked: boolean) {
    if (!data) return;
    const flags: AuditFlags = { ...data.flags, [key]: checked };
    setData({ ...data, flags });
    await mutate(() => saveWorkAuditState(workspace.book.id, flags, data.finalConfirmation));
  }

  async function setFindingStatus(finding: AuditFinding, next: AuditFindingStatus) {
    let justification = '';
    if (next === 'ignored') {
      justification = window.prompt('Informe a justificativa para ignorar este achado:')?.trim() || '';
      if (!justification) return;
    }
    await mutate(() => updateAuditFinding(workspace.book.id, finding.id, { status: next, justification }));
  }

  if (!data) return <section className="verbum-stage-content"><div className={`verbum-audit-state${error ? ' is-error' : ''}`}>{error || 'Carregando Auditoria da Obra...'}</div></section>;

  const summary = data.summary;
  return <>
    <section className="verbum-stage-content verbum-audit-stage">
      <header className="verbum-audit-heading">
        <div><span className="verbum-eyebrow">Etapa 7 de 11</span><h2>Auditoria da Obra</h2><p>Verifique a integridade editorial da versão congelada antes da Mesa Editorial.</p></div>
        <div className="verbum-audit-version"><small>Versão em Auditoria</small><strong>{data.version.number} — {data.version.name}</strong><span>{data.version.chapterCount} capítulos · {data.version.wordCount.toLocaleString('pt-BR')} palavras</span><code>{data.version.hash.slice(0, 18)}…</code></div>
      </header>

      {data.workChangedAfterBaseline && <div className="verbum-audit-warning"><strong>A obra de trabalho possui alterações posteriores à versão auditada.</strong><p>A rodada continua vinculada a {data.version.number}. Para auditar as correções, volte ao Controle de Versões e selecione uma nova versão.</p><button type="button" onClick={() => onStageChange('versions')}>Voltar ao Controle de Versões</button></div>}

      <div className="verbum-audit-stats">
        <article><strong>{summary.total}</strong><span>Achados</span></article><article><strong>{summary.resolved + summary.ignored}</strong><span>Conformes/Tratados</span></article><article><strong>{summary.warnings}</strong><span>Avisos</span></article><article><strong>{summary.openPending}</strong><span>Pendências abertas</span></article><article className={summary.openCritical ? 'is-critical' : ''}><strong>{summary.openCritical}</strong><span>Críticos</span></article><article><strong>{data.progress}%</strong><span>Progresso</span></article>
      </div>

      <div className="verbum-audit-layout">
        <main>
          <section className="verbum-audit-card">
            <div className="verbum-audit-card-head"><div><h3>Achados da Auditoria</h3><p>Verificações automáticas e registros manuais da rodada #{data.round.number}.</p></div><span className={`result-${data.result}`}>{data.resultLabel}</span></div>
            <div className="verbum-audit-filters"><select value={category} onChange={(event) => setCategory(event.target.value)}><option value="all">Todas as categorias</option>{data.categories.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><select value={severity} onChange={(event) => setSeverity(event.target.value)}><option value="all">Todas as severidades</option>{data.severities.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><select value={status} onChange={(event) => setStatus(event.target.value)}><option value="all">Todos os status</option>{data.statuses.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select></div>
            <div className="verbum-audit-findings">{visibleFindings.length === 0 ? <p>Nenhum achado neste filtro.</p> : visibleFindings.map((finding) => <article key={finding.id} className={`severity-${finding.severity} status-${finding.status}`}><header><div><span>{finding.categoryLabel}</span><strong>{finding.severityLabel}</strong>{finding.origin === 'automatic' && <em>Automático</em>}</div><small>{finding.statusLabel}</small></header><p>{finding.description}</p>{finding.chapterTitle && <small>Capítulo: {finding.chapterTitle}</small>}{finding.recommendation && <blockquote>{finding.recommendation}</blockquote>}{finding.justification && <p className="verbum-audit-justification"><b>Justificativa:</b> {finding.justification}</p>}<footer>{finding.status !== 'resolved' && <button type="button" disabled={busy || data.completed} onClick={() => void setFindingStatus(finding, 'resolved')}>✓ Resolver</button>}{finding.status !== 'reviewing' && <button type="button" disabled={busy || data.completed} onClick={() => void setFindingStatus(finding, 'reviewing')}>Em análise</button>}{finding.status !== 'ignored' && <button type="button" disabled={busy || data.completed} onClick={() => void setFindingStatus(finding, 'ignored')}>Ignorar com justificativa</button>}{finding.origin === 'manual' && <button type="button" className="is-danger" disabled={busy || data.completed} onClick={() => window.confirm('Excluir este achado manual?') && void mutate(() => deleteAuditFinding(workspace.book.id, finding.id))}>Excluir</button>}</footer></article>)}</div>
          </section>

          <section className="verbum-audit-card"><div className="verbum-audit-card-head"><div><h3>+ Registrar achado</h3><p>Inclua uma verificação manual que não foi detectada automaticamente.</p></div></div><div className="verbum-audit-new"><select value={newCategory} onChange={(event) => setNewCategory(event.target.value)}>{data.categories.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><select value={newSeverity} onChange={(event) => setNewSeverity(event.target.value as AuditSeverity)}>{data.severities.map((item) => <option key={item.key} value={item.key}>{item.label}</option>)}</select><textarea value={newDescription} onChange={(event) => setNewDescription(event.target.value)} placeholder="Descreva o achado ou ponto que precisa ser conferido..." /><button type="button" disabled={!newDescription.trim() || busy || data.completed} onClick={() => void mutate(() => createAuditFinding(workspace.book.id, { category: newCategory, severity: newSeverity, description: newDescription })).then(() => setNewDescription(''))}>Registrar achado</button></div></section>

          <section className="verbum-audit-card"><div className="verbum-audit-card-head"><div><h3>Elementos editoriais</h3><p>Elementos obrigatórios e opcionais preservados na versão auditada.</p></div></div><div className="verbum-audit-elements">{data.elements.map((item) => <article key={item.key} className={item.present ? 'is-present' : item.required ? 'is-missing' : 'is-optional'}><strong>{item.label}</strong><span>{item.present ? 'Presente ✓' : item.required ? 'Necessário' : 'Não utilizado'}</span></article>)}</div></section>

          <section className="verbum-audit-card"><div className="verbum-audit-card-head"><div><h3>Fontes e Referências</h3><p>{data.sources.filter((source) => source.used).length} fonte(s) efetivamente utilizada(s) congeladas nesta rodada.</p></div></div><div className="verbum-audit-sources">{data.sources.filter((source) => source.used).length === 0 ? <p>Nenhuma fonte utilizada foi registrada.</p> : data.sources.filter((source) => source.used).map((source) => <article key={source.id}><div><strong>{source.reference || source.title || 'Fonte sem referência'}</strong><span>{source.chapterTitle}</span></div><em className={source.verified ? 'is-verified' : ''}>{source.verified ? 'Verificada ✓' : 'Conferir'}</em></article>)}</div></section>
        </main>

        <aside>
          <section className="verbum-audit-card verbum-audit-progress"><div className="verbum-audit-card-head"><h3>Checklist final</h3><strong>{data.progress}%</strong></div><div className="verbum-audit-progressbar"><span style={{ width: `${data.progress}%` }} /></div>{data.checklist.map((item) => { const manual = !item.automatic && item.key !== 'completed'; const checked = manual ? Boolean(data.flags[item.key]) : item.completed; return <label key={item.key} className={checked ? 'is-complete' : ''}><input type="checkbox" checked={checked} disabled={!manual || busy || data.completed} onChange={(event) => manual && void toggleFlag(item.key, event.target.checked)} /><span>{item.label}</span></label>; })}<label className={data.finalConfirmation ? 'is-complete is-confirmation' : 'is-confirmation'}><input type="checkbox" checked={data.finalConfirmation} disabled={busy || data.completed} onChange={(event) => { const value = event.target.checked; setData({ ...data, finalConfirmation: value }); void mutate(() => saveWorkAuditState(workspace.book.id, data.flags, value)); }} /><span>Confirmo que esta versão auditada está pronta para aprovação.</span></label><button type="button" className="verbum-primary-button" disabled={!data.ready || busy || data.completed} onClick={() => void mutate(() => completeWorkAudit(workspace.book.id)).then(() => onStageChange('editorial_desk'))}>{data.completed ? 'Auditoria aprovada ✓' : 'Aprovar Auditoria'}</button></section>

          <section className="verbum-audit-card"><div className="verbum-audit-card-head"><h3>Relatório de Auditoria</h3></div>{data.reportGenerated ? <><p><b>Versão:</b> {data.report.versionNumber}</p><p><b>Gerado:</b> {data.report.generatedAt ? new Date(data.report.generatedAt).toLocaleString('pt-BR') : '—'}</p><p><b>Resultado:</b> {data.report.resultLabel}</p><button type="button" disabled={busy || data.completed} onClick={() => void mutate(() => generateAuditReport(workspace.book.id))}>Atualizar relatório</button></> : <><p>Gere o relatório consolidado antes da aprovação final.</p><button type="button" disabled={busy || data.completed} onClick={() => void mutate(() => generateAuditReport(workspace.book.id))}>Gerar relatório</button></>}</section>

          <section className="verbum-audit-card verbum-audit-assistant"><h3>Assistente de Auditoria</h3><p>Gera achados para análise humana. Não aprova a obra nem inventa fontes.</p><div>{[['gaps','Lacunas editoriais'],['consistency','Inconsistências'],['terms','Termos'],['repetitions','Repetições'],['markers','Marcadores pendentes'],['structure','Estrutura'],['checks','Itens para conferência'],['doctrine','Conferência doutrinal']].map(([key, label]) => <button key={key} type="button" disabled={busy || data.completed} onClick={() => { setBusy(true); setError(''); assistWorkAudit(workspace.book.id, key).then((result) => setAssistant(result.suggestion)).catch((cause) => setError(cause instanceof Error ? cause.message : 'Não foi possível usar o Assistente.')).finally(() => setBusy(false)); }}>{label}</button>)}</div>{assistant && <div className="verbum-audit-assistant-result"><textarea value={assistant} onChange={(event) => setAssistant(event.target.value)} /><button type="button" disabled={busy} onClick={() => void mutate(() => createAuditFinding(workspace.book.id, { category: 'editorial', severity: 'warning', description: assistant })).then(() => setAssistant(''))}>Aceitar como achado</button><button type="button" onClick={() => setAssistant('')}>Descartar</button></div>}</section>

          <section className="verbum-audit-card"><div className="verbum-audit-card-head"><h3>Histórico de rodadas</h3></div>{data.rounds.map((round) => <article key={round.id} className="verbum-audit-round"><strong>Auditoria #{round.number} · {round.versionNumber}</strong><span>{round.status === 'approved' ? 'Aprovada ✓' : 'Em andamento'}</span><small>{round.startedAt ? new Date(round.startedAt).toLocaleString('pt-BR') : ''}</small></article>)}</section>
        </aside>
      </div>
      {error && <p className="verbum-audit-message is-error">{error}</p>}
    </section>
    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('versions')} onBackToLibrary={() => onStageChange('versions')} />
  </>;
}
