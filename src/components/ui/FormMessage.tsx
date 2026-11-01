import { AlertTriangleIcon, CheckCircle2Icon } from 'lucide-react';

/** A field's validation message, linked to its control with `id` / aria-describedby. */
export function FieldError({ id, message }: {id: string;message?: string;}) {
  if (!message) return null;
  return (
    <p id={id} role="alert" className="mt-1.5 text-caption text-state-danger">
      {message}
    </p>);

}

/** A form- or section-level error or success line. */
export function FormAlert({ tone = 'error', message }: {tone?: 'error' | 'success';message?: string | null;}) {
  if (!message) return null;
  const error = tone === 'error';
  const Icon = error ? AlertTriangleIcon : CheckCircle2Icon;

  return (
    <div
      role={error ? 'alert' : 'status'}
      className={`flex gap-2.5 rounded-xl px-3.5 py-3 text-small ${
      error ? 'bg-state-dangerBg/60 text-ink' : 'bg-state-successBg/70 text-ink'}`
      }>

      <Icon className={`mt-0.5 h-4 w-4 shrink-0 ${error ? 'text-state-danger' : 'text-state-success'}`} aria-hidden="true" />
      <p>{message}</p>
    </div>);

}

/** Loading line for API-backed sections. */
export function LoadingLine({ label }: {label: string;}) {
  return (
    <p role="status" className="rounded-2xl border border-line bg-white px-5 py-6 text-small text-ink-soft">
      {label}
    </p>);

}
