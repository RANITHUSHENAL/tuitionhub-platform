import React, { useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { FileTextIcon, PlusIcon, UsersIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { Tabs } from '../../components/ui/Tabs';
import { Badge } from '../../components/ui/Badge';
import { Avatar, AvatarStack } from '../../components/ui/Avatar';
import { Button, LinkButton } from '../../components/ui/Button';
import { EmptyState } from '../../components/ui/States';
import { ActivityCard } from '../../components/cards/ActivityCard';
import { activities, circleById } from '../../data/study';
import { studentProfile } from '../../data/people';

const tabs = [
{ id: 'overview', label: 'Overview' },
{ id: 'discussions', label: 'Discussions' },
{ id: 'activities', label: 'Activities' },
{ id: 'resources', label: 'Resources' },
{ id: 'members', label: 'Members' }];


const discussions = [
{
  id: 'd1',
  title: 'Stationary waves — why does the node spacing halve?',
  author: 'Hasini',
  initials: 'HW',
  replies: 7,
  time: '2 hours ago',
  tag: 'Doubt'
},
{
  id: 'd2',
  title: 'Best order to revise Unit 6 before the model paper',
  author: 'Nimal',
  initials: 'NP',
  replies: 12,
  time: 'Yesterday',
  tag: 'Discussion'
},
{
  id: 'd3',
  title: 'Sharing my summary sheet for electric fields',
  author: 'Thilina',
  initials: 'TS',
  replies: 4,
  time: '2 days ago',
  tag: 'Resource'
}];


const resources = [
{ id: 'r1', name: 'Unit 6 — Waves summary sheet', by: 'Thilina', kind: 'PDF · 2 pages' },
{ id: 'r2', name: 'Electric fields worked problems', by: 'Nimal', kind: 'PDF · 6 pages' },
{ id: 'r3', name: 'Model paper answer checklist', by: 'Ayesha', kind: 'Doc' }];


const members = [
{ name: 'Nimal Perera', initials: 'NP', role: 'Host · A/L 2027' },
{ name: 'Ayesha Rajapaksa', initials: 'AR', role: 'A/L 2027 · Maths' },
{ name: 'Thilina Samarasinghe', initials: 'TS', role: 'A/L 2027 · Physics' },
{ name: 'Hasini Weerasinghe', initials: 'HW', role: 'A/L 2027 · Physics' },
{ name: 'Ruwan Fernando', initials: 'RF', role: 'A/L 2027 · Maths' },
{ name: 'Kasun Perera', initials: 'KP', role: 'You · A/L 2027' }];


export function CircleDetail() {
  const { id = '' } = useParams();
  const navigate = useNavigate();
  const circle = circleById(id);
  const [tab, setTab] = useState('overview');

  if (!circle) {
    return (
      <AppShell
        role="Student"
        accountName={studentProfile.name}
        accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
        initials={studentProfile.initials}
        nav={studentNav}
        title="Study Circle">
        
        <EmptyState
          icon={<UsersIcon className="h-5 w-5" />}
          title="Circle not found"
          body="This circle may have been closed by its host."
          action={<LinkButton to="/study">Back to Study</LinkButton>} />
        
      </AppShell>);

  }

  const circleActivities = activities.filter((a) => a.subject === circle.subject);

  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title={circle.name}
      subtitle={`${circle.members} members · ${circle.area} · hosted by ${circle.verifiedHost}`}
      actions={
      <Button size="sm" onClick={() => navigate('/study/create')}>
          <PlusIcon className="h-4 w-4" />
          Create study session
        </Button>
      }>
      
      <Tabs items={tabs} active={tab} onChange={setTab} ariaLabel="Circle sections" />

      <div className="mt-6">
        {tab === 'overview' ?
        <div className="grid gap-6 lg:grid-cols-[1.4fr_0.6fr]">
            <div className="space-y-6">
              <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
                <h2 className="text-h3 text-ink">About this circle</h2>
                <p className="mt-2 text-small text-ink-soft">{circle.description}</p>
                <div className="mt-4 flex flex-wrap gap-2">
                  <Badge>{circle.subject}</Badge>
                  <Badge>{circle.examTarget}</Badge>
                  <Badge tone="success">{circle.activitiesThisWeek} sessions this week</Badge>
                </div>
              </section>

              <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
                <h2 className="text-h3 text-ink">Next sessions</h2>
                <div className="mt-4 space-y-4">
                  {circleActivities.slice(0, 2).map((a) =>
                <ActivityCard
                  key={a.id}
                  activity={a}
                  onOpen={(aid) => navigate(`/study/activity/${aid}`)} />

                )}
                </div>
              </section>
            </div>

            <aside className="space-y-4">
              <section className="rounded-2xl border border-line bg-white p-5">
                <h2 className="text-h3 text-ink">Members</h2>
                <div className="mt-3">
                  <AvatarStack people={members} max={5} total={circle.members} />
                </div>
                <LinkButton
                to={`/messages?thread=${circle.id}`}
                variant="secondary"
                size="sm"
                className="mt-4 w-full">
                
                  Open circle chat
                </LinkButton>
              </section>
              <section className="rounded-2xl border border-line bg-surface-muted p-5">
                <h2 className="text-h3 text-ink">How circles work</h2>
                <p className="mt-2 text-small text-ink-soft">
                  A circle is a standing group for one subject and exam year. Sessions
                  are created inside it, discussion stays in one place, and notes shared
                  by members build up over the year.
                </p>
              </section>
            </aside>
          </div> :
        null}

        {tab === 'discussions' ?
        <ul className="max-w-3xl space-y-3">
            {discussions.map((d) =>
          <li
            key={d.id}
            className="flex items-start gap-3 rounded-2xl border border-line bg-white px-5 py-4 shadow-card">
            
                <Avatar initials={d.initials} size="sm" />
                <div className="min-w-0 flex-1">
                  <p className="text-small font-medium text-ink">{d.title}</p>
                  <p className="mt-0.5 text-caption text-ink-soft">
                    {d.author} · {d.time} · {d.replies} replies
                  </p>
                </div>
                <Badge tone="neutral">{d.tag}</Badge>
              </li>
          )}
          </ul> :
        null}

        {tab === 'activities' ?
        <div className="grid gap-4 lg:grid-cols-2">
            {circleActivities.map((a) =>
          <ActivityCard
            key={a.id}
            activity={a}
            onOpen={(aid) => navigate(`/study/activity/${aid}`)} />

          )}
          </div> :
        null}

        {tab === 'resources' ?
        <ul className="max-w-3xl space-y-3">
            {resources.map((r) =>
          <li
            key={r.id}
            className="flex items-center gap-3 rounded-2xl border border-line bg-white px-5 py-4">
            
                <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-forest-50 text-forest">
                  <FileTextIcon className="h-4 w-4" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-small font-medium text-ink">{r.name}</p>
                  <p className="text-caption text-ink-soft">
                    Shared by {r.by} · {r.kind}
                  </p>
                </div>
                <Button variant="secondary" size="sm">
                  Open
                </Button>
              </li>
          )}
            <li className="rounded-2xl border border-dashed border-line-strong px-5 py-4 text-caption text-ink-soft">
              Members share their own notes here. Copyrighted past papers are not hosted
              on TuitionHub.
            </li>
          </ul> :
        null}

        {tab === 'members' ?
        <ul className="grid max-w-3xl gap-3 sm:grid-cols-2">
            {members.map((m) =>
          <li
            key={m.name}
            className="flex items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3">
            
                <Avatar initials={m.initials} size="sm" />
                <div className="min-w-0">
                  <p className="truncate text-small font-medium text-ink">{m.name}</p>
                  <p className="truncate text-caption text-ink-soft">{m.role}</p>
                </div>
              </li>
          )}
          </ul> :
        null}
      </div>
    </AppShell>);

}