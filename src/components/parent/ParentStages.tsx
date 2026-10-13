import React from 'react';
import { CheckIcon, HourglassIcon, LockIcon } from 'lucide-react';
import type { SetupStep } from '../../utils/parentSetup';

const statusStyle: Record<SetupStep['status'], string> = {
  done: 'text-state-success',
  todo: 'text-clay',
  waiting: 'text-state-info',
  locked: 'text-ink-faint'
};

/** Compact horizontal indicator of the four parent stages. */
export function ParentStages({ steps, current }: {steps: SetupStep[];current?: SetupStep['id'];}) {
  return (
    <ol className="grid grid-cols-2 gap-2 sm:grid-cols-4" aria-label="Parent setup stages">
      {steps.map((s, i) => {
        const active = s.id === current;
        return (
          <li
            key={s.id}
            aria-current={active ? 'step' : undefined}
            className={`rounded-xl border px-3 py-2.5 ${active ? 'border-forest bg-forest-50' : 'border-line bg-white'}`}>
            
            <p className="flex items-center gap-1.5 text-caption text-ink-faint">
              <StepDot status={s.status} n={i + 1} />
              {s.label}
            </p>
            <p className={`mt-1 text-caption font-medium ${statusStyle[s.status]}`}>{s.statusLabel}</p>
          </li>);

      })}
    </ol>);

}

export function StepDot({ status, n }: {status: SetupStep['status'];n: number;}) {
  if (status === 'done')
  return (
    <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-forest text-white">
        <CheckIcon className="h-2.5 w-2.5" aria-hidden="true" />
      </span>);

  if (status === 'waiting')
  return (
    <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-state-infoBg text-state-info">
        <HourglassIcon className="h-2.5 w-2.5" aria-hidden="true" />
      </span>);

  if (status === 'locked')
  return (
    <span className="inline-flex h-4 w-4 items-center justify-center rounded-full bg-surface-sunk text-ink-faint">
        <LockIcon className="h-2.5 w-2.5" aria-hidden="true" />
      </span>);

  return (
    <span className="inline-flex h-4 w-4 items-center justify-center rounded-full border border-clay text-[10px] font-semibold text-clay">
      {n}
    </span>);

}