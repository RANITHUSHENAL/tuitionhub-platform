import React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ArrowLeftIcon, ShieldCheckIcon } from 'lucide-react';
import { actionCopy } from '../../data/accounts';

export function AuthLayout({
  title,
  subtitle,
  step,
  children





}: {title: string;subtitle?: string;step?: {current: number;total: number;label: string;};children: React.ReactNode;}) {
  const [params] = useSearchParams();
  const action = params.get('action');
  const next = params.get('next');

  return (
    <div className="min-h-screen bg-canvas">
      <div className="mx-auto grid min-h-screen max-w-[1200px] lg:grid-cols-[0.9fr_1.1fr]">
        <aside className="hidden flex-col justify-between border-r border-line px-10 py-10 lg:flex">
          <div>
            <Link to="/" className="text-h3 font-bold tracking-tight text-ink">
              Tuition<span className="text-forest">Hub</span>
            </Link>
            <h2 className="mt-16 max-w-sm text-h1 text-ink">
              Browse freely. Sign in when it matters.
            </h2>
            <p className="mt-4 max-w-sm text-body text-ink-soft">
              Your phone number is your account. We only ask for more when a step needs
              it — enrolling, paying, or connecting a family.
            </p>
          </div>
          <div className="flex gap-2.5 rounded-2xl border border-line bg-white p-4">
            <ShieldCheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-forest" aria-hidden="true" />
            <p className="text-caption text-ink-soft">
              One-time codes are sent by SMS. TuitionHub never asks for your code over the
              phone or WhatsApp.
            </p>
          </div>
        </aside>

        <main className="flex flex-col px-5 py-8 sm:px-10 lg:py-12">
          <div className="flex items-center justify-between">
            <Link
              to={next ?? '/'}
              className="inline-flex items-center gap-1.5 text-small text-ink-soft transition-colors duration-150 ease-out hover:text-ink">
              
              <ArrowLeftIcon className="h-4 w-4" />
              {next ? 'Back to where you were' : 'Back to TuitionHub'}
            </Link>
            <Link to="/" className="text-h3 font-bold tracking-tight text-ink lg:hidden">
              Tuition<span className="text-forest">Hub</span>
            </Link>
          </div>

          <div className="mx-auto w-full max-w-md flex-1 py-10">
            {action && actionCopy[action] ?
            <p className="mb-6 rounded-xl border border-forest-100 bg-forest-50 px-4 py-3 text-small text-forest">
                Sign in to {actionCopy[action]}. You’ll come straight back to continue.
              </p> :
            null}
            {step ?
            <div className="mb-5">
                <p className="text-caption font-medium text-ink-faint">
                  {step.label}
                </p>
                <div className="mt-2 flex gap-1.5" aria-hidden="true">
                  {Array.from({ length: step.total }).map((_, i) =>
                <span
                  key={i}
                  className={`h-1 flex-1 rounded-full ${i < step.current ? 'bg-forest' : 'bg-line'}`} />

                )}
                </div>
              </div> :
            null}
            <h1 className="text-h1 text-ink">{title}</h1>
            {subtitle ? <p className="mt-2 text-body text-ink-soft">{subtitle}</p> : null}
            <div className="mt-8">{children}</div>
          </div>
        </main>
      </div>
    </div>);

}

/** Carries the next/action params through every auth link. */
export function useAuthParams() {
  const [params] = useSearchParams();
  const carry = new URLSearchParams();
  const next = params.get('next');
  const action = params.get('action');
  if (next) carry.set('next', next);
  if (action) carry.set('action', action);
  return { next, action, role: params.get('role'), mode: params.get('mode'), carry: carry.toString() };
}