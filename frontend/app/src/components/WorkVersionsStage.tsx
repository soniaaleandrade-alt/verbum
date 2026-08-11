import { useEffect, useMemo, useState } from 'react';
import {
  compareWorkVersions,
  completeWorkVersions,
  createWorkVersion,
  deleteWorkVersion,
  duplicateWorkVersion,
  getWorkVersion,
  getWorkVersions,
  restoreWorkVersion,
  saveWorkVersionsState,
  selectAuditWorkVersion,
  updateWorkVersion,
} from '../services/work-versions-service';
import type { WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import type { CreateWorkVersionInput, WorkVersionCompareResponse, WorkVersionDetailResponse, WorkVersionSummary, WorkVersionsFlags, WorkVersionsStage } from '../types/work-versions';
import { WorkspaceFooter } from './WorkspaceFooter';

type Props = {
  workspace: WorkWorkspaceData;
  onWorkspaceChange: (workspace: WorkWorkspaceData) => void;
  onStageChange: (stage: WorkStageKey) => void;
  onPersisted: () => void | Promise<void>;
};

const emptyCreate: CreateWorkVersionInput = { name: '', type: 'milestone', notes: '', protected: false, major: false };

export function WorkVersionsStage({ workspace, onWorkspaceChange, onStageChange, onPersisted }: Props) {
  const [data, setData] = useState<WorkVersionsStage | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [filter, setFilter] = useState('all');
  const [search, setSearch] = useState('');
  const [createOpen, setCreateOpen] = useState(false);
  const [createForm, setCreateForm] = useState<CreateWorkVersionInput>(emptyCreate);
  const [detail, setDetail] = useState<WorkVersionDetailResponse | null>(null);
  const [comparison, setComparison] = useState<WorkVersionCompareResponse | null>(null);
  const [compareFrom, setCompareFrom] = useState('');
  const [compareTo, setCompareTo] = useState('');

  useEffect(() => {
    let alive = true;
    setError('');
    getWorkVersions(workspace.book.id).then((result) => {
      if (!alive) return;
      setData(result);
      if (result.versions.length >= 2) {
        setCompareFrom(result.versions[result.versions.length - 1].id);
        setCompareTo(result.versions[0].id);
      }
    }).catch((cause) => alive && setError(cause instanceof Error ? cause.message : 'Não foi possível carregar o Controle de Versões.'));
    return () => { alive = false; };
  }, [workspace.book.id]);

  const visibleVersions = useMemo(() => {
    if (!data) return [];
    const term = search.trim().toLocaleLowerCase('pt-BR');
    return data.versions.filter((version) => {
      const matchesSearch = !term || `${version.number} ${version.name} ${version.notes} ${version.typeLabel}`.toLocaleLowerCase('pt-BR').includes(term);
      const matchesFilter = filter === 'all'
        || (filter === 'protected' && version.protected)
        || (filter === 'manual' && version.origin === 'manual')
        || (filter === 'automatic' && version.origin !== 'manual')
        || (filter === 'milestone' && version.type === 'milestone')
        || (filter === 'backup' && ['manual_backup', 'before_restore', 'before_change'].includes(version.type));
      return matchesSearch && matchesFilter;
    });
  }, [data, filter, search]);

  async function applyMutation(action: () => Promise<{ versionsStage: WorkVersionsStage; workspace: WorkWorkspaceData }>) {
    if (busy) return;
    setBusy(true); setError('');
    try {
      const result = await action();
      setData(result.versionsStage);
      onWorkspaceChange(result.workspace);
      await onPersisted();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a operação.');
      throw cause;
    } finally { setBusy(false); }
  }

  async function createVersion(force = false) {
    try {
      await applyMutation(() => createWorkVersion(workspace.book.id, { ...createForm, force }));
      setCreateForm(emptyCreate); setCreateOpen(false);
    } catch (cause) {
      const message = cause instanceof Error ? cause.message : '';
      if (!force && message.includes('Não foram encontradas alterações') && window.confirm(`${message}\n\nDeseja criar a versão mesmo assim?`)) void createVersion(true);
    }
  }

  async function openVersion(version: WorkVersionSummary) {
    setBusy(true); setError('');
    try { setDetail(await getWorkVersion(workspace.book.id, version.id)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível abrir a versão.'); }
    finally { setBusy(false); }
  }

  async function runCompare() {
    if (!compareFrom || !compareTo || compareFrom === compareTo) return;
    setBusy(true); setError('');
    try { setComparison(await compareWorkVersions(workspace.book.id, compareFrom, compareTo)); setData(await getWorkVersions(workspace.book.id)); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível comparar as versões.'); }
    finally { setBusy(false); }
  }

  async function toggleFlag(key: keyof WorkVersionsFlags, checked: boolean) {
    if (!data) return;
    const flags = { ...data.flags, [key]: checked };
    setData({ ...data, flags });
    try { await applyMutation(() => saveWorkVersionsState(workspace.book.id, flags)); } catch { /* surfaced above */ }
  }

  if (!data) return <section className="verbum-stage-content"><div className={`verbum-versions-state${error ? ' is-error' : ''}`}>{error || 'Carregando Controle de Versões...'}</div></section>;

  const latest = data.versions.find((version) => version.id === data.latestVersionId);
  return <>
    <section className="verbum-stage-content verbum-versions-stage">
      <header className="verbum-versions-heading">
        <div><span className="verbum-eyebrow">Etapa 6 de 11</span><h2>Controle de Versões</h2><p>Gerencie o histórico editorial e preserve versões importantes da obra.</p></div>
        <button type="button" className="verbum-primary-button" onClick={() => setCreateOpen(true)}>+ Criar versão</button>
      </header>

      <div className="verbum-versions-stats">
        <article><strong>{data.versions.length}</strong><span>Versões salvas</span></article>
        <article><strong>{latest?.number || '—'}</strong><span>Versão atual</span></article>
        <article><strong>{latest ? new Date(latest.createdAt).toLocaleDateString('pt-BR') : '—'}</strong><span>Última versão</span></article>
        <article><strong>{latest?.wordCount.toLocaleString('pt-BR') || 0}</strong><span>Palavras</span></article>
        <article><strong>{latest?.chapterCount || 0}</strong><span>Capítulos</span></article>
      </div>

      <div className={`verbum-versions-unversioned ${data.unversioned.hasChanges ? 'has-changes' : 'is-clean'}`}>
        <div><span>Alterações não versionadas</span><strong>{data.unversioned.hasChanges ? `Há alterações desde ${data.latestVersionNumber}` : `Tudo preservado em ${data.latestVersionNumber}`}</strong><p>{data.unversioned.hasChanges ? `${data.unversioned.changedChapters} capítulo(s) modificados · ${data.unversioned.wordDelta >= 0 ? '+' : ''}${data.unversioned.wordDelta.toLocaleString('pt-BR')} palavras` : 'Nenhuma diferença detectada desde a última versão formal.'}</p></div>
        {data.unversioned.hasChanges && <button type="button" onClick={() => setCreateOpen(true)}>Criar nova versão</button>}
      </div>

      {data.integrityErrors.length > 0 && <div className="verbum-versions-integrity is-error"><strong>Falha de integridade detectada</strong><p>Verifique: {data.integrityErrors.join(', ')}.</p></div>}

      <div className="verbum-versions-layout">
        <main>
          <div className="verbum-versions-toolbar">
            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Pesquisar versões..." />
            <select value={filter} onChange={(event) => setFilter(event.target.value)}><option value="all">Todas</option><option value="milestone">Marcos editoriais</option><option value="backup">Backups</option><option value="automatic">Automáticas</option><option value="manual">Manuais</option><option value="protected">Protegidas</option></select>
          </div>
          <section className="verbum-versions-timeline">
            <div className="verbum-versions-section-head"><div><h3>Histórico da Obra</h3><p>Marcos editoriais e backups preservados como snapshots imutáveis.</p></div></div>
            {visibleVersions.map((version) => <article key={version.id} className={`verbum-version-card${version.auditBaseline ? ' is-audit' : ''}`}>
              <div className="verbum-version-marker"><span /></div>
              <div className="verbum-version-card-main">
                <div className="verbum-version-title"><div><strong>{version.number}</strong><h4>{version.name}</h4></div><div>{version.protected && <span className="is-protected">Protegida</span>}{version.auditBaseline && <span className="is-audit">Versão para Auditoria</span>}</div></div>
                <p>{version.typeLabel} · {new Date(version.createdAt).toLocaleString('pt-BR')} · {version.wordCount.toLocaleString('pt-BR')} palavras · {version.chapterCount} capítulos</p>
                {version.notes && <blockquote>{version.notes}</blockquote>}
                <div className="verbum-version-actions"><button type="button" onClick={() => void openVersion(version)}>Visualizar</button><button type="button" onClick={() => { setCompareFrom(data.versions[data.versions.length - 1]?.id || version.id); setCompareTo(version.id); }}>Comparar</button><button type="button" onClick={() => void applyMutation(() => selectAuditWorkVersion(workspace.book.id, version.id))}>Usar na Auditoria</button><button type="button" onClick={() => void applyMutation(() => updateWorkVersion(workspace.book.id, version.id, { protected: !version.protected }))}>{version.protected ? 'Desproteger' : 'Proteger'}</button><button type="button" onClick={() => void applyMutation(() => duplicateWorkVersion(workspace.book.id, version.id))}>Duplicar</button><button type="button" className="is-danger" disabled={version.protected || version.auditBaseline} onClick={() => window.confirm(`Excluir apenas o snapshot ${version.number}?`) && void applyMutation(() => deleteWorkVersion(workspace.book.id, version.id))}>Excluir</button></div>
              </div>
            </article>)}
          </section>

          <section className="verbum-versions-compare-panel">
            <div className="verbum-versions-section-head"><div><h3>Comparar versões</h3><p>Compare estrutura, capítulos, palavras e diferenças de parágrafos.</p></div></div>
            <div className="verbum-versions-compare-controls"><select value={compareFrom} onChange={(event) => setCompareFrom(event.target.value)}>{data.versions.slice().reverse().map((version) => <option key={version.id} value={version.id}>{version.number} — {version.name}</option>)}</select><span>→</span><select value={compareTo} onChange={(event) => setCompareTo(event.target.value)}>{data.versions.slice().reverse().map((version) => <option key={version.id} value={version.id}>{version.number} — {version.name}</option>)}</select><button type="button" disabled={compareFrom === compareTo || busy} onClick={() => void runCompare()}>Comparar</button></div>
            {comparison && <div className="verbum-versions-comparison"><header><strong>{comparison.from.number} → {comparison.to.number}</strong><span>{comparison.comparison.summary.changedChapters} alterados · {comparison.comparison.summary.addedChapters} adicionados · {comparison.comparison.summary.removedChapters} removidos · {comparison.comparison.summary.wordDelta >= 0 ? '+' : ''}{comparison.comparison.summary.wordDelta.toLocaleString('pt-BR')} palavras</span></header>{comparison.comparison.chapters.map((chapter) => <details key={chapter.id} className={`is-${chapter.status}`}><summary><strong>{chapter.title}</strong><span>{chapter.status === 'unchanged' ? 'Sem alteração' : chapter.status === 'added' ? 'Adicionado' : chapter.status === 'removed' ? 'Removido' : 'Alterado'}</span></summary>{chapter.previousTitle && chapter.previousTitle !== chapter.title && <p>Renomeado de: {chapter.previousTitle}</p>}{chapter.paragraphs.added.length > 0 && <div className="diff-added"><b>Adicionado</b>{chapter.paragraphs.added.map((text, index) => <p key={index}>{text}</p>)}</div>}{chapter.paragraphs.removed.length > 0 && <div className="diff-removed"><b>Removido</b>{chapter.paragraphs.removed.map((text, index) => <p key={index}>{text}</p>)}</div>}</details>)}</div>}
          </section>
        </main>

        <aside className="verbum-versions-progress-card">
          <div className="verbum-versions-section-head"><h3>Progresso</h3><strong>{data.progress}%</strong></div><div className="verbum-versions-progress"><span style={{ width: `${data.progress}%` }} /></div>
          {data.checklist.map((item) => {
            const manual = !item.automatic && item.key !== 'completed';
            const checked = manual ? Boolean(data.flags[item.key as keyof WorkVersionsFlags]) : item.completed;
            return <label key={item.key} className={checked ? 'is-complete' : ''}><input type="checkbox" checked={checked} disabled={!manual || busy} onChange={(event) => manual && void toggleFlag(item.key as keyof WorkVersionsFlags, event.target.checked)} /><span>{item.label}</span></label>;
          })}
          <button type="button" className="verbum-primary-button" disabled={!data.ready || data.completed || busy} onClick={() => void applyMutation(() => completeWorkVersions(workspace.book.id)).then(() => onStageChange('audit'))}>{data.completed ? 'Etapa concluída ✓' : 'Concluir Controle de Versões ›'}</button>
          {!data.auditBaselineId && <small>Selecione uma versão atual como “Versão para Auditoria”.</small>}
        </aside>
      </div>

      {error && <p className="verbum-versions-message is-error">{error}</p>}
    </section>

    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('general_review')} onBackToLibrary={() => onStageChange('general_review')} />

    {createOpen && <div className="verbum-versions-modal-backdrop" role="presentation" onMouseDown={() => !busy && setCreateOpen(false)}><div className="verbum-versions-modal" role="dialog" aria-modal="true" onMouseDown={(event) => event.stopPropagation()}><header><div><span className="verbum-eyebrow">Novo marco editorial</span><h3>Criar versão</h3></div><button type="button" onClick={() => setCreateOpen(false)}>×</button></header><label>Nome da versão<input value={createForm.name} onChange={(event) => setCreateForm({ ...createForm, name: event.target.value })} placeholder="Ex.: Ajustes após leitura crítica" /></label><label>Tipo<select value={createForm.type} onChange={(event) => setCreateForm({ ...createForm, type: event.target.value as CreateWorkVersionInput['type'] })}>{data.types.map((type) => <option key={type.key} value={type.key}>{type.label}</option>)}</select></label><label>Notas da versão<textarea value={createForm.notes} onChange={(event) => setCreateForm({ ...createForm, notes: event.target.value })} placeholder="Descreva as principais alterações desta versão..." /></label><label className="is-inline"><input type="checkbox" checked={createForm.protected} onChange={(event) => setCreateForm({ ...createForm, protected: event.target.checked })} /> Proteger contra exclusão</label><label className="is-inline"><input type="checkbox" checked={createForm.major} onChange={(event) => setCreateForm({ ...createForm, major: event.target.checked })} /> Nova versão principal (ex.: v2.0)</label><footer><button type="button" onClick={() => setCreateOpen(false)}>Cancelar</button><button type="button" className="verbum-primary-button" disabled={busy} onClick={() => void createVersion()}>{busy ? 'Criando...' : 'Criar versão'}</button></footer></div></div>}

    {detail && <div className="verbum-versions-modal-backdrop" role="presentation" onMouseDown={() => setDetail(null)}><div className="verbum-version-reader" role="dialog" aria-modal="true" onMouseDown={(event) => event.stopPropagation()}><header><div><span>Você está visualizando uma versão histórica.</span><h3>{detail.version.number} — {detail.version.name}</h3><p>{detail.version.wordCount.toLocaleString('pt-BR')} palavras · {detail.version.chapterCount} capítulos</p></div><button type="button" onClick={() => setDetail(null)}>×</button></header><div className="verbum-version-reader-body"><nav>{detail.snapshot.chapters.map((chapter) => <a key={chapter.id} href={`#version-chapter-${chapter.id}`}>Cap. {chapter.number} — {chapter.title}</a>)}</nav><main>{detail.snapshot.frontMatter.preface && <section><h2>Prefácio</h2><div dangerouslySetInnerHTML={{ __html: detail.snapshot.frontMatter.preface }} /></section>}{detail.snapshot.frontMatter.presentation && <section><h2>Apresentação</h2><div dangerouslySetInnerHTML={{ __html: detail.snapshot.frontMatter.presentation }} /></section>}{detail.snapshot.frontMatter.introduction && <section><h2>Introdução</h2><div dangerouslySetInnerHTML={{ __html: detail.snapshot.frontMatter.introduction }} /></section>}{detail.snapshot.chapters.map((chapter) => <section id={`version-chapter-${chapter.id}`} key={chapter.id}><h2>Capítulo {chapter.number}</h2><h3>{chapter.title}</h3><div dangerouslySetInnerHTML={{ __html: chapter.content }} /></section>)}{detail.snapshot.frontMatter.conclusion && <section><h2>Conclusão</h2><div dangerouslySetInnerHTML={{ __html: detail.snapshot.frontMatter.conclusion }} /></section>}</main></div><footer><button type="button" onClick={() => void applyMutation(() => duplicateWorkVersion(workspace.book.id, detail.version.id)).then(() => setDetail(null))}>Criar nova versão a partir desta</button><button type="button" className="is-danger" onClick={() => window.confirm(`Restaurar a obra para ${detail.version.number}? O estado atual será preservado automaticamente antes da restauração.`) && void applyMutation(() => restoreWorkVersion(workspace.book.id, detail.version.id)).then(() => setDetail(null))}>Restaurar esta versão</button></footer></div></div>}
  </>;
}
