import { useEffect, useState, type FormEvent } from 'react';
import type { CreateBookInput, VerbumBook, VerbumProject } from '../types/verbum';

type BookDialogProps = {
  open: boolean;
  projects: VerbumProject[];
  book?: VerbumBook | null;
  onClose: () => void;
  onSave: (input: CreateBookInput) => Promise<void>;
};

type FormState = {
  projectId: string;
  title: string;
  subtitle: string;
  series: string;
  category: string;
  genre: string;
  audience: string;
  ageRange: string;
  language: string;
  country: string;
  authorName: string;
  coauthorName: string;
  plannedChapters: string;
  wordGoal: string;
  targetDate: string;
  workflowStatus: string;
  tags: string;
  collection: string;
  priority: string;
  color: string;
  notes: string;
};

const emptyForm: FormState = {
  projectId: '',
  title: '',
  subtitle: '',
  series: '',
  category: '',
  genre: '',
  audience: '',
  ageRange: '',
  language: 'Português',
  country: 'Brasil',
  authorName: '',
  coauthorName: '',
  plannedChapters: '',
  wordGoal: '',
  targetDate: '',
  workflowStatus: 'Planejamento',
  tags: '',
  collection: '',
  priority: 'Normal',
  color: '#7a3042',
  notes: '',
};

export function BookDialog({ open, projects, book, onClose, onSave }: BookDialogProps) {
  const [form, setForm] = useState<FormState>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!open) return;
    setForm(book ? {
      projectId: book.projectId,
      title: book.title ?? '',
      subtitle: book.subtitle ?? '',
      series: book.series ?? '',
      category: book.category ?? '',
      genre: book.genre ?? '',
      audience: book.audience ?? '',
      ageRange: book.ageRange ?? '',
      language: book.language ?? 'Português',
      country: book.country ?? 'Brasil',
      authorName: book.authorName ?? '',
      coauthorName: book.coauthorName ?? '',
      plannedChapters: String(book.plannedChapters ?? ''),
      wordGoal: String(book.wordGoal ?? ''),
      targetDate: book.targetDate ?? '',
      workflowStatus: book.workflowStatus ?? 'Planejamento',
      tags: Array.isArray(book.tags) ? book.tags.join(', ') : '',
      collection: book.collection ?? '',
      priority: book.priority ?? 'Normal',
      color: book.color || '#7a3042',
      notes: book.notes ?? '',
    } : {
      ...emptyForm,
      projectId: projects.find((project) => project.status === 'active')?.id ?? '',
    });
    setError('');
  }, [open, book, projects]);

  if (!open) return null;

  function set<K extends keyof FormState>(key: K, value: FormState[K]) {
    setForm((current) => ({ ...current, [key]: value }));
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!form.projectId) {
      setError('Selecione o projeto da obra.');
      return;
    }
    if (!form.title.trim()) {
      setError('Informe o título da obra.');
      return;
    }

    const input: CreateBookInput = {
      project_id: Number(form.projectId),
      title: form.title.trim(),
      subtitle: form.subtitle.trim(),
      series: form.series.trim(),
      category: form.category.trim(),
      genre: form.genre.trim(),
      audience: form.audience.trim(),
      age_range: form.ageRange.trim(),
      language: form.language.trim(),
      country: form.country.trim(),
      author_name: form.authorName.trim(),
      coauthor_name: form.coauthorName.trim(),
      planned_chapters: form.plannedChapters ? Number(form.plannedChapters) : 0,
      word_goal: form.wordGoal ? Number(form.wordGoal) : 0,
      target_date: form.targetDate,
      workflow_status: form.workflowStatus,
      tags: form.tags.split(',').map((tag) => tag.trim()).filter(Boolean),
      collection: form.collection.trim(),
      priority: form.priority,
      color: form.color,
      notes: form.notes.trim(),
    };

    setSaving(true);
    setError('');
    try {
      await onSave(input);
      onClose();
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Não foi possível salvar a obra.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="verbum-dialog-backdrop" role="presentation" onMouseDown={(event) => event.currentTarget === event.target && onClose()}>
      <section className="verbum-dialog verbum-dialog-wide" role="dialog" aria-modal="true" aria-labelledby="verbum-book-dialog-title">
        <div className="verbum-dialog-header">
          <div>
            <span className="verbum-eyebrow">Identificação da Obra</span>
            <h2 id="verbum-book-dialog-title">{book ? 'Editar obra' : 'Criar nova obra'}</h2>
          </div>
          <button type="button" className="verbum-icon-button" onClick={onClose} aria-label="Fechar">×</button>
        </div>

        <form className="verbum-form" onSubmit={submit}>
          <div className="verbum-form-section">
            <div className="verbum-form-section-heading">
              <strong>Informações principais</strong>
              <small>Dados que identificam a obra no seu workspace.</small>
            </div>
            <div className="verbum-form-grid">
              <label className="verbum-field-span-2">
                <span>Projeto *</span>
                <select value={form.projectId} onChange={(event) => set('projectId', event.target.value)}>
                  <option value="">Selecione um projeto</option>
                  {projects.filter((project) => project.status === 'active').map((project) => (
                    <option key={project.id} value={project.id}>{project.name}</option>
                  ))}
                </select>
              </label>
              <label>
                <span>Título da obra *</span>
                <input value={form.title} onChange={(event) => set('title', event.target.value)} maxLength={180} autoFocus />
              </label>
              <label>
                <span>Subtítulo</span>
                <input value={form.subtitle} onChange={(event) => set('subtitle', event.target.value)} maxLength={220} />
              </label>
              <label>
                <span>Série (opcional)</span>
                <input value={form.series} onChange={(event) => set('series', event.target.value)} maxLength={140} />
              </label>
              <label>
                <span>Coleção</span>
                <input value={form.collection} onChange={(event) => set('collection', event.target.value)} maxLength={140} />
              </label>
              <label>
                <span>Categoria</span>
                <input value={form.category} onChange={(event) => set('category', event.target.value)} maxLength={100} />
              </label>
              <label>
                <span>Gênero</span>
                <input value={form.genre} onChange={(event) => set('genre', event.target.value)} maxLength={100} />
              </label>
              <label>
                <span>Público-alvo</span>
                <input value={form.audience} onChange={(event) => set('audience', event.target.value)} maxLength={160} />
              </label>
              <label>
                <span>Faixa etária</span>
                <input value={form.ageRange} onChange={(event) => set('ageRange', event.target.value)} maxLength={80} />
              </label>
              <label>
                <span>Idioma</span>
                <input value={form.language} onChange={(event) => set('language', event.target.value)} maxLength={80} />
              </label>
              <label>
                <span>País</span>
                <input value={form.country} onChange={(event) => set('country', event.target.value)} maxLength={80} />
              </label>
              <label>
                <span>Autor</span>
                <input value={form.authorName} onChange={(event) => set('authorName', event.target.value)} maxLength={140} />
              </label>
              <label>
                <span>Coautor (opcional)</span>
                <input value={form.coauthorName} onChange={(event) => set('coauthorName', event.target.value)} maxLength={140} />
              </label>
            </div>
          </div>

          <div className="verbum-form-section">
            <div className="verbum-form-section-heading">
              <strong>Planejamento inicial</strong>
              <small>Metas gerais; o planejamento editorial detalhado será feito na etapa própria.</small>
            </div>
            <div className="verbum-form-grid">
              <label>
                <span>Capítulos previstos</span>
                <input type="number" min="0" value={form.plannedChapters} onChange={(event) => set('plannedChapters', event.target.value)} />
              </label>
              <label>
                <span>Meta de palavras</span>
                <input type="number" min="0" step="100" value={form.wordGoal} onChange={(event) => set('wordGoal', event.target.value)} />
              </label>
              <label>
                <span>Conclusão prevista</span>
                <input type="date" value={form.targetDate} onChange={(event) => set('targetDate', event.target.value)} />
              </label>
              <label>
                <span>Status</span>
                <select value={form.workflowStatus} onChange={(event) => set('workflowStatus', event.target.value)}>
                  <option>Planejamento</option>
                  <option>Em andamento</option>
                  <option>Em pausa</option>
                  <option>Concluída</option>
                </select>
              </label>
              <label>
                <span>Prioridade</span>
                <select value={form.priority} onChange={(event) => set('priority', event.target.value)}>
                  <option>Baixa</option>
                  <option>Normal</option>
                  <option>Alta</option>
                </select>
              </label>
              <label>
                <span>Cor da obra</span>
                <input type="color" value={form.color} onChange={(event) => set('color', event.target.value)} />
              </label>
              <label className="verbum-field-span-2">
                <span>Tags</span>
                <input value={form.tags} onChange={(event) => set('tags', event.target.value)} placeholder="ex.: espiritualidade, sacerdócio, formação" />
              </label>
              <label className="verbum-field-span-2">
                <span>Anotações gerais</span>
                <textarea value={form.notes} onChange={(event) => set('notes', event.target.value)} rows={4} maxLength={3000} />
              </label>
            </div>
          </div>

          {error && <p className="verbum-form-error" role="alert">{error}</p>}
          <div className="verbum-dialog-actions">
            <button type="button" className="verbum-secondary-button" onClick={onClose} disabled={saving}>Cancelar</button>
            <button type="submit" className="verbum-primary-button" disabled={saving}>{saving ? 'Salvando...' : 'Salvar obra'}</button>
          </div>
        </form>
      </section>
    </div>
  );
}
