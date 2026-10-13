import React from 'react';
import { CheckCircle2Icon, CircleDashedIcon } from 'lucide-react';

/** Subtle, stacked trust indicators: done items in forest, pending items muted. */
export function TrustBadges({ flags }: {flags: {label: string;done: boolean;}[];}) {
  return (
    <ul className="flex flex-wrap gap-x-4 gap-y-1.5">
      {flags.map((f) =>
      <li
        key={f.label}
        className={`inline-flex items-center gap-1.5 text-caption font-medium ${
        f.done ? 'text-forest-600' : 'text-ink-faint'}`
        }>
        
          {f.done ?
        <CheckCircle2Icon className="h-3.5 w-3.5" aria-hidden="true" /> :

        <CircleDashedIcon className="h-3.5 w-3.5" aria-hidden="true" />
        }
          {f.label}
          <span className="sr-only">{f.done ? '(complete)' : '(not yet)'}</span>
        </li>
      )}
    </ul>);

}