import React from 'react';

const palette = [
'bg-forest text-white',
'bg-clay text-white',
'bg-forest-100 text-forest',
'bg-state-infoBg text-state-info',
'bg-state-warnBg text-state-warn'];


function hue(seed: string) {
  let sum = 0;
  for (let i = 0; i < seed.length; i += 1) sum += seed.charCodeAt(i);
  return palette[sum % palette.length];
}

export function Avatar({
  initials,
  size = 'md',
  label




}: {initials: string;size?: 'xs' | 'sm' | 'md' | 'lg';label?: string;}) {
  const dims =
  size === 'xs' ?
  'h-7 w-7 text-[11px]' :
  size === 'sm' ?
  'h-8 w-8 text-caption' :
  size === 'lg' ?
  'h-14 w-14 text-body' :
  'h-10 w-10 text-small';
  return (
    <span
      title={label}
      aria-label={label}
      className={`inline-flex shrink-0 items-center justify-center rounded-full font-semibold ${dims} ${hue(initials)}`}>
      
      {initials}
    </span>);

}

export function AvatarStack({
  people,
  max = 4,
  total




}: {people: {initials: string;name: string;}[];max?: number;total?: number;}) {
  const shown = people.slice(0, max);
  const remainder = (total ?? people.length) - shown.length;
  return (
    <div className="flex items-center">
      <div className="flex -space-x-2">
        {shown.map((p) =>
        <span key={p.name} className="rounded-full ring-2 ring-white">
            <Avatar initials={p.initials} size="sm" label={p.name} />
          </span>
        )}
      </div>
      {remainder > 0 ?
      <span className="ml-2 text-caption text-ink-soft">+{remainder}</span> :
      null}
    </div>);

}