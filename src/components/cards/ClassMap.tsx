import React from 'react';
import { Link } from 'react-router-dom';
import { Badge } from '../ui/Badge';
import type { TuitionClass } from '../../types';
import { lkr } from '../../utils/format';

/** Schematic map of physical classes. Pins show approximate institute areas. */
export function ClassMap({ results, className = 'min-h-[420px]' }: {results: TuitionClass[];className?: string;}) {
  const physical = results.filter((c) => c.mode !== 'Online');
  return (
    <div className={`relative overflow-hidden rounded-2xl border border-line bg-surface-sunk ${className}`}>
      <svg viewBox="0 0 100 100" preserveAspectRatio="none" className="absolute inset-0 h-full w-full" aria-hidden="true">
        <rect width="100" height="100" fill="#EDF2EC" />
        <path d="M 0 40 C 30 42, 60 46, 100 38" stroke="#D8E2D7" strokeWidth="1.2" fill="none" vectorEffect="non-scaling-stroke" />
        <path d="M 30 0 C 32 30, 26 60, 18 100" stroke="#D8E2D7" strokeWidth="1.2" fill="none" vectorEffect="non-scaling-stroke" />
        <path d="M 72 0 C 70 34, 78 66, 90 100" stroke="#D8E2D7" strokeWidth="1.2" fill="none" vectorEffect="non-scaling-stroke" />
      </svg>
      {physical.map((c) =>
      <Link
        key={c.id}
        to={`/class/${c.id}`}
        className="absolute -translate-x-1/2 -translate-y-full"
        style={{ left: `${c.mapX}%`, top: `${c.mapY}%` }}
        aria-label={`${c.title}, ${c.city}, ${lkr(c.feeMonthly)} a month`}>
        
          <span className="flex items-center gap-2 rounded-full border border-line bg-white px-3 py-1.5 text-caption font-medium text-ink shadow-card transition-colors duration-150 ease-out hover:border-forest">
            {lkr(c.feeMonthly)}
            <span className="text-ink-faint">{c.city}</span>
          </span>
        </Link>
      )}
      <div className="absolute bottom-3 left-3">
        <Badge>Approximate institute locations · online classes shown in list</Badge>
      </div>
    </div>);

}