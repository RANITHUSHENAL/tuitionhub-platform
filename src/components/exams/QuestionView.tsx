import React from 'react';
import { CheckIcon } from 'lucide-react';
import type { ExamQuestion } from '../../types';
import { questionTypeLabels } from '../../data/exams';

export type Answer = number[] | string;

export function QuestionView({
  question,
  index,
  answer,
  onAnswer,
  disabled = false






}: {question: ExamQuestion;index: number;answer?: Answer;onAnswer: (a: Answer) => void;disabled?: boolean;}) {
  const selected = Array.isArray(answer) ? answer : [];
  const multi = question.type === 'multiple';

  const pick = (i: number) => {
    if (disabled) return;
    if (multi) onAnswer(selected.includes(i) ? selected.filter((x) => x !== i) : [...selected, i]);else
    onAnswer([i]);
  };

  return (
    <div>
      <div className="flex flex-wrap items-center gap-2 text-caption text-ink-faint">
        <span className="font-medium text-ink-soft">Question {index + 1}</span>
        <span>·</span>
        <span>{questionTypeLabels[question.type].label}</span>
        <span>·</span>
        <span>
          {question.marks} {question.marks === 1 ? 'mark' : 'marks'}
        </span>
      </div>
      <p className="mt-3 text-h3 text-ink">{question.prompt}</p>
      {multi ? <p className="mt-1 text-caption text-ink-soft">Select all that apply.</p> : null}

      {question.type === 'image' ?
      <figure className="mt-4 overflow-hidden rounded-2xl border border-line bg-surface-muted p-4">
          <WaveDiagram />
          <figcaption className="mt-2 text-caption text-ink-faint">Figure 1 — string fixed at both ends</figcaption>
        </figure> :
      null}

      {question.options ?
      <div role={multi ? 'group' : 'radiogroup'} aria-label={`Question ${index + 1} options`} className="mt-5 grid gap-2">
          {question.options.map((o, i) => {
          const on = selected.includes(i);
          return (
            <button
              key={o}
              type="button"
              role={multi ? 'checkbox' : 'radio'}
              aria-checked={on}
              disabled={disabled}
              onClick={() => pick(i)}
              className={`flex items-center gap-3 rounded-xl border px-4 py-3.5 text-left transition-[border-color,background-color] duration-150 ease-out ${
              on ? 'border-forest bg-forest-50' : 'border-line bg-white hover:border-line-strong'}`
              }>
              
                <span
                className={`flex h-6 w-6 shrink-0 items-center justify-center border text-caption font-semibold ${
                multi ? 'rounded-md' : 'rounded-full'} ${
                on ? 'border-forest bg-forest text-white' : 'border-line-strong text-ink-soft'}`}>
                
                  {on ? <CheckIcon className="h-3.5 w-3.5" /> : String.fromCharCode(65 + i)}
                </span>
                <span className="text-small text-ink">{o}</span>
              </button>);

        })}
        </div> :
      null}

      {question.type === 'short' ?
      <input
        value={typeof answer === 'string' ? answer : ''}
        onChange={(e) => onAnswer(e.target.value)}
        disabled={disabled}
        aria-label="Your answer"
        placeholder="Type your answer"
        className="mt-5 h-12 w-full rounded-xl border border-line bg-white px-4 text-body text-ink focus:border-forest focus:outline-none focus:ring-2 focus:ring-forest/15" /> :

      null}

      {question.type === 'numerical' ?
      <div className="mt-5 flex max-w-xs items-center rounded-xl border border-line bg-white focus-within:border-forest focus-within:ring-2 focus-within:ring-forest/15">
          <input
          inputMode="decimal"
          value={typeof answer === 'string' ? answer : ''}
          onChange={(e) => onAnswer(e.target.value.replace(/[^\d.-]/g, ''))}
          disabled={disabled}
          aria-label="Numerical answer"
          placeholder="0"
          className="h-12 w-full bg-transparent px-4 text-body tabular-nums text-ink focus:outline-none" />
        
          {question.unit ? <span className="border-l border-line px-4 text-small text-ink-soft">{question.unit}</span> : null}
        </div> :
      null}

      {question.type === 'essay' ?
      <div className="mt-5">
          <textarea
          rows={8}
          value={typeof answer === 'string' ? answer : ''}
          onChange={(e) => onAnswer(e.target.value)}
          disabled={disabled}
          aria-label="Essay answer"
          placeholder="Write your answer. It is saved as you type."
          className="w-full rounded-xl border border-line bg-white px-4 py-3 text-body text-ink focus:border-forest focus:outline-none focus:ring-2 focus:ring-forest/15" />
        
          <p className="mt-1 text-caption text-ink-faint">
            {typeof answer === 'string' ? answer.trim().split(/\s+/).filter(Boolean).length : 0} words · marked by your teacher
          </p>
        </div> :
      null}
    </div>);

}

function WaveDiagram() {
  const path = Array.from({ length: 121 }, (_, i) => {
    const x = i / 120 * 300;
    const y = 50 + Math.sin(i / 120 * Math.PI * 3) * 32;
    return `${i === 0 ? 'M' : 'L'} ${x + 10} ${y}`;
  }).join(' ');
  const mirror = Array.from({ length: 121 }, (_, i) => {
    const x = i / 120 * 300;
    const y = 50 - Math.sin(i / 120 * Math.PI * 3) * 32;
    return `${i === 0 ? 'M' : 'L'} ${x + 10} ${y}`;
  }).join(' ');
  return (
    <svg viewBox="0 0 320 100" className="h-auto w-full" role="img" aria-label="Stationary wave on a string with three loops">
      <line x1="10" y1="50" x2="310" y2="50" stroke="#BECBBD" strokeDasharray="3 4" />
      <path d={path} fill="none" stroke="#13402C" strokeWidth="2" />
      <path d={mirror} fill="none" stroke="#B04A2F" strokeWidth="1.5" strokeDasharray="5 4" />
      <rect x="4" y="30" width="6" height="40" fill="#55605A" />
      <rect x="310" y="30" width="6" height="40" fill="#55605A" />
    </svg>);

}