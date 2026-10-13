import React from 'react';
import { ClockIcon, MapPinIcon, UsersIcon } from 'lucide-react';
import type { StudyActivity } from '../../types';
import { ActivityStatusBadge, Badge } from '../ui/Badge';
import { AvatarStack } from '../ui/Avatar';

export const activityAccent: Record<string, string> = {
  'Study Together': 'bg-forest',
  'Peer Teaching': 'bg-clay',
  'Past Paper Discussion': 'bg-state-info',
  'Practice Session': 'bg-forest-400',
  Discussion: 'bg-state-warn',
  'Project / Knowledge Sharing': 'bg-ink',
  'Online Study Session': 'bg-state-info'
};

export function joinLabel(activity: StudyActivity): string {
  if (activity.status === 'joined') return 'Open group chat';
  if (activity.status === 'full') return 'Activity full';
  if (activity.status === 'request-pending') return 'Request sent';
  if (activity.visibility === 'request') return 'Request to join';
  if (activity.visibility === 'private') return 'Invite only';
  return 'Join';
}

export function ActivityCard({
  activity,
  onOpen,
  selected = false




}: {activity: StudyActivity;onOpen: (id: string) => void;selected?: boolean;}) {
  return (
    <article
      className={`rounded-2xl border bg-white p-4 shadow-card transition-[border-color,box-shadow] duration-200 ease-out ${
      selected ? 'border-forest ring-1 ring-forest/20' : 'border-line hover:border-line-strong'}`
      }>
      
      <div className="flex items-start gap-3">
        <span
          className={`mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ${activityAccent[activity.type] ?? 'bg-forest'}`}
          aria-hidden="true" />
        
        <div className="min-w-0 flex-1">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <p className="text-caption font-medium uppercase tracking-wide text-ink-faint">
                {activity.type}
              </p>
              <h3 className="mt-1 text-h3 text-ink">
                <button
                  onClick={() => onOpen(activity.id)}
                  className="text-left hover:text-forest">
                  
                  {activity.title}
                </button>
              </h3>
            </div>
            <ActivityStatusBadge status={activity.status} />
          </div>

          <dl className="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-small text-ink-soft">
            <div className="flex items-center gap-1.5">
              <ClockIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
              <dd>
                {activity.dayLabel} · {activity.timeLabel}
              </dd>
            </div>
            <div className="flex items-center gap-1.5">
              <MapPinIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
              <dd>{activity.areaLabel}</dd>
            </div>
            <div className="flex items-center gap-1.5">
              <UsersIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
              <dd>
                {activity.going} / {activity.capacity} going
              </dd>
            </div>
          </dl>

          <div className="mt-4 flex items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <AvatarStack people={activity.participants} total={activity.going} />
              <Badge>{activity.examTarget}</Badge>
            </div>
            <button
              onClick={() => onOpen(activity.id)}
              className="inline-flex h-9 items-center rounded-xl border border-forest bg-forest px-3.5 text-small font-medium text-white transition-colors duration-150 ease-out hover:bg-forest-600 disabled:border-line disabled:bg-surface-muted disabled:text-ink-faint"
              disabled={activity.status === 'full' || activity.status === 'cancelled'}>
              
              {joinLabel(activity)}
            </button>
          </div>
        </div>
      </div>
    </article>);

}