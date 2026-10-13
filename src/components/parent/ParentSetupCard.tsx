import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon } from 'lucide-react';
import { LinkButton } from '../ui/Button';
import { StepDot } from './ParentStages';
import { useAuth } from '../../contexts/AuthContext';
import { connectChildPath, nextSetupStep, parentSetupSteps } from '../../utils/parentSetup';

/**
 * Profile-completion checklist. Shows what is done and what is left, with one
 * clear next action. Never blocks the rest of the platform.
 */
export function ParentSetupCard({ welcome = false }: {welcome?: boolean;}) {
  const { user } = useAuth();
  const steps = parentSetupSteps(user);
  const next = nextSetupStep(user);
  const done = steps.filter((s) => s.status === 'done').length;

  return (
    <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
      <div className="flex flex-wrap items-start justify-between gap-4 border-b border-line px-6 py-5">
        <div className="max-w-xl">
          <h2 className="text-h2 text-ink">{welcome ? 'Welcome to TuitionHub' : 'Finish setting up'}</h2>
          <p className="mt-1 text-small text-ink-soft">
            Complete your parent profile and connect your child to start managing their tuition
            journey.
          </p>
        </div>
        <div className="text-right">
          <p className="text-caption text-ink-faint">
            {done} of {steps.length} done
          </p>
          <div className="mt-1.5 flex gap-1" aria-hidden="true">
            {steps.map((s) =>
            <span key={s.id} className={`h-1.5 w-8 rounded-full ${s.status === 'done' ? 'bg-forest' : 'bg-line'}`} />
            )}
          </div>
        </div>
      </div>

      <ol className="divide-y divide-line">
        {steps.map((s, i) => {
          const isNext = next?.id === s.id;
          return (
            <li key={s.id} className={`flex flex-wrap items-center gap-4 px-6 py-4 ${isNext ? 'bg-forest-50/50' : ''}`}>
              <StepDot status={s.status} n={i + 1} />
              <div className="min-w-0 flex-1">
                <p className="text-small font-medium text-ink">
                  {s.label} <span className="font-normal text-ink-soft">· {s.statusLabel}</span>
                </p>
                <p className="text-caption text-ink-soft">
                  {s.status === 'locked' ?
                  s.id === 'verification' ?
                  'Available after your parent profile is complete.' :
                  'Available once you are a verified guardian.' :
                  s.status === 'waiting' ?
                  'A reviewer is checking it — usually within a few hours. Nothing else is blocked.' :
                  s.why}
                </p>
              </div>
              {s.status === 'todo' ?
              <LinkButton to={s.to} size="sm" variant={isNext ? 'primary' : 'secondary'}>
                  {s.action}
                </LinkButton> :
              null}
            </li>);

        })}
      </ol>

      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-surface-muted px-6 py-4">
        <div className="flex flex-wrap gap-2">
          <LinkButton to="/parent/profile" size="sm" variant="secondary">
            Complete parent profile
          </LinkButton>
          <LinkButton to={connectChildPath(user)} size="sm" variant="secondary">
            Connect a child
          </LinkButton>
        </div>
        <Link to="/search" className="inline-flex items-center gap-1 text-small font-medium text-forest">
          Or browse classes first <ArrowRightIcon className="h-3.5 w-3.5" />
        </Link>
      </div>
    </section>);

}