import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, MegaphoneIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Badge, PaymentBadge } from '../../components/ui/Badge';
import { LinkButton } from '../../components/ui/Button';
import { PrototypeNote } from '../../components/ui/States';
import { ParentSetupCard } from '../../components/parent/ParentSetupCard';
import { useAuth } from '../../contexts/AuthContext';
import {
  announcements,
  invoices,
  marks,
  parentProfile,
  upcomingSessions } from
'../../data/people';
import { lkr } from '../../utils/format';

export function ParentOverview() {
  const { user } = useAuth();
  const [childIndex, setChildIndex] = useState(0);

  // New parent: no child yet. They can explore; the setup card guides the rest.
  if (user && (user.childrenLinked ?? 0) === 0) {
    return (
      <AppShell
        role="Parent"
        accountName={user.name}
        accountDetail={user.detail}
        initials={user.initials}
        nav={parentNav}
        title={`Hi ${user.name.split(' ')[0]}`}
        subtitle="Your parent account is ready.">
        
        <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
          <ParentSetupCard welcome />
          <aside className="space-y-4">
            <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
              <h2 className="text-h3 text-ink">While you set up</h2>
              <p className="mt-2 text-small text-ink-soft">
                Browse classes, institutes and teachers. You can save classes now and enrol once
                your child is connected.
              </p>
              <LinkButton to="/search" size="sm" variant="secondary" className="mt-4">
                Find a class
              </LinkButton>
            </section>
            <section className="rounded-2xl border border-line bg-surface-muted p-6">
              <h2 className="text-h3 text-ink">After you connect a child</h2>
              <p className="mt-2 text-small text-ink-soft">
                This page shows their next class, attendance, latest marks and any fees due —
                for every child you add.
              </p>
            </section>
          </aside>
        </div>
      </AppShell>);

  }

  const child = parentProfile.children[childIndex];
  const due = invoices.filter((i) => i.status === 'pending' || i.status === 'failed');
  const nextSession = upcomingSessions[0];
  const latestMark = marks[0];

  return (
    <AppShell
      role="Parent"
      accountName={parentProfile.name}
      accountDetail="2 children · Nugegoda"
      initials="CP"
      nav={parentNav}
      title="Good evening, Mrs. Perera"
      subtitle="Here is where each child stands tonight."
      actions={
      <div className="flex rounded-xl border border-line bg-white p-1">
          {parentProfile.children.map((c, i) =>
        <button
          key={c.id}
          onClick={() => setChildIndex(i)}
          aria-pressed={i === childIndex}
          className={`rounded-lg px-3 py-1.5 text-small font-medium transition-colors duration-150 ease-out ${
          i === childIndex ? 'bg-forest text-white' : 'text-ink-soft hover:text-ink'}`
          }>
          
              {c.name.split(' ')[0]}
            </button>
        )}
        </div>
      }>
      
      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-6">
          <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
              <div>
                <h2 className="text-h3 text-ink">{child.name}</h2>
                <p className="text-caption text-ink-soft">
                  {child.grade} · {child.examTarget} · {child.school}
                </p>
              </div>
              <Badge tone="neutral">{child.classesEnrolled} classes enrolled</Badge>
            </div>

            <div className="grid gap-px bg-line sm:grid-cols-3">
              <Stat label="Next class" value={nextSession.className.split('—')[0].trim()} detail={nextSession.when} />
              <Stat label="Attendance this term" value={`${child.attendanceRate}%`} detail="2 sessions missed" />
              <Stat
                label="Latest result"
                value={`${latestMark.score} / ${latestMark.outOf}`}
                detail={`${latestMark.assessment} · avg ${latestMark.classAverage}`} />
              
            </div>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex items-center justify-between gap-4">
              <h2 className="text-h3 text-ink">Needs your attention</h2>
              <Link to="/parent/fees" className="text-small font-medium text-forest">
                All fees
              </Link>
            </div>
            <ul className="mt-4 space-y-3">
              {due.map((inv) =>
              <li
                key={inv.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">
                
                  <div className="min-w-0">
                    <p className="text-small font-medium text-ink">{inv.className}</p>
                    <p className="text-caption text-ink-soft">
                      {inv.month} · {inv.institute} · {lkr(inv.amount)}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <PaymentBadge status={inv.status} />
                    <LinkButton to="/parent/fees" size="sm">
                      {inv.status === 'failed' ? 'Retry payment' : 'Pay now'}
                    </LinkButton>
                  </div>
                </li>
              )}
            </ul>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Upcoming classes</h2>
            <ul className="mt-4 divide-y divide-line">
              {upcomingSessions.map((s) =>
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <div>
                    <p className="text-small font-medium text-ink">{s.className}</p>
                    <p className="text-caption text-ink-soft">
                      {s.institute} · {s.where}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="text-small text-ink">{s.when}</p>
                    <p className="text-caption text-ink-faint">
                      {s.mode}
                      {s.link ? ` · ${s.link}` : ''}
                    </p>
                  </div>
                </li>
              )}
            </ul>
          </section>
        </div>

        <div className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex items-center gap-2">
              <MegaphoneIcon className="h-4 w-4 text-forest" aria-hidden="true" />
              <h2 className="text-h3 text-ink">Announcements</h2>
            </div>
            <ul className="mt-4 space-y-4">
              {announcements.map((a) =>
              <li key={a.id}>
                  <p className="text-small font-medium text-ink">{a.title}</p>
                  <p className="mt-0.5 text-caption text-ink-soft">{a.body}</p>
                  <p className="mt-1 text-caption text-ink-faint">
                    {a.from} · {a.time} · {a.channel}
                  </p>
                </li>
              )}
            </ul>
            <Link
              to="/parent/announcements"
              className="mt-4 inline-flex items-center gap-1.5 text-small font-medium text-forest">
              
              All announcements
              <ArrowRightIcon className="h-3.5 w-3.5" />
            </Link>
          </section>

          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <h2 className="text-h3 text-ink">How TuitionHub shows information</h2>
            <p className="mt-2 text-small text-ink-soft">
              You see attendance, marks and payments for the classes you pay for. You do
              not see your child's location, messages, or Study activity.
            </p>
            <PrototypeNote>
              Children, classes and figures on this account are fictional.
            </PrototypeNote>
          </section>
        </div>
      </div>
    </AppShell>);

}

function Stat({ label, value, detail }: {label: string;value: string;detail: string;}) {
  return (
    <div className="bg-white px-6 py-5">
      <p className="text-caption uppercase tracking-wide text-ink-faint">{label}</p>
      <p className="mt-1.5 text-h2 text-ink">{value}</p>
      <p className="mt-1 text-caption text-ink-soft">{detail}</p>
    </div>);

}