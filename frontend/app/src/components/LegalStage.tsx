import { useEffect, useState } from 'react';
import {
  assistLegalStage, completeLegalStage, createLegalDocument, createLegalIssue, createThirdPartyItem,
  deleteLegalDocument, deleteLegalIssue, deleteThirdPartyItem, getLegalStage, registerLegalProof,
  saveLegalStage, updateLegalDocument, updateLegalIssue, updateThirdPartyItem,
} from '../services/legal-stage-service';
import type { LegalMutationResponse, LegalStage as LegalStageData, LegalState } from '../types/legal-stage';
import type { WorkStageKey, WorkWorkspaceData } from '../types/verbum';
import { WorkspaceFooter } from './WorkspaceFooter';

type Props = { workspace: WorkWorkspaceData; onWorkspaceChange: (workspace: WorkWorkspaceData) => void; onStageChange: (stage: WorkStageKey) => void; onPersisted: () => void | Promise<void> };
const text = (value: unknown) => typeof value === 'string' ? value : value == null ? '' : String(value);

export function LegalStage({ workspace, onWorkspaceChange, onStageChange, onPersisted }: Props) {
  const [data, setData] = useState<LegalStageData | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [dirty, setDirty] = useState(false);
  const [assistant, setAssistant] = useState('');
  const [docName, setDocName] = useState('');
  const [docCategory, setDocCategory] = useState('other');
  const [thirdDescription, setThirdDescription] = useState('');
  const [thirdRequired, setThirdRequired] = useState(false);
  const [issueText, setIssueText] = useState('');
  const [issueType, setIssueType] = useState('other');
  const [issuePriority, setIssuePriority] = useState('medium');
  const [proofUrl, setProofUrl] = useState('');

  useEffect(() => { let alive = true; getLegalStage(workspace.book.id).then((result) => alive && setData(result)).catch((cause) => alive && setError(cause instanceof Error ? cause.message : 'Não foi possível carregar os Trâmites Legais.')); return () => { alive = false; }; }, [workspace.book.id]);

  async function mutate(action: () => Promise<LegalMutationResponse>) {
    if (busy) return; setBusy(true); setError('');
    try { const result = await action(); setData(result.legalStage); setDirty(false); onWorkspaceChange(result.workspace); await onPersisted(); }
    catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível concluir a operação.'); }
    finally { setBusy(false); }
  }
  function state(update: (current: LegalState) => LegalState) { if (!data || data.completed) return; setData({ ...data, state: update(data.state) }); setDirty(true); }
  function setSection(section: keyof LegalState, key: string, value: unknown) { state((current) => ({ ...current, [section]: { ...(current[section] as Record<string, unknown>), [key]: value } } as LegalState)); }
  async function save() { if (!data) return; await mutate(() => saveLegalStage(workspace.book.id, data.state, data.flags, data.finalConfirmation)); }
  async function assist(action: string) { setBusy(true); setError(''); try { const result = await assistLegalStage(workspace.book.id, action); setAssistant(result.suggestion); } catch (cause) { setError(cause instanceof Error ? cause.message : 'Não foi possível usar o Assistente Legal-Editorial.'); } finally { setBusy(false); } }

  if (!data) return <section className="verbum-stage-content"><div className={`verbum-legal-state${error ? ' is-error' : ''}`}>{error || 'Carregando Trâmites Legais da Obra...'}</div></section>;
  const id = data.state.identity;
  const cat = data.state.cataloging;
  const rights = data.state.copyright;
  const registration = data.state.authorRegistration;
  const credits = data.state.credits;
  const files = data.state.finalFiles;
  const technical = data.state.technical;

  return <>
    <section className="verbum-stage-content verbum-legal-stage">
      <header className="verbum-legal-heading"><div><span className="verbum-eyebrow">Etapa 10 de 11</span><h2>Trâmites Legais da Obra</h2><p>Centralize registros, documentos, autorizações e arquivos finais antes da publicação.</p></div><div className="verbum-legal-baseline"><small>Edição em regularização</small><strong>{data.version.number} — {data.version.name}</strong><span>{data.layout.format} · {data.layout.pageCount} páginas · {text(id.edition)} · {text(id.year)}</span><code>{data.version.hash.slice(0, 18)}…</code></div></header>
      {!data.baselineValid && <div className="verbum-legal-warning"><strong>A baseline da Diagramação não está mais válida.</strong><p>Confira a versão antes de continuar os Trâmites Legais.</p></div>}
      {data.alerts.map((alert) => <div className="verbum-legal-alert" key={alert}>{alert}</div>)}
      {error && <div className="verbum-legal-state is-error">{error}</div>}
      <div className="verbum-legal-stats"><article><strong>{data.documents.length}</strong><span>Documentos</span></article><article><strong>{data.openIssueCount}</strong><span>Pendências</span></article><article><strong>{data.blockingIssueCount}</strong><span>Bloqueantes</span></article><article><strong>{data.authorizationBlockers}</strong><span>Autorizações</span></article><article><strong>{data.proofs.length}</strong><span>Provas legais</span></article><article><strong>{data.progress}%</strong><span>Progresso</span></article></div>

      <div className="verbum-legal-grid"><main>
        <Card title="Identificação legal da edição" subtitle="Dados herdados das etapas editoriais e da Diagramação.">
          <div className="verbum-legal-readonly"><Field label="Título" value={id.title} /><Field label="Subtítulo" value={id.subtitle} /><Field label="Autor" value={id.author} /><Field label="Edição" value={id.edition} /><Field label="Ano" value={id.year} /><Field label="Formato" value={id.format} /><Field label="Páginas" value={id.pageCount} /><Field label="Idioma" value={id.language} /></div>
        </Card>

        <Card title="ISBN por formato" subtitle="A validação local verifica apenas a estrutura do número informado, não sua emissão oficial.">
          <div className="verbum-legal-isbn">{Object.entries(data.state.isbn).map(([key, record]) => <article key={key}><header><strong>{record.label}</strong><span className={record.structureValid ? 'is-ok' : ''}>{record.number ? (record.structureValid ? 'Estrutura válida' : 'Conferir estrutura') : 'Sem número'}</span></header><div className="verbum-legal-form cols-2"><label>Status<select disabled={data.completed} value={record.status} onChange={(e) => state((current) => ({ ...current, isbn: { ...current.isbn, [key]: { ...current.isbn[key], status: e.target.value } } }))}>{data.processStatuses.map((option) => <option value={option.key} key={option.key}>{option.label}</option>)}</select></label><Input label="ISBN" disabled={data.completed} value={record.number} onChange={(value) => state((current) => ({ ...current, isbn: { ...current.isbn, [key]: { ...current.isbn[key], number: value } } }))} /><Input label="Solicitado em" type="date" disabled={data.completed} value={record.requestedAt} onChange={(value) => state((current) => ({ ...current, isbn: { ...current.isbn, [key]: { ...current.isbn[key], requestedAt: value } } }))} /><Input label="Emitido em" type="date" disabled={data.completed} value={record.issuedAt} onChange={(value) => state((current) => ({ ...current, isbn: { ...current.isbn, [key]: { ...current.isbn[key], issuedAt: value } } }))} /><Input label="Instituição emissora" disabled={data.completed} value={record.issuer} onChange={(value) => state((current) => ({ ...current, isbn: { ...current.isbn, [key]: { ...current.isbn[key], issuer: value } } }))} /></div></article>)}</div>
        </Card>

        <Card title="Ficha Catalográfica" subtitle="Registre o acompanhamento e a inserção da ficha no arquivo da edição.">
          <div className="verbum-legal-form cols-2"><Select label="Status" disabled={data.completed} value={cat.status} options={data.processStatuses} onChange={(value) => setSection('cataloging', 'status', value)} /><Input label="Profissional responsável" disabled={data.completed} value={cat.professional} onChange={(value) => setSection('cataloging', 'professional', value)} /><Input label="Registro profissional" disabled={data.completed} value={cat.professionalRegistration} onChange={(value) => setSection('cataloging', 'professionalRegistration', value)} /><Input label="Solicitada em" type="date" disabled={data.completed} value={cat.requestedAt} onChange={(value) => setSection('cataloging', 'requestedAt', value)} /><Input label="Recebida em" type="date" disabled={data.completed} value={cat.receivedAt} onChange={(value) => setSection('cataloging', 'receivedAt', value)} /><Input label="Arquivo / URL protegida" disabled={data.completed} value={cat.fileUrl} onChange={(value) => setSection('cataloging', 'fileUrl', value)} /></div><Toggle checked={cat.insertedInLayout} disabled={data.completed} label="Ficha inserida no arquivo diagramado" onChange={(value) => setSection('cataloging', 'insertedInLayout', value)} />
        </Card>

        <Card title="Direitos Autorais e Registro" subtitle="Organização editorial; esta área não substitui orientação jurídica.">
          <div className="verbum-legal-form cols-2"><Input label="Titular dos direitos" disabled={data.completed} value={text(rights.rightsHolder)} onChange={(value) => setSection('copyright', 'rightsHolder', value)} /><Input label="Ano" disabled={data.completed} value={text(rights.year)} onChange={(value) => setSection('copyright', 'year', value)} /><Input label="Texto de copyright" disabled={data.completed} value={text(rights.notice)} onChange={(value) => setSection('copyright', 'notice', value)} /><Input label="Licença / reserva" disabled={data.completed} value={text(rights.licenseChoice)} onChange={(value) => setSection('copyright', 'licenseChoice', value)} /></div><div className="verbum-legal-form cols-3"><Select label="Registro da obra" disabled={data.completed} value={text(registration.status)} options={data.processStatuses} onChange={(value) => setSection('authorRegistration', 'status', value)} /><Input label="Instituição" disabled={data.completed} value={text(registration.institution)} onChange={(value) => setSection('authorRegistration', 'institution', value)} /><Input label="Número / protocolo" disabled={data.completed} value={text(registration.number) || text(registration.protocol)} onChange={(value) => setSection('authorRegistration', 'number', value)} /></div>
        </Card>

        <Card title="Conteúdos de terceiros e autorizações" subtitle="Itens que exigem autorização permanecem bloqueantes até serem resolvidos.">
          {!data.completed && <div className="verbum-legal-new"><input placeholder="Descrição do material" value={thirdDescription} onChange={(e) => setThirdDescription(e.target.value)} /><label><input type="checkbox" checked={thirdRequired} onChange={(e) => setThirdRequired(e.target.checked)} /> Autorização necessária</label><button disabled={busy || !thirdDescription.trim()} onClick={() => void mutate(() => createThirdPartyItem(workspace.book.id, { description: thirdDescription, authorization_required: thirdRequired }).then((result) => { setThirdDescription(''); setThirdRequired(false); return result; }))}>Adicionar</button></div>}
          <div className="verbum-legal-list">{data.thirdParty.length ? data.thirdParty.map((item) => <article key={item.id}><div><strong>{item.description}</strong><span>{item.authorizationRequired ? 'Autorização necessária' : 'Sem autorização marcada'}</span></div><select disabled={data.completed || busy} value={item.status} onChange={(e) => void mutate(() => updateThirdPartyItem(workspace.book.id, item.id, { status: e.target.value }))}>{data.thirdPartyStatuses.map((option) => <option value={option.key} key={option.key}>{option.label}</option>)}</select>{!data.completed && <button className="is-danger" onClick={() => void mutate(() => deleteThirdPartyItem(workspace.book.id, item.id))}>Excluir</button>}</article>) : <p>Nenhum conteúdo de terceiro registrado.</p>}</div>
        </Card>

        <Card title="Créditos editoriais" subtitle="Dados estruturados para expediente e página de créditos.">
          <div className="verbum-legal-form cols-2">{[['author','Autor'],['revision','Revisão'],['editorialPreparation','Preparação editorial'],['cover','Capa'],['illustration','Ilustração'],['layout','Diagramação'],['cataloging','Ficha catalográfica'],['publisher','Editora'],['printing','Impressão'],['other','Outros créditos']].map(([key,label]) => <Input key={key} label={label} disabled={data.completed} value={text(credits[key])} onChange={(value) => setSection('credits', key, value)} />)}</div>
        </Card>

        <Card title="Documentos" subtitle="Central privada por conta e obra; registre referências ou URLs protegidas.">
          {!data.completed && <div className="verbum-legal-new"><input placeholder="Nome do documento" value={docName} onChange={(e) => setDocName(e.target.value)} /><select value={docCategory} onChange={(e) => setDocCategory(e.target.value)}>{data.documentCategories.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}</select><button disabled={busy || !docName.trim()} onClick={() => void mutate(() => createLegalDocument(workspace.book.id, { name: docName, category: docCategory }).then((result) => { setDocName(''); return result; }))}>Adicionar</button></div>}
          <div className="verbum-legal-list">{data.documents.length ? data.documents.map((doc) => <article key={doc.id}><div><strong>{doc.name}</strong><span>{doc.categoryLabel} · {doc.statusLabel}</span></div><select disabled={data.completed || busy} value={doc.status} onChange={(e) => void mutate(() => updateLegalDocument(workspace.book.id, doc.id, { status: e.target.value }))}>{data.processStatuses.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}</select>{!data.completed && <button className="is-danger" onClick={() => void mutate(() => deleteLegalDocument(workspace.book.id, doc.id))}>Excluir</button>}</article>) : <p>Nenhum documento cadastrado.</p>}</div>
        </Card>

        <Card title="Arquivos finais e dados técnicos" subtitle="Selecione o arquivo que seguirá para a Publicação.">
          <div className="verbum-legal-form cols-2"><Input label="PDF do miolo" disabled={data.completed} value={files.printInteriorUrl} onChange={(value) => setSection('finalFiles', 'printInteriorUrl', value)} /><Input label="Arquivo da capa" disabled={data.completed} value={files.coverUrl} onChange={(value) => setSection('finalFiles', 'coverUrl', value)} /><Input label="Arquivo digital" disabled={data.completed} value={files.digitalFileUrl} onChange={(value) => setSection('finalFiles', 'digitalFileUrl', value)} /><Input label="Arquivo selecionado para Publicação" disabled={data.completed} value={files.selectedFileUrl} onChange={(value) => setSection('finalFiles', 'selectedFileUrl', value)} /><Input label="Gráfica" disabled={data.completed} value={text(technical.printer)} onChange={(value) => setSection('technical', 'printer', value)} /><Input label="Papel do miolo" disabled={data.completed} value={text(technical.interiorPaper)} onChange={(value) => setSection('technical', 'interiorPaper', value)} /><Input label="Acabamento" disabled={data.completed} value={text(technical.finish)} onChange={(value) => setSection('technical', 'finish', value)} /><Input label="Encadernação" disabled={data.completed} value={text(technical.binding)} onChange={(value) => setSection('technical', 'binding', value)} /></div>
          {!data.completed && <div className="verbum-legal-proof"><input placeholder="URL / referência da Prova Legal Final" value={proofUrl} onChange={(e) => setProofUrl(e.target.value)} /><button disabled={busy || !proofUrl.trim()} onClick={() => void mutate(() => registerLegalProof(workspace.book.id, proofUrl).then((result) => { setProofUrl(''); return result; }))}>Registrar Prova Legal</button></div>}
          {data.proofs.map((proof) => <p key={proof.id}><strong>{proof.label}</strong> · {new Date(proof.createdAt).toLocaleString('pt-BR')}</p>)}
        </Card>

        <Card title="Perfis opcionais" subtitle="Use somente quando forem pertinentes à edição.">
          <div className="verbum-legal-toggles"><Toggle checked={data.state.ecclesial.applicable} disabled={data.completed} label="Acompanhar autorizações eclesiais" onChange={(value) => setSection('ecclesial', 'applicable', value)} /><Toggle checked={data.state.academic.applicable} disabled={data.completed} label="Acompanhar dados acadêmicos" onChange={(value) => setSection('academic', 'applicable', value)} /></div>
          {data.state.ecclesial.applicable && <div className="verbum-legal-form cols-2"><Input label="Responsável / autoridade" disabled={data.completed} value={text(data.state.ecclesial.responsible)} onChange={(value) => setSection('ecclesial', 'responsible', value)} /><Input label="Nihil Obstat" disabled={data.completed} value={text(data.state.ecclesial.nihilObstat)} onChange={(value) => setSection('ecclesial', 'nihilObstat', value)} /><Input label="Imprimatur" disabled={data.completed} value={text(data.state.ecclesial.imprimatur)} onChange={(value) => setSection('ecclesial', 'imprimatur', value)} /></div>}
          {data.state.academic.applicable && <div className="verbum-legal-form cols-2"><Input label="Instituição" disabled={data.completed} value={text(data.state.academic.institution)} onChange={(value) => setSection('academic', 'institution', value)} /><Input label="Programa" disabled={data.completed} value={text(data.state.academic.program)} onChange={(value) => setSection('academic', 'program', value)} /><Input label="Orientador" disabled={data.completed} value={text(data.state.academic.advisor)} onChange={(value) => setSection('academic', 'advisor', value)} /></div>}
        </Card>

        <Card title="Pendências legais" subtitle="Prioridade bloqueante impede a conclusão enquanto estiver aberta.">
          {!data.completed && <div className="verbum-legal-new"><select value={issueType} onChange={(e) => setIssueType(e.target.value)}>{data.issueTypes.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}</select><select value={issuePriority} onChange={(e) => setIssuePriority(e.target.value)}>{data.priorities.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}</select><input placeholder="Descrição da pendência" value={issueText} onChange={(e) => setIssueText(e.target.value)} /><button disabled={busy || !issueText.trim()} onClick={() => void mutate(() => createLegalIssue(workspace.book.id, { type: issueType, priority: issuePriority, description: issueText }).then((result) => { setIssueText(''); return result; }))}>Registrar</button></div>}
          <div className="verbum-legal-list">{data.issues.length ? data.issues.map((issue) => <article className={issue.priority === 'blocking' ? 'is-blocking' : ''} key={issue.id}><div><strong>{issue.typeLabel}</strong><span>{issue.priorityLabel} · {issue.statusLabel}</span><p>{issue.description}</p></div>{issue.status !== 'resolved' && !data.completed && <button onClick={() => void mutate(() => updateLegalIssue(workspace.book.id, issue.id, { status: 'resolved' }))}>Resolver</button>}{!data.completed && <button className="is-danger" onClick={() => void mutate(() => deleteLegalIssue(workspace.book.id, issue.id))}>Excluir</button>}</article>) : <p>Nenhuma pendência registrada.</p>}</div>
        </Card>

        <Card title="Assistente Legal-Editorial" subtitle="Apoio organizacional e editorial; não substitui aconselhamento jurídico.">
          <div className="verbum-legal-actions">{[['checklist','Organizar checklist'],['documents','Organizar documentos'],['credits','Revisar créditos'],['consistency','Conferir consistência'],['third_party','Conferir terceiros'],['next_steps','Sugerir próximos passos']].map(([key,label]) => <button disabled={busy} key={key} onClick={() => void assist(key)}>{label}</button>)}</div>{assistant && <div className="verbum-legal-assistant-result">{assistant}</div>}
        </Card>
      </main>

      <aside>
        <section className="verbum-legal-card"><div className="verbum-legal-card-head"><div><h3>Checklist Legal</h3><p>{data.completedCount}/{data.total} concluídos</p></div><strong>{data.progress}%</strong></div><div className="verbum-legal-progress"><span style={{ width: `${data.progress}%` }} /></div>{data.checklist.map((item) => { const manual = !item.automatic && item.key !== 'completed'; const checked = manual ? !!data.flags[item.key] : item.completed; return <label className={checked ? 'is-complete' : ''} key={item.key}><input type="checkbox" disabled={!manual || data.completed} checked={checked} onChange={(e) => { if (!manual) return; setData({ ...data, flags: { ...data.flags, [item.key]: e.target.checked } }); setDirty(true); }} /> <span>{item.label}</span></label>; })}</section>
        <section className="verbum-legal-card"><h3>Confirmação final</h3><label className="verbum-legal-confirm"><input type="checkbox" disabled={data.completed} checked={data.finalConfirmation} onChange={(e) => { setData({ ...data, finalConfirmation: e.target.checked }); setDirty(true); }} /> Confirmo que revisei os dados e documentos desta edição e que as pendências necessárias foram resolvidas.</label><button className="is-primary" disabled={busy || data.completed || !data.ready} onClick={() => void mutate(() => completeLegalStage(workspace.book.id))}>{data.completed ? 'Trâmites Legais concluídos' : 'Concluir Trâmites Legais'}</button></section>
        <section className="verbum-legal-card"><h3>Histórico</h3><div className="verbum-legal-history">{data.history.slice(0, 8).map((item) => <article key={item.id}><strong>{item.event}</strong><span>{new Date(item.createdAt).toLocaleString('pt-BR')}</span><p>{item.detail}</p></article>)}</div></section>
      </aside></div>
      {!data.completed && <div className="verbum-legal-save"><span>{dirty ? 'Há alterações não salvas.' : 'Dados salvos.'}</span><button disabled={busy || !dirty} onClick={() => void save()}>Salvar agora</button></div>}
    </section>
    <WorkspaceFooter canGoBack onPrevious={() => onStageChange('layout')} onBackToLibrary={() => onStageChange('layout')} />
  </>;
}

function Card({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) { return <section className="verbum-legal-card"><div className="verbum-legal-card-head"><div><h3>{title}</h3><p>{subtitle}</p></div></div>{children}</section>; }
function Field({ label, value }: { label: string; value: unknown }) { return <div><small>{label}</small><strong>{text(value) || '—'}</strong></div>; }
function Input({ label, value, onChange, disabled, type = 'text' }: { label: string; value: unknown; onChange: (value: string) => void; disabled?: boolean; type?: string }) { return <label>{label}<input type={type} disabled={disabled} value={text(value)} onChange={(e) => onChange(e.target.value)} /></label>; }
function Select({ label, value, options, onChange, disabled }: { label: string; value: string; options: Array<{ key: string; label: string }>; onChange: (value: string) => void; disabled?: boolean }) { return <label>{label}<select disabled={disabled} value={value} onChange={(e) => onChange(e.target.value)}>{options.map((option) => <option key={option.key} value={option.key}>{option.label}</option>)}</select></label>; }
function Toggle({ label, checked, onChange, disabled }: { label: string; checked: boolean; onChange: (value: boolean) => void; disabled?: boolean }) { return <label className="verbum-legal-toggle"><input type="checkbox" disabled={disabled} checked={checked} onChange={(e) => onChange(e.target.checked)} /><span>{label}</span></label>; }
