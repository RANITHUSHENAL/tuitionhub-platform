import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { ListIcon, MapIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { FlexibleShell } from '../../components/app/FlexibleShell';
import { SegmentedControl, Tabs } from '../../components/ui/Tabs';
import { Button, LinkButton } from '../../components/ui/Button';
import { useAuth } from '../../contexts/AuthContext';
import type { StudyActivity } from '../../types';
import { Badge } from '../../components/ui/Badge';
import { EmptyState, PrototypeNote } from '../../components/ui/States';
import { DetailPanel, DockedPanel } from '../../components/ui/DetailPanel';
import { ActivityCard } from '../../components/cards/ActivityCard';
import { ActivityDetailBody } from '../../components/study/ActivityDetailBody';
import { MapLegend, StudyMap } from '../../components/study/StudyMap';
import { activities, circles } from '../../data/study';

const filters = [
{ id: 'all', label: 'All' },
{ id: 'today', label: 'Today' },
{ id: 'online', label: 'Online' },
{ id: 'teaching', label: 'Peer teaching' },
{ id: 'papers', label: 'Past papers' }];


export function StudyDiscover() {
  const navigate = useNavigate();
  const { user, requireAuth, guardianState } = useAuth();
  const [params, setParams] = useSearchParams();
  const [view, setView] = useState<'map' | 'list'>('map');
  const [filter, setFilter] = useState('all');
  const [query, setQuery] = useState('');
  const [selectedId, setSelectedId] = useState<string | null>(params.get('activity'));
  const [joined, setJoined] = useState<string[]>([]);

  const join = (a: StudyActivity) => {
    if (joined.includes(a.id)) {
      if (!requireAuth('message', `/messages?thread=${a.id}`)) return;
      navigate(`/messages?thread=${a.id}`);
      return;
    }
    const intent = a.visibility === 'request' ? 'request' : 'join';
    if (!requireAuth(intent, `/study?activity=${a.id}&intent=join`)) return;
    if (guardianState === 'restricted') {
      navigate('/student/connect-guardian');
      return;
    }
    setJoined((j) => [...j, a.id]);
  };

  const create = () => {
    if (!requireAuth('create', '/study/create')) return;
    navigate('/study/create');
  };

  // Resume a join that started as a guest.
  useEffect(() => {
    const id = params.get('activity');
    if (params.get('intent') === 'join' && id && user) {
      setJoined((j) => j.includes(id) ? j : [...j, id]);
      params.delete('intent');
      setParams(params, { replace: true });
    }
  }, [params, setParams, user]);

  const results = useMemo(
    () =>
    activities.filter((a) => {
      // Invite-only sessions never appear in public discovery.
      if (a.visibility === 'private' && !user) return false;
      if (query && !`${a.title} ${a.subject} ${a.type}`.toLowerCase().includes(query.toLowerCase()))
      return false;
      if (filter === 'today') return a.dayLabel === 'Today';
      if (filter === 'online') return a.venueType === 'Online';
      if (filter === 'teaching') return a.type === 'Peer Teaching';
      if (filter === 'papers') return a.type === 'Past Paper Discussion';
      return true;
    }),
    [query, filter, user]
  );

  const selected = activities.find((a) => a.id === selectedId) ?? null;

  return (
    <FlexibleShell
      title="Study"
      subtitle="What can I learn or study with other people right now?"
      actions={
      <Button size="sm" onClick={create}>
          <PlusIcon className="h-4 w-4" />
          Create activity
        </Button>
      }>
      
      {!user ?
      <p className="mb-4 rounded-xl border border-line bg-white px-4 py-3 text-small text-ink-soft">
          You’re exploring public study sessions as a guest. Areas are approximate — exact
          meeting points are shared only with people who join. Sign in to join, request
          or message.
        </p> :
      null}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex min-w-[15rem] flex-1 items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5 sm:max-w-sm">
          <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
          <input
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Subject, topic or activity"
            aria-label="Search study activities"
            className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
          
        </div>
        <SegmentedControl
          ariaLabel="Study view"
          value={view}
          onChange={setView}
          options={[
          { value: 'map', label: 'Map', icon: <MapIcon className="h-4 w-4" /> },
          { value: 'list', label: 'List', icon: <ListIcon className="h-4 w-4" /> }]
          } />
        
      </div>

      <div className="mt-4">
        <Tabs items={filters} active={filter} onChange={setFilter} ariaLabel="Activity filters" />
      </div>

      {view === 'map' ?
      <div className="mt-6 grid gap-5 lg:grid-cols-[1.45fr_0.55fr]">
          <div className="space-y-3">
            <div className="h-[60vh] min-h-[440px]">
              <StudyMap
              activities={results}
              selectedId={selectedId}
              onSelect={(id) => setSelectedId(id)} />
            
            </div>
            <MapLegend />
          </div>

          {selected ?
        <DockedPanel title="Activity" onClose={() => setSelectedId(null)}>
              <ActivityDetailBody
            activity={selected}
            joined={joined.includes(selected.id)}
            onJoin={() => join(selected)} />
          
              <div className="mt-4">
                <LinkButton
              to={`/study/activity/${selected.id}`}
              variant="ghost"
              size="sm"
              className="w-full">
              
                  Open full activity page
                </LinkButton>
              </div>
            </DockedPanel> :

        <aside className="hidden rounded-2xl border border-line bg-white p-5 lg:block">
              <h2 className="text-h3 text-ink">Pick an activity</h2>
              <p className="mt-1.5 text-small text-ink-soft">
                Tap a marker to see what the session covers, who is going, and how to
                join. Nothing on this map shows a person's live location.
              </p>
              <div className="mt-5 space-y-3">
                <p className="text-caption uppercase tracking-wide text-ink-faint">
                  Your circles
                </p>
                {circles.slice(0, 2).map((c) =>
            <Link
              key={c.id}
              to={`/study/circles/${c.id}`}
              className="block rounded-xl border border-line px-3.5 py-3 transition-colors duration-150 ease-out hover:border-line-strong">
              
                    <p className="text-small font-medium text-ink">{c.name}</p>
                    <p className="text-caption text-ink-soft">
                      {c.members} members · {c.activitiesThisWeek} sessions this week
                    </p>
                  </Link>
            )}
              </div>
              <div className="mt-5">
                <PrototypeNote>Activities and hosts are fictional.</PrototypeNote>
              </div>
            </aside>
        }
        </div> :

      <div className="mt-6 grid gap-5 lg:grid-cols-[1.4fr_0.6fr]">
          <div className="space-y-4">
            {results.length === 0 ?
          <EmptyState
            icon={<SearchIcon className="h-5 w-5" />}
            title="No sessions match yet"
            body="Nothing is running for that filter right now. Create the session you were looking for — students studying the same subject will see it."
            action={
            <Button onClick={create}>Create activity</Button>
            } /> :


          results.map((a) =>
          <ActivityCard
            key={a.id}
            activity={a}
            selected={a.id === selectedId}
            onOpen={(id) => setSelectedId(id)} />

          )
          }
          </div>

          <aside className="space-y-4">
            <section className="rounded-2xl border border-line bg-white p-5">
              <h2 className="text-h3 text-ink">Study Circles</h2>
              <p className="mt-1 text-small text-ink-soft">
                Activities end. Circles keep going.
              </p>
              <ul className="mt-4 space-y-3">
                {circles.map((c) =>
              <li key={c.id}>
                    <Link
                  to={`/study/circles/${c.id}`}
                  className="block rounded-xl border border-line px-3.5 py-3 transition-colors duration-150 ease-out hover:border-line-strong">
                  
                      <p className="text-small font-medium text-ink">{c.name}</p>
                      <p className="mt-0.5 text-caption text-ink-soft">
                        {c.members} members · {c.area}
                      </p>
                      <div className="mt-2">
                        <Badge>{c.activitiesThisWeek} sessions this week</Badge>
                      </div>
                    </Link>
                  </li>
              )}
              </ul>
            </section>
          </aside>
        </div>
      }

      <DetailPanel
        open={selected !== null}
        onClose={() => setSelectedId(null)}
        title="Activity">
        
        {selected ?
        <ActivityDetailBody
          activity={selected}
          joined={joined.includes(selected.id)}
          onJoin={() => join(selected)} /> :

        null}
      </DetailPanel>
    </FlexibleShell>);

}