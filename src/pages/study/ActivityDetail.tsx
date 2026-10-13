import React, { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ChevronLeftIcon, MessagesSquareIcon, UsersIcon } from 'lucide-react';
import { FlexibleShell } from '../../components/app/FlexibleShell';
import { ActivityStatusBadge, Badge } from '../../components/ui/Badge';
import { useAuth } from '../../contexts/AuthContext';
import { Avatar } from '../../components/ui/Avatar';
import { Button, LinkButton } from '../../components/ui/Button';
import { EmptyState, Toast } from '../../components/ui/States';
import { activityById, activities } from '../../data/study';
import { joinLabel } from '../../components/cards/ActivityCard';

export function ActivityDetail() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  const { user, requireAuth, guardianState } = useAuth();
  const activity = activityById(id);
  const [joined, setJoined] = useState(!!user && activity?.status === 'joined');
  const [toast, setToast] = useState(false);

  if (!activity || activity.visibility === 'private' && !user) {
    return (
      <FlexibleShell title="Activity">
        <EmptyState
          icon={<UsersIcon className="h-5 w-5" />}
          title="Activity not found"
          body="This session may have ended or been cancelled by the host."
          action={<LinkButton to="/study">Back to Study</LinkButton>} />
        
      </FlexibleShell>);

  }

  const requestFlow = activity.visibility === 'request';
  const related = activities.filter((a) => a.id !== activity.id && a.subject === activity.subject);

  const onJoin = () => {
    if (joined) {
      navigate(`/messages?thread=${activity.id}`);
      return;
    }
    if (!requireAuth(requestFlow ? 'request' : 'join', `/study?activity=${activity.id}&intent=join`)) return;
    if (guardianState === 'restricted') {
      navigate('/student/connect-guardian');
      return;
    }
    setJoined(true);
    setToast(true);
  };

  return (
    <FlexibleShell
      title={activity.title}
      subtitle={`${activity.dayLabel} · ${activity.timeLabel} · ${activity.areaLabel}`}
      actions={
      <Button variant="ghost" size="sm" onClick={() => navigate('/study')}>
          <ChevronLeftIcon className="h-4 w-4" />
          Back to Study
        </Button>
      }>
      
      <div className="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
        <div className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="neutral">{activity.type}</Badge>
              <Badge>{activity.subject}</Badge>
              <Badge>{activity.examTarget}</Badge>
              <ActivityStatusBadge status={joined ? 'joined' : activity.status} />
            </div>
            <h2 className="mt-4 text-h2 text-ink">About</h2>
            <p className="mt-2 text-body text-ink-soft">{activity.description}</p>

            <dl className="mt-6 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-3">
              <Fact label="When" value={`${activity.dayLabel} · ${activity.timeLabel}`} detail={activity.durationLabel} />
              <Fact label="Where" value={activity.areaLabel} detail={`${activity.venueType} · exact point shared after joining`} />
              <Fact label="Going" value={`${activity.going} / ${activity.capacity}`} detail={requestFlow ? 'Host approves requests' : 'Open to join'} />
            </dl>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Participants</h2>
            <ul className="mt-4 grid gap-3 sm:grid-cols-2">
              {activity.participants.map((p) =>
              <li key={p.name} className="flex items-center gap-3 rounded-xl border border-line px-3.5 py-3">
                  <Avatar initials={p.initials} size="sm" />
                  <div className="min-w-0">
                    <p className="truncate text-small font-medium text-ink">{p.name}</p>
                    <p className="truncate text-caption text-ink-soft">{p.detail}</p>
                  </div>
                </li>
              )}
            </ul>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-h3 text-ink">Group chat</h2>
              {!joined ? <Badge tone="neutral">Visible after you join</Badge> : null}
            </div>
            <div className={`mt-4 space-y-3 ${joined ? '' : 'pointer-events-none select-none opacity-45'}`}>
              {activity.chatPreview.map((m, i) =>
              <div key={i} className="flex gap-3">
                  <Avatar initials={m.initials} size="xs" />
                  <div>
                    <p className="text-caption text-ink-faint">
                      {m.author} · {m.time}
                    </p>
                    <p className="text-small text-ink">{m.text}</p>
                  </div>
                </div>
              )}
            </div>
            <div className="mt-4">
              <LinkButton
                to={`/messages?thread=${activity.id}`}
                variant={joined ? 'primary' : 'secondary'}
                size="sm">
                
                <MessagesSquareIcon className="h-4 w-4" />
                Open group chat
              </LinkButton>
            </div>
          </section>
        </div>

        <aside className="space-y-4 lg:sticky lg:top-24">
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <p className="text-caption uppercase tracking-wide text-ink-faint">Host</p>
            <div className="mt-2 flex items-center gap-3">
              <Avatar initials={activity.host.name.slice(0, 2).toUpperCase()} />
              <div>
                <p className="text-small font-medium text-ink">{activity.host.name}</p>
                <p className="text-caption text-ink-soft">{activity.host.detail}</p>
              </div>
            </div>
            {activity.host.verified ?
            <div className="mt-3">
                <Badge tone="success">Verified student</Badge>
              </div> :
            null}

            <div className="mt-5 space-y-2">
              <Button
                className="w-full"
                onClick={onJoin}
                disabled={activity.status === 'full' && !joined}
                variant={joined ? 'secondary' : 'primary'}>
                
                {joined ?
                requestFlow ?
                'Request sent' :
                'Joined · open group chat' :
                joinLabel(activity)}
              </Button>
              <div className="flex gap-2">
                <Button variant="secondary" size="sm" className="flex-1">
                  Share
                </Button>
                <Button variant="secondary" size="sm" className="flex-1">
                  Report
                </Button>
              </div>
            </div>

            <p className="mt-4 text-caption text-ink-faint">
              Participant limit set by the host. You can leave any time, and the host
              can remove anyone from the session.
            </p>
          </section>

          {related.length ?
          <section className="rounded-2xl border border-line bg-white p-5">
              <h2 className="text-h3 text-ink">More {activity.subject}</h2>
              <ul className="mt-3 space-y-2">
                {related.map((r) =>
              <li key={r.id}>
                    <Link
                  to={`/study/activity/${r.id}`}
                  className="block rounded-xl border border-line px-3.5 py-2.5 text-small text-ink transition-colors duration-150 ease-out hover:border-line-strong">
                  
                      {r.title}
                      <span className="block text-caption text-ink-soft">
                        {r.dayLabel} · {r.areaLabel}
                      </span>
                    </Link>
                  </li>
              )}
              </ul>
            </section> :
          null}
        </aside>
      </div>

      <Toast
        open={toast}
        message={requestFlow ? 'Request sent to the host' : 'You joined this session'}
        detail={
        requestFlow ?
        'You will get a notification when the host responds.' :
        'The group chat and exact meeting point are now available.'
        }
        onClose={() => setToast(false)} />
      
    </FlexibleShell>);

}

function Fact({ label, value, detail }: {label: string;value: string;detail: string;}) {
  return (
    <div className="bg-white px-5 py-4">
      <dt className="text-caption uppercase tracking-wide text-ink-faint">{label}</dt>
      <dd className="mt-1 text-small font-medium text-ink">{value}</dd>
      <p className="mt-0.5 text-caption text-ink-soft">{detail}</p>
    </div>);

}