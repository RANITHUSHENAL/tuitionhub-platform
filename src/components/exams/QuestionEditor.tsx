import React from 'react';
import { ArrowDownIcon, ArrowUpIcon, CheckIcon, ImageIcon, PlusIcon, TrashIcon } from 'lucide-react';
import type { ExamQuestion } from '../../types';
import { questionTypeLabels } from '../../data/exams';

export function QuestionEditor({
  question,
  index,
  total,
  onChange,
  onRemove,
  onMove







}: {question: ExamQuestion;index: number;total: number;onChange: (q: ExamQuestion) => void;onRemove: () => void;onMove: (dir: -1 | 1) => void;}) {
  const q = question;
  const hasOptions = !!q.options;
  const multi = q.type === 'multiple';
  const correct = q.correct ?? [];

  const toggleCorrect = (i: number) => {
    if (multi) onChange({ ...q, correct: correct.includes(i) ? correct.filter((c) => c !== i) : [...correct, i] });else
    onChange({ ...q, correct: [i] });
  };

  return (
    <article className="rounded-2xl border border-line bg-white p-5 shadow-card">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-small font-medium text-ink">
          {index + 1}. <span className="text-ink-soft">{questionTypeLabels[q.type].label}</span>
        </p>
        <div className="flex items-center gap-1">
          <label className="mr-2 flex items-center gap-2 text-caption text-ink-soft">
            Marks
            <input
              type="number"
              min={1}
              value={q.marks}
              onChange={(e) => onChange({ ...q, marks: Math.max(1, Number(e.target.value)) })}
              className="h-8 w-16 rounded-lg border border-line px-2 text-small text-ink focus:border-forest focus:outline-none" />
            
          </label>
          <IconBtn label="Move up" disabled={index === 0} onClick={() => onMove(-1)}>
            <ArrowUpIcon className="h-4 w-4" />
          </IconBtn>
          <IconBtn label="Move down" disabled={index === total - 1} onClick={() => onMove(1)}>
            <ArrowDownIcon className="h-4 w-4" />
          </IconBtn>
          <IconBtn label="Delete question" onClick={onRemove}>
            <TrashIcon className="h-4 w-4" />
          </IconBtn>
        </div>
      </header>

      <textarea
        rows={2}
        value={q.prompt}
        onChange={(e) => onChange({ ...q, prompt: e.target.value })}
        placeholder="Type the question"
        aria-label={`Question ${index + 1} text`}
        className="mt-3 w-full rounded-xl border border-line px-3.5 py-2.5 text-body text-ink focus:border-forest focus:outline-none focus:ring-2 focus:ring-forest/15" />
      

      {q.type === 'image' ?
      <button
        type="button"
        className="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-line-strong bg-surface-muted py-6 text-small text-ink-soft transition-colors duration-150 ease-out hover:border-forest hover:text-forest">
        
          <ImageIcon className="h-4 w-4" />
          Upload diagram (PNG or JPG)
        </button> :
      null}

      {hasOptions ?
      <div className="mt-4 space-y-2">
          <p className="text-caption text-ink-faint">
            {multi ? 'Tick every correct answer' : 'Tick the correct answer'}
          </p>
          {q.options!.map((o, i) => {
          const on = correct.includes(i);
          return (
            <div key={i} className="flex items-center gap-2">
                <button
                type="button"
                onClick={() => toggleCorrect(i)}
                aria-pressed={on}
                aria-label={`Mark option ${String.fromCharCode(65 + i)} correct`}
                className={`flex h-8 w-8 shrink-0 items-center justify-center border transition-colors duration-150 ease-out ${
                multi ? 'rounded-md' : 'rounded-full'} ${
                on ? 'border-forest bg-forest text-white' : 'border-line-strong text-ink-faint hover:border-forest'}`}>
                
                  {on ? <CheckIcon className="h-4 w-4" /> : String.fromCharCode(65 + i)}
                </button>
                <input
                value={o}
                disabled={q.type === 'truefalse'}
                onChange={(e) =>
                onChange({ ...q, options: q.options!.map((x, idx) => idx === i ? e.target.value : x) })
                }
                placeholder={`Option ${String.fromCharCode(65 + i)}`}
                aria-label={`Option ${String.fromCharCode(65 + i)}`}
                className="h-10 flex-1 rounded-xl border border-line px-3.5 text-small text-ink focus:border-forest focus:outline-none disabled:bg-surface-muted" />
              
                {q.type !== 'truefalse' && q.options!.length > 2 ?
              <IconBtn
                label="Remove option"
                onClick={() =>
                onChange({
                  ...q,
                  options: q.options!.filter((_, idx) => idx !== i),
                  correct: correct.filter((c) => c !== i).map((c) => c > i ? c - 1 : c)
                })
                }>
                
                    <TrashIcon className="h-3.5 w-3.5" />
                  </IconBtn> :
              null}
              </div>);

        })}
          {q.type !== 'truefalse' && q.options!.length < 6 ?
        <button
          type="button"
          onClick={() => onChange({ ...q, options: [...q.options!, ''] })}
          className="inline-flex items-center gap-1.5 text-caption font-medium text-forest">
          
              <PlusIcon className="h-3.5 w-3.5" />
              Add option
            </button> :
        null}
        </div> :
      null}

      {q.type === 'short' || q.type === 'numerical' ?
      <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_10rem]">
          <input
          value={q.answerText ?? ''}
          onChange={(e) => onChange({ ...q, answerText: e.target.value })}
          placeholder={q.type === 'numerical' ? 'Correct value, e.g. 340' : 'Accepted answer'}
          aria-label="Accepted answer"
          className="h-10 rounded-xl border border-line px-3.5 text-small text-ink focus:border-forest focus:outline-none" />
        
          {q.type === 'numerical' ?
        <input
          value={q.unit ?? ''}
          onChange={(e) => onChange({ ...q, unit: e.target.value })}
          placeholder="Unit (m s⁻¹)"
          aria-label="Unit"
          className="h-10 rounded-xl border border-line px-3.5 text-small text-ink focus:border-forest focus:outline-none" /> :

        null}
        </div> :
      null}

      {q.type === 'essay' ?
      <p className="mt-3 text-caption text-ink-faint">
          Essay answers go to your marking queue. Students see “Teacher marking” until you release.
        </p> :
      null}
    </article>);

}

function IconBtn({
  label,
  onClick,
  disabled,
  children





}: {label: string;onClick: () => void;disabled?: boolean;children: React.ReactNode;}) {
  return (
    <button
      type="button"
      aria-label={label}
      onClick={onClick}
      disabled={disabled}
      className="rounded-lg p-1.5 text-ink-faint transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink disabled:opacity-40">
      
      {children}
    </button>);

}