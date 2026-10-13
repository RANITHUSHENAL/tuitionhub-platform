import React, { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { CheckIcon, EyeIcon, SendIcon } from 'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { Button } from '../../components/ui/Button';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { Toast } from '../../components/ui/States';
import { QuestionEditor } from '../../components/exams/QuestionEditor';
import { QuestionView } from '../../components/exams/QuestionView';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import { examById, questionTypeLabels } from '../../data/exams';
import type { ExamQuestion, QuestionType } from '../../types';

const steps = ['Details', 'Questions', 'Settings', 'Preview'] as const;
type Step = (typeof steps)[number];

function blank(type: QuestionType): ExamQuestion {
  const id = `q-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`;
  if (type === 'truefalse') return { id, type, prompt: '', options: ['True', 'False'], correct: [0], marks: 1 };
  if (type === 'single' || type === 'multiple' || type === 'image')
  return { id, type, prompt: '', options: ['', '', '', ''], correct: [], marks: 2 };
  return { id, type, prompt: '', marks: type === 'essay' ? 5 : 2 };
}

/** Teacher-owned exam authoring. Class choice is limited to the teacher's own classes. */
export function ExamBuilder() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const { myClasses } = useTeacherScope();
  const preset = myClasses.find((c) => c.id === params.get('class'));
  const [step, setStep] = useState<Step>('Details');
  const [title, setTitle] = useState('Physics — Waves Unit Test');
  const [className, setClassName] = useState(preset?.name ?? myClasses[0]?.name ?? '');
  const [questions, setQuestions] = useState<ExamQuestion[]>(() => examById('exm-01')?.questions.slice(0, 3) ?? []);
  const [duration, setDuration] = useState(30);
  const [opens, setOpens] = useState('');
  const [shuffle, setShuffle] = useState(true);
  const [showResult, setShowResult] = useState<'instant' | 'release'>('release');
  const [previewIndex, setPreviewIndex] = useState(0);
  const [published, setPublished] = useState(false);

  const total = questions.reduce((s, q) => s + q.marks, 0);
  const idx = steps.indexOf(step);

  const update = (i: number, q: ExamQuestion) => setQuestions((all) => all.map((x, n) => n === i ? q : x));
  const move = (i: number, dir: -1 | 1) =>
  setQuestions((all) => {
    const next = [...all];
    const [item] = next.splice(i, 1);
    next.splice(i + dir, 0, item);
    return next;
  });

  return (
    <TeacherShell title="Create exam" subtitle={`${questions.length} questions · ${total} marks · ${duration} min`}>
      <ol className="mb-6 flex flex-wrap gap-2" aria-label="Exam builder steps">
        {steps.map((s, i) =>
        <li key={s}>
            <button
            onClick={() => setStep(s)}
            aria-current={s === step ? 'step' : undefined}
            className={`inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-small font-medium transition-colors duration-150 ease-out ${
            s === step ?
            'border-forest bg-forest text-white' :
            i < idx ?
            'border-forest-100 bg-forest-50 text-forest' :
            'border-line bg-white text-ink-soft hover:text-ink'}`
            }>
            
              {i < idx ? <CheckIcon className="h-3.5 w-3.5" /> : <span>{i + 1}</span>}
              {s}
            </button>
          </li>
        )}
      </ol>

      {step === 'Details' ?
      <section className="max-w-2xl space-y-4 rounded-2xl border border-line bg-white p-6 shadow-card">
          <div>
            <Label htmlFor="etitle">Exam title</Label>
            <TextInput id="etitle" value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="eclass" hint="Only your classes · only enrolled students see it">
              Class
            </Label>
            <Select id="eclass" value={className} onChange={(e) => setClassName(e.target.value)}>
              {myClasses.map((c) =>
            <option key={c.id}>{c.name}</option>
            )}
            </Select>
          </div>
          <Button onClick={() => setStep('Questions')}>Continue to questions</Button>
        </section> :
      null}

      {step === 'Questions' ?
      <div className="grid gap-6 lg:grid-cols-[1fr_16rem] lg:items-start">
          <div className="space-y-4">
            {questions.map((q, i) =>
          <QuestionEditor
            key={q.id}
            question={q}
            index={i}
            total={questions.length}
            onChange={(nq) => update(i, nq)}
            onRemove={() => setQuestions((all) => all.filter((_, n) => n !== i))}
            onMove={(d) => move(i, d)} />

          )}
            {questions.length === 0 ?
          <p className="rounded-2xl border border-dashed border-line-strong bg-surface-muted px-5 py-10 text-center text-small text-ink-soft">
                Add your first question from the panel.
              </p> :
          null}
          </div>
          <aside className="space-y-3 rounded-2xl border border-line bg-white p-5 lg:sticky lg:top-24">
            <p className="text-small font-medium text-ink">Add question</p>
            <ul className="space-y-1.5">
              {(Object.keys(questionTypeLabels) as QuestionType[]).map((t) =>
            <li key={t}>
                  <button
                onClick={() => setQuestions((all) => [...all, blank(t)])}
                className="w-full rounded-xl border border-line px-3 py-2 text-left transition-colors duration-150 ease-out hover:border-forest">
                
                    <span className="block text-small font-medium text-ink">{questionTypeLabels[t].label}</span>
                    <span className="block text-caption text-ink-soft">{questionTypeLabels[t].hint}</span>
                  </button>
                </li>
            )}
            </ul>
            <div className="border-t border-line pt-3 text-caption text-ink-soft">
              Total <span className="font-semibold text-ink">{total} marks</span>
            </div>
            <Button className="w-full" disabled={!questions.length} onClick={() => setStep('Settings')}>
              Continue
            </Button>
          </aside>
        </div> :
      null}

      {step === 'Settings' ?
      <section className="max-w-2xl space-y-5 rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="edur">Duration (minutes)</Label>
              <TextInput id="edur" type="number" min={5} value={duration} onChange={(e) => setDuration(Number(e.target.value))} />
            </div>
            <div>
              <Label htmlFor="eopen">Opens</Label>
              <TextInput id="eopen" type="datetime-local" value={opens} onChange={(e) => setOpens(e.target.value)} />
            </div>
          </div>
          <label className="flex items-center justify-between gap-4 rounded-xl border border-line px-4 py-3">
            <span>
              <span className="block text-small font-medium text-ink">Shuffle question order</span>
              <span className="block text-caption text-ink-soft">Each student sees a different order</span>
            </span>
            <input type="checkbox" checked={shuffle} onChange={(e) => setShuffle(e.target.checked)} className="h-4 w-4 accent-[#13402C]" />
          </label>
          <fieldset>
            <legend className="mb-2 text-small font-medium text-ink">When do students and guardians see results?</legend>
            <div className="grid gap-2 sm:grid-cols-2">
              {(
            [
            ['instant', 'Instantly', 'Objective marks on submit'],
            ['release', 'When I publish', 'After I mark written answers']] as
            const).
            map(([v, l, d]) =>
            <button
              key={v}
              type="button"
              role="radio"
              aria-checked={showResult === v}
              onClick={() => setShowResult(v)}
              className={`rounded-xl border px-4 py-3 text-left transition-colors duration-150 ease-out ${
              showResult === v ? 'border-forest bg-forest-50' : 'border-line hover:border-line-strong'}`
              }>
              
                  <span className="block text-small font-medium text-ink">{l}</span>
                  <span className="block text-caption text-ink-soft">{d}</span>
                </button>
            )}
            </div>
          </fieldset>
          <Button onClick={() => setStep('Preview')}>
            <EyeIcon className="h-4 w-4" />
            Preview as a student
          </Button>
        </section> :
      null}

      {step === 'Preview' ?
      <div className="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card lg:p-8">
            <p className="mb-5 text-caption text-ink-faint">Student view · answers are not saved</p>
            {questions[previewIndex] ?
          <QuestionView question={questions[previewIndex]} index={previewIndex} onAnswer={() => undefined} /> :
          null}
            <div className="mt-6 flex justify-between border-t border-line pt-4">
              <Button variant="ghost" size="sm" disabled={previewIndex === 0} onClick={() => setPreviewIndex((i) => i - 1)}>
                Previous
              </Button>
              <Button
              variant="secondary"
              size="sm"
              disabled={previewIndex >= questions.length - 1}
              onClick={() => setPreviewIndex((i) => i + 1)}>
              
                Next
              </Button>
            </div>
          </section>
          <aside className="space-y-3 rounded-2xl border border-line bg-white p-5 lg:sticky lg:top-24">
            <p className="text-h3 text-ink">{title}</p>
            <dl className="space-y-1.5 text-small">
              <Row k="Class" v={className} />
              <Row k="Questions" v={`${questions.length}`} />
              <Row k="Marks" v={`${total}`} />
              <Row k="Duration" v={`${duration} min`} />
              <Row k="Results" v={showResult === 'instant' ? 'Instantly' : 'When you publish'} />
            </dl>
            <Button
            className="w-full"
            onClick={() => {
              setPublished(true);
              window.setTimeout(() => navigate('/teacher/exams'), 1400);
            }}>
            
              <SendIcon className="h-4 w-4" />
              Publish to class
            </Button>
            <p className="text-caption text-ink-faint">Enrolled students get an app notification and SMS.</p>
          </aside>
        </div> :
      null}

      <Toast
        open={published}
        message="Exam published"
        detail={`${className} students can see it${opens ? ' when it opens' : ' now'}.`}
        onClose={() => setPublished(false)} />
      
    </TeacherShell>);

}

function Row({ k, v }: {k: string;v: string;}) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-ink-soft">{k}</dt>
      <dd className="truncate text-right font-medium text-ink">{v}</dd>
    </div>);

}