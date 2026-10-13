import React from 'react';
import { Link } from 'react-router-dom';
import {
  BanIcon,
  ClockIcon,
  FlagIcon,
  MapPinIcon,
  MessageSquareIcon,
  Share2Icon,
  UsersIcon } from
'lucide-react';
import type { StudyActivity } from '../../types';
import { ActivityStatusBadge, Badge } from '../ui/Badge';
import { Avatar } from '../ui/Avatar';
import { Button, LinkButton } from '../ui/Button';
import { joinLabel } from '../cards/ActivityCard';

export function ActivityDetailBody({
  activity,
  joined,
  onJoin,
  showChatPreview = true





}: {activity: StudyActivity;joined: boolean;onJoin: () => void;showChatPreview?: boolean;}) {
  const requestFlow = activity.visibility === 'request';
  const disabled = activity.status === 'full' || activity.status === 'cancelled';

  return (
    <div className="space-y-6">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone="neutral">{activity.type}</Badge>
          <ActivityStatusBadge status={joined ? 'joined' : activity.status} />
          <Badge>{activity.examTarget}</Badge>
        </div>
        <h3 className="mt-3 text-h2 text-ink">{activity.title}</h3>
        <dl className="mt-3 space-y-2 text-small text-ink-soft">
          <div className="flex items-center gap-2">
            <ClockIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
            <dd>
              {activity.dayLabel} · {activity.timeLabel} · {activity.durationLabel}
            </dd>
          </div>
          <div className="flex items-center gap-2">
            <MapPinIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
            <dd>
              {activity.areaLabel} · {activity.venueType}
            </dd>
          </div>
          <div className="flex items-center gap-2">
            <UsersIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
            <dd>
              {activity.going} / {activity.capacity} going ·{' '}
              {activity.visibility === 'public' ?
              'Anyone can join' :
              activity.visibility === 'request' ?
              'Host approves requests' :
              'Invite only'}
            </dd>
          </div>
        </dl>
      </div>

      <section>
        <h4 className="text-caption font-semibold uppercase tracking-wide text-ink-faint">
          About
        </h4>
        <p className="mt-2 text-small text-ink-soft">{activity.description}</p>
      </section>

      <section>
        <h4 className="text-caption font-semibold uppercase tracking-wide text-ink-faint">
          Host
        </h4>
        <div className="mt-2 flex items-center gap-3 rounded-xl border border-line px-3.5 py-3">
          <Avatar initials={activity.host.name.slice(0, 2).toUpperCase()} />
          <div className="min-w-0">
            <p className="text-small font-medium text-ink">{activity.host.name}</p>
            <p className="text-caption text-ink-soft">{activity.host.detail}</p>
          </div>
          {activity.host.verified ?
          <Badge tone="success" className="ml-auto">
              Verified student
            </Badge> :
          null}
        </div>
      </section>

      <section>
        <h4 className="text-caption font-semibold uppercase tracking-wide text-ink-faint">
          Participants
        </h4>
        <ul className="mt-2 space-y-2">
          {activity.participants.map((p) =>
          <li key={p.name} className="flex items-center gap-3">
              <Avatar initials={p.initials} size="sm" />
              <span className="text-small text-ink">{p.name}</span>
              <span className="text-caption text-ink-faint">{p.detail}</span>
            </li>
          )}
        </ul>
      </section>

      {showChatPreview && (joined || activity.status === 'joined') ?
      <section>
          <h4 className="text-caption font-semibold uppercase tracking-wide text-ink-faint">
            Group chat
          </h4>
          <div className="mt-2 space-y-2 rounded-xl border border-line bg-surface-muted p-3">
            {activity.chatPreview.slice(0, 3).map((m, i) =>
          <p key={i} className="text-small text-ink-soft">
                <span className="font-medium text-ink">{m.author}:</span> {m.text}
              </p>
          )}
          </div>
          <LinkButton
          to={`/messages?thread=${activity.id}`}
          variant="secondary"
          size="sm"
          className="mt-3 w-full">
          
            <MessageSquareIcon className="h-4 w-4" />
            Open group chat
          </LinkButton>
        </section> :
      null}

      <div className="space-y-2 border-t border-line pt-4">
        <Button
          className="w-full"
          onClick={onJoin}
          disabled={disabled}
          variant={joined ? 'secondary' : 'primary'}>
          
          {joined ?
          requestFlow ?
          'Request sent — waiting for host' :
          'Joined · open group chat' :
          joinLabel(activity)}
        </Button>
        <div className="flex gap-2">
          <Button variant="secondary" size="sm" className="flex-1">
            <Share2Icon className="h-4 w-4" />
            Share
          </Button>
          <Button variant="secondary" size="sm" className="flex-1">
            <FlagIcon className="h-4 w-4" />
            Report
          </Button>
          <Button variant="secondary" size="sm" className="flex-1">
            <BanIcon className="h-4 w-4" />
            Block host
          </Button>
        </div>
        <p className="pt-1 text-caption text-ink-faint">
          Hosts can remove participants and close joining at any time.{' '}
          <Link to="/blueprint#safety" className="underline hover:text-ink-soft">
            Safety in Study
          </Link>
        </p>
      </div>
    </div>);

}