import React from 'react';
import { Link } from 'react-router-dom';
import { CalendarIcon, MapPinIcon, MonitorIcon, UsersIcon } from 'lucide-react';
import type { TuitionClass } from '../../types';
import { Badge, ClassStatusBadge, VerifiedBadge } from '../ui/Badge';
import { lkr, percent } from '../../utils/format';

export function ClassCard({
  item,
  compact = false,
  reason,
  sponsoredBy







}: {item: TuitionClass;compact?: boolean; /** Why this organic recommendation appears */reason?: string; /** Paid placement — always rendered with a visible "Sponsored" label */sponsoredBy?: string;}) {
  const fill = percent(item.seatsTaken, item.seatsTotal);
  return (
    <article
      className={`group flex h-full flex-col rounded-2xl border bg-white p-5 shadow-card transition-[border-color,box-shadow,transform] duration-200 ease-out hover:-translate-y-0.5 hover:border-line-strong hover:shadow-lift ${
      sponsoredBy ? 'border-state-warn/30' : 'border-line'}`
      }>
      
      {sponsoredBy ?
      <p className="mb-3 flex items-center justify-between gap-2 border-b border-line pb-2.5 text-caption">
          <span className="rounded-md bg-state-warnBg px-1.5 py-0.5 font-semibold text-state-warn">
            Sponsored
          </span>
          <span className="truncate text-ink-faint">Paid placement by {sponsoredBy}</span>
        </p> :
      reason ?
      <p className="mb-3 border-b border-line pb-2.5 text-caption text-ink-soft">
          <span className="font-medium text-forest">Recommended</span> · {reason}
        </p> :
      null}
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-caption font-medium uppercase tracking-wide text-clay">
            {item.examTarget} · {item.subject}
          </p>
          <h3 className="mt-1.5 text-h3 text-ink">
            <Link to={`/class/${item.id}`} className="hover:text-forest">
              {item.title}
            </Link>
          </h3>
          <p className="mt-1 text-small text-ink-soft">
            {item.institute} · {item.teacher}
          </p>
          {item.instituteVerified ?
          <div className="mt-1.5">
              <VerifiedBadge />
            </div> :
          null}
        </div>
        <ClassStatusBadge status={item.status} />
      </div>

      {!compact ?
      <p className="mt-3 text-small text-ink-soft">{item.summary}</p> :
      null}

      <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2.5 text-small text-ink-soft">
        <div className="flex items-center gap-2">
          <CalendarIcon className="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <dd>
            {item.day} · {item.startTime}
          </dd>
        </div>
        <div className="flex items-center gap-2">
          {item.mode === 'Online' ?
          <MonitorIcon className="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" /> :

          <MapPinIcon className="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
          }
          <dd>{item.city}</dd>
        </div>
        <div className="flex items-center gap-2">
          <UsersIcon className="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
          <dd>
            {item.seatsTaken}/{item.seatsTotal} enrolled
          </dd>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-caption font-medium text-ink-faint">Medium</span>
          <dd>{item.medium}</dd>
        </div>
      </dl>

      <div className="mt-4">
        <div
          className="h-1.5 w-full overflow-hidden rounded-full bg-surface-sunk"
          role="img"
          aria-label={`${fill}% of seats taken`}>
          
          <div
            className={`h-full rounded-full ${fill >= 95 ? 'bg-clay' : 'bg-forest-400'}`}
            style={{ width: `${fill}%` }} />
          
        </div>
      </div>

      <div className="mt-auto flex items-end justify-between gap-3 pt-5">
        <div>
          <p className="text-h3 text-ink">{lkr(item.feeMonthly)}</p>
          <p className="text-caption text-ink-faint">per month · {item.mode}</p>
        </div>
        <Link
          to={`/class/${item.id}`}
          className="inline-flex h-10 items-center rounded-xl border border-line bg-white px-4 text-small font-medium text-ink transition-colors duration-150 ease-out group-hover:border-forest group-hover:bg-forest group-hover:text-white">
          
          View class
        </Link>
      </div>
    </article>);

}

export function ClassCardMini({ item }: {item: TuitionClass;}) {
  return (
    <Link
      to={`/class/${item.id}`}
      className="flex items-center justify-between gap-4 rounded-xl border border-line bg-white px-4 py-3 transition-colors duration-150 ease-out hover:border-line-strong">
      
      <div className="min-w-0">
        <p className="truncate text-small font-medium text-ink">{item.title}</p>
        <p className="truncate text-caption text-ink-soft">
          {item.institute} · {item.day} {item.startTime}
        </p>
      </div>
      <Badge>{lkr(item.feeMonthly)}</Badge>
    </Link>);

}