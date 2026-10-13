import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { CheckCircle2Icon, ChevronLeftIcon, ChevronRightIcon, ClockIcon, FlagIcon } from 'lucide-react';
import { Button, LinkButton } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import { Modal } from '../../components/ui/DetailPanel';
import { EmptyState } from '../../components/ui/States';
import { QuestionView, type Answer } from '../../components/exams/QuestionView';
import { examById } from '../../data/exams';
import type { ExamQuestion } from '../../types';

type Stage = 'instructions' | 'active' | 'result';

function autoMark(q: ExamQuestion, a?: Answer): number | null {
  if (q.type === 'essay') return null;
  if (a === undefined) return 0;
  if (q.correct && Array.isArray(a)) {
    const ok = q.correct.length === a.length && q.correct.every((c) => a.includes(c));
    return ok ? q.marks : 0;
  }
  if (typeof a === 'string' && q.answerText) {
    if (q.type === 'numerical') return Math.abs(Number(a) - Number(q.answerText)) <= 1 ? q.marks : 0;
    return a.trim().toLowerCase() === q.answerText.toLowerCase() ? q.marks : 0;
  }
  return 0;
}

/** Full-screen exam — intentionally outside the app shell to remove distraction. */
export function ExamTake() {
  const { id = '' } = useParams();
  const exam = examById(id);
  const [stage, setStage] = useState<Stage>('instructions');
  const [current, setCurrent] = useState(0);
  const [answers, setAnswers] = useState<Record<string, Answer>>({});
  const [flags, setFlags] = useState<string[]>([]);
  const [confirm, setConfirm] = useState(false);
  const [secondsLeft, setSecondsLeft] = useState((exam?.durationMins ?? 0) * 60);

  useEffect(() => {
    if (stage !== 'active') return;
    if (secondsLeft <= 0) {
      setStage('result');
      return;
    }
    const t = window.setTimeout(() => setSecondsLeft((s) => s - 1), 1000);
    return () => window.clearTimeout(t);
  }, [stage, secondsLeft]);

  const questions = exam?.questions ?? [];
  const answered = (q: ExamQuestion) => {
    const a = answers[q.id];
    return Array.isArray(a) ? a.length > 0 : !!a && a.trim().length > 0;
  };
  const unanswered = questions.filter((q) => !answered(q)).length;

  const result = useMemo(() => {
    let auto = 0;
    let autoMax = 0;
    let pending = 0;
    questions.forEach((q) => {
      const m = autoMark(q, answers[q.id]);
      if (m === null) pending += q.marks;else
      {
        auto += m;
        autoMax += q.marks;
      }
    });
    return { auto, autoMax, pending };
  }, [questions, answers]);

  if (!exam || questions.length === 0) {
    return (
      <div className="min-h-screen bg-canvas px-5 py-20">
        <div className="mx-auto max-w-lg">
          <EmptyState
            icon={<ClockIcon className="h-5 w-5" />}
            title="This exam isn't open"
            body="It may not have started yet, or the window has closed."
            action={<LinkButton to="/student/exams">Back to exams</LinkButton>} />
          
        </div>
      </div>);

  }

  const q = questions[current];
  const mm = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
  const ss = String(secondsLeft % 60).padStart(2, '0');
  const low = secondsLeft < 300;

  return (
    <div className="min-h-screen bg-canvas">
      <header className="sticky top-0 z-20 border-b border-line bg-white">
        <div className="mx-auto flex h-16 max-w-[1100px] items-center justify-between gap-4 px-5">
          <div className="min-w-0">
            <p className="truncate text-small font-medium text-ink">{exam.title}</p>
            <p className="truncate text-caption text-ink-soft">{exam.className}</p>
          </div>
          {stage === 'active' ?
          <div
            className={`inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 tabular-nums ${
            low ? 'border-state-danger/30 bg-state-dangerBg text-state-danger' : 'border-line text-ink'}`
            }
            role="timer"
            aria-label={`${mm} minutes ${ss} seconds remaining`}>
            
              <ClockIcon className="h-4 w-4" aria-hidden="true" />
              <span className="text-small font-semibold">
                {mm}:{ss}
              </span>
            </div> :

          <Link to="/student/exams" className="text-small text-ink-soft hover:text-ink">
              Exit
            </Link>
          }
        </div>
      </header>

      {stage === 'instructions' ?
      <main className="mx-auto max-w-2xl px-5 py-12">
          <h1 className="text-h1 text-ink">{exam.title}</h1>
          <dl className="mt-6 grid grid-cols-3 gap-px overflow-hidden rounded-2xl border border-line bg-line">
            <Fact label="Duration" value={`${exam.durationMins} min`} />
            <Fact label="Questions" value={`${questions.length}`} />
            <Fact label="Marks" value={`${exam.totalMarks}`} />
          </dl>
          <ul className="mt-6 space-y-2 text-small text-ink-soft">
            <li>• The timer starts when you press Start and keeps running if you leave the page.</li>
            <li>• Answers save automatically. You can move between questions and flag any to revisit.</li>
            <li>• Objective questions are marked instantly; written answers are marked by your teacher.</li>
            <li>• When time runs out, your answers are submitted as they are.</li>
          </ul>
          <Button size="lg" className="mt-8 w-full sm:w-auto" onClick={() => setStage('active')}>
            Start exam
          </Button>
        </main> :
      null}

      {stage === 'active' ?
      <main className="mx-auto grid max-w-[1100px] gap-6 px-5 py-8 lg:grid-cols-[1fr_16rem]">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card lg:p-8">
            <QuestionView
            question={q}
            index={current}
            answer={answers[q.id]}
            onAnswer={(a) => setAnswers((all) => ({ ...all, [q.id]: a }))} />
          
            <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
              <Button variant="ghost" size="sm" disabled={current === 0} onClick={() => setCurrent((c) => c - 1)}>
                <ChevronLeftIcon className="h-4 w-4" />
                Previous
              </Button>
              <Button
              variant="secondary"
              size="sm"
              aria-pressed={flags.includes(q.id)}
              onClick={() => setFlags((f) => f.includes(q.id) ? f.filter((x) => x !== q.id) : [...f, q.id])}>
              
                <FlagIcon className="h-4 w-4" />
                {flags.includes(q.id) ? 'Flagged' : 'Flag for review'}
              </Button>
              {current < questions.length - 1 ?
            <Button size="sm" onClick={() => setCurrent((c) => c + 1)}>
                  Next
                  <ChevronRightIcon className="h-4 w-4" />
                </Button> :

            <Button size="sm" onClick={() => setConfirm(true)}>
                  Review & submit
                </Button>
            }
            </div>
          </section>

          <aside className="h-fit space-y-4 rounded-2xl border border-line bg-white p-5 lg:sticky lg:top-24">
            <p className="text-small font-medium text-ink">Questions</p>
            <div className="grid grid-cols-5 gap-2">
              {questions.map((qq, i) =>
            <button
              key={qq.id}
              onClick={() => setCurrent(i)}
              aria-label={`Question ${i + 1}${answered(qq) ? ', answered' : ''}${flags.includes(qq.id) ? ', flagged' : ''}`}
              aria-current={i === current}
              className={`relative h-9 rounded-lg border text-caption font-semibold transition-colors duration-150 ease-out ${
              i === current ?
              'border-forest bg-forest text-white' :
              answered(qq) ?
              'border-forest-100 bg-forest-50 text-forest' :
              'border-line text-ink-soft hover:border-line-strong'}`
              }>
              
                  {i + 1}
                  {flags.includes(qq.id) ? <span className="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-clay" /> : null}
                </button>
            )}
            </div>
            <p className="text-caption text-ink-soft">
              {questions.length - unanswered} of {questions.length} answered · saved just now
            </p>
            <Button variant="secondary" size="sm" className="w-full" onClick={() => setConfirm(true)}>
              Submit exam
            </Button>
          </aside>
        </main> :
      null}

      {stage === 'result' ?
      <main className="mx-auto max-w-2xl px-5 py-12">
          <div className="flex items-center gap-3">
            <CheckCircle2Icon className="h-6 w-6 text-forest" aria-hidden="true" />
            <h1 className="text-h1 text-ink">Submitted</h1>
          </div>
          <section className="mt-6 rounded-2xl border border-line bg-white p-6 shadow-card">
            <p className="text-caption uppercase tracking-wide text-ink-faint">Marked so far</p>
            <p className="mt-1 text-display text-ink">
              {result.auto}
              <span className="text-ink-faint"> / {result.autoMax}</span>
            </p>
            <p className="mt-2 text-small text-ink-soft">
              {result.pending} marks of written answers are with your teacher. Your final
              result appears in Results — and for your guardian — once they are marked.
            </p>
          </section>
          <ul className="mt-6 divide-y divide-line rounded-2xl border border-line bg-white">
            {questions.map((qq, i) => {
            const m = autoMark(qq, answers[qq.id]);
            return (
              <li key={qq.id} className="flex items-center justify-between gap-3 px-5 py-3">
                  <span className="truncate text-small text-ink">
                    {i + 1}. {qq.prompt}
                  </span>
                  {m === null ?
                <Badge tone="info">Teacher marking</Badge> :

                <Badge tone={m === qq.marks ? 'success' : 'danger'}>
                      {m}/{qq.marks}
                    </Badge>
                }
                </li>);

          })}
          </ul>
          <LinkButton to="/student/marks" className="mt-6">
            Go to Results
          </LinkButton>
        </main> :
      null}

      <Modal open={confirm} onClose={() => setConfirm(false)} title="Submit exam?">
        <p className="text-small text-ink-soft">
          {unanswered > 0 ?
          `${unanswered} question${unanswered > 1 ? 's are' : ' is'} unanswered.` :
          'All questions answered.'}{' '}
          {flags.length ? `${flags.length} flagged for review. ` : ''}You can't change answers after submitting.
        </p>
        <div className="mt-5 flex gap-2">
          <Button
            className="flex-1"
            onClick={() => {
              setConfirm(false);
              setStage('result');
            }}>
            
            Submit
          </Button>
          <Button variant="secondary" onClick={() => setConfirm(false)}>
            Keep working
          </Button>
        </div>
      </Modal>
    </div>);

}

function Fact({ label, value }: {label: string;value: string;}) {
  return (
    <div className="bg-white px-4 py-4">
      <dt className="text-caption uppercase tracking-wide text-ink-faint">{label}</dt>
      <dd className="mt-1 text-h3 text-ink">{value}</dd>
    </div>);

}