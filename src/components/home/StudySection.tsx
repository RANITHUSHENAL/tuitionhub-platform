import React from 'react';
import { useNavigate } from 'react-router-dom';
import { MessagesSquareIcon, SearchIcon, UserPlusIcon, UsersIcon } from 'lucide-react';
import { activities } from '../../data/study';
import { ActivityCard } from '../cards/ActivityCard';
import { LinkButton } from '../ui/Button';

const flow = [
{ icon: <SearchIcon className="h-4 w-4" />, label: 'Discover', detail: 'Map or list, near you' },
{ icon: <UserPlusIcon className="h-4 w-4" />, label: 'Join', detail: 'Open or host-approved' },
{ icon: <MessagesSquareIcon className="h-4 w-4" />, label: 'Chat', detail: 'Group chat for that session' },
{ icon: <UsersIcon className="h-4 w-4" />, label: 'Study', detail: 'Together, then again next week' }];


export function StudySection() {
  const navigate = useNavigate();
  const featured = [activities[0], activities[1], activities[2]];

  return (
    <section className="border-b border-line/60 bg-forest text-white">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <div className="flex flex-wrap items-end justify-between gap-6">
          <div className="max-w-2xl">
            <p className="text-caption font-medium uppercase tracking-wide text-forest-100">
              Study · new in TuitionHub
            </p>
            <h2 className="mt-2 text-h1 text-white">Don't always study alone.</h2>
            <p className="mt-3 text-body text-forest-100">
              Find students learning the same thing, join a study session, exchange
              knowledge, and build your learning circle. Study sessions are created by
              students, for a subject and an exam year — not a feed to scroll.
            </p>
          </div>
          <LinkButton
            to="/study"
            variant="secondary"
            className="border-transparent bg-white/10 text-white hover:bg-white/20">
            
            Open the Study map
          </LinkButton>
        </div>

        <div className="mt-10 grid gap-5 lg:grid-cols-3">
          {featured.map((a) =>
          <div key={a.id} className="text-ink">
              <ActivityCard activity={a} onOpen={(id) => navigate(`/study/activity/${id}`)} />
            </div>
          )}
        </div>

        <ol className="mt-10 grid gap-px overflow-hidden rounded-2xl border border-white/15 bg-white/15 sm:grid-cols-2 lg:grid-cols-4">
          {flow.map((f) =>
          <li key={f.label} className="bg-forest px-5 py-4">
              <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-white">
                {f.icon}
              </span>
              <p className="mt-3 text-h3 text-white">{f.label}</p>
              <p className="text-small text-forest-100">{f.detail}</p>
            </li>
          )}
        </ol>

        <p className="mt-5 max-w-3xl text-caption text-forest-100">
          Study activities show an approximate area only, participant limits are set by
          the host, and every activity has report and block controls. Study Circles keep
          a group going after a single session ends.
        </p>
      </div>
    </section>);

}