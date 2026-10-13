import React from 'react';

export function SectionHeading({
  eyebrow,
  title,
  body,
  action





}: {eyebrow?: string;title: string;body?: string;action?: React.ReactNode;}) {
  return (
    <div className="flex flex-wrap items-end justify-between gap-6">
      <div className="max-w-2xl">
        {eyebrow ?
        <p className="text-caption font-medium uppercase tracking-wide text-clay">
            {eyebrow}
          </p> :
        null}
        <h2 className="mt-2 text-h1 text-ink">{title}</h2>
        {body ? <p className="mt-3 text-body text-ink-soft">{body}</p> : null}
      </div>
      {action}
    </div>);

}