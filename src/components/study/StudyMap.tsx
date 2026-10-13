import React from 'react';
import { ShieldCheckIcon } from 'lucide-react';
import type { StudyActivity } from '../../types';
import { activityAccent } from '../cards/ActivityCard';

const areas = [
{ label: 'Colombo 07', x: 56, y: 20 },
{ label: 'Colombo 05', x: 52, y: 33 },
{ label: 'Nugegoda', x: 38, y: 47 },
{ label: 'Dehiwala', x: 62, y: 46 },
{ label: 'Maharagama', x: 27, y: 64 },
{ label: 'Pannipitiya', x: 22, y: 79 }];


const roads = [
'M 4 30 C 28 34, 44 44, 58 62 S 78 88, 96 94',
'M 10 78 C 30 70, 46 58, 62 40 S 84 18, 98 12',
'M 0 54 C 24 52, 52 50, 100 44',
'M 34 0 C 36 26, 30 52, 20 100',
'M 70 0 C 68 30, 74 60, 86 100'];


/**
 * A schematic discovery map. It deliberately shows approximate areas, not
 * precise pins on real addresses — public discovery never exposes an exact
 * meeting point before the host accepts you.
 */
export function StudyMap({
  activities,
  selectedId,
  onSelect




}: {activities: StudyActivity[];selectedId: string | null;onSelect: (id: string) => void;}) {
  return (
    <div className="relative h-full min-h-[420px] w-full overflow-hidden rounded-2xl border border-line bg-surface-sunk">
      <svg
        viewBox="0 0 100 100"
        preserveAspectRatio="none"
        className="absolute inset-0 h-full w-full"
        aria-hidden="true">
        
        <rect width="100" height="100" fill="#EDF2EC" />
        <path d="M 0 84 C 20 80, 34 88, 48 100 L 0 100 Z" fill="#DCE9E1" />
        <path d="M 74 0 C 86 12, 92 26, 100 30 L 100 0 Z" fill="#DCE9E1" />
        {roads.map((d) =>
        <path
          key={d}
          d={d}
          fill="none"
          stroke="#D8E2D7"
          strokeWidth="1.1"
          vectorEffect="non-scaling-stroke" />

        )}
      </svg>

      {areas.map((a) =>
      <span
        key={a.label}
        className="pointer-events-none absolute -translate-x-1/2 text-caption font-medium uppercase tracking-wide text-ink-faint"
        style={{ left: `${a.x}%`, top: `${a.y}%` }}>
        
          {a.label}
        </span>
      )}

      {activities.map((a) => {
        const selected = a.id === selectedId;
        return (
          <button
            key={a.id}
            onClick={() => onSelect(a.id)}
            aria-label={`${a.title}, ${a.areaLabel}, ${a.going} of ${a.capacity} going`}
            aria-pressed={selected}
            className="absolute -translate-x-1/2 -translate-y-full transition-transform duration-200 ease-out hover:-translate-y-[108%]"
            style={{ left: `${a.mapX}%`, top: `${a.mapY}%` }}>
            
            <span
              className={`flex items-center gap-2 rounded-full border bg-white py-1.5 pl-1.5 pr-3 shadow-card ${
              selected ? 'border-forest ring-2 ring-forest/20' : 'border-line'}`
              }>
              
              <span
                className={`flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-semibold text-white ${
                activityAccent[a.type] ?? 'bg-forest'}`
                }>
                
                {a.going}
              </span>
              <span className="max-w-[9rem] truncate text-caption font-medium text-ink">
                {a.subject}
              </span>
            </span>
            <span
              className={`mx-auto block h-2 w-2 -translate-y-1 rotate-45 border-b border-r bg-white ${
              selected ? 'border-forest' : 'border-line'}`
              } />
            
          </button>);

      })}

      <p className="absolute bottom-3 left-3 right-3 inline-flex items-start gap-2 rounded-xl border border-line bg-white/95 px-3 py-2 text-caption text-ink-soft backdrop-blur">
        <ShieldCheckIcon className="mt-0.5 h-3.5 w-3.5 shrink-0 text-forest" aria-hidden="true" />
        Activities show an approximate area only. The exact meeting point is shared with
        participants after the host accepts them.
      </p>
    </div>);

}

export function MapLegend() {
  const types = [
  'Study Together',
  'Peer Teaching',
  'Past Paper Discussion',
  'Practice Session',
  'Project / Knowledge Sharing'];

  return (
    <ul className="flex flex-wrap gap-x-4 gap-y-2">
      {types.map((t) =>
      <li key={t} className="flex items-center gap-2 text-caption text-ink-soft">
          <span className={`h-2.5 w-2.5 rounded-full ${activityAccent[t]}`} aria-hidden="true" />
          {t}
        </li>
      )}
    </ul>);

}