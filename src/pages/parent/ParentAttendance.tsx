import React, { useState } from 'react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { AttendanceBadge, Badge } from '../../components/ui/Badge';
import { Tabs } from '../../components/ui/Tabs';
import { PrototypeNote } from '../../components/ui/States';
import { attendanceRecords, parentProfile } from '../../data/people';

const filters = [
{ id: 'all', label: 'All sessions' },
{ id: 'missed', label: 'Missed only' },
{ id: 'physics', label: 'A/L Physics' }];


export function ParentAttendance() {
  const [tab, setTab] = useState('all');

  const rows = attendanceRecords.filter((r) => {
    if (tab === 'missed') return r.status === 'absent' || r.status === 'late';
    if (tab === 'physics') return r.className.includes('Physics');
    return true;
  });

  const present = attendanceRecords.filter((r) => r.status === 'present').length;

  return (
    <AppShell
      role="Parent"
      accountName={parentProfile.name}
      accountDetail="2 children · Nugegoda"
      initials="CP"
      nav={parentNav}
      title="Attendance"
      subtitle="Kasun Perera · Grade 12 · this term">
      
      <div className="grid gap-6 xl:grid-cols-[0.62fr_1.38fr]">
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <p className="text-caption uppercase tracking-wide text-ink-faint">
            Term attendance
          </p>
          <p className="mt-1.5 text-display text-ink">92%</p>
          <p className="mt-1 text-small text-ink-soft">
            {present} present · 1 late · 1 absent · 1 excused, across 3 classes
          </p>

          <ul className="mt-6 space-y-3">
            <ClassRate name="A/L Physics" rate={90} />
            <ClassRate name="Combined Maths" rate={96} />
            <ClassRate name="A/L Chemistry" rate={88} />
          </ul>

          <div className="mt-6 rounded-xl bg-surface-muted px-4 py-3">
            <p className="text-caption text-ink-soft">
              Attendance is marked by the teacher at the hall. If a mark looks wrong,
              raise it with the institute from the session row — it creates a record
              rather than a phone call.
            </p>
          </div>
        </section>

        <section className="rounded-2xl border border-line bg-white shadow-card">
          <div className="px-6 pt-5">
            <Tabs items={filters} active={tab} onChange={setTab} ariaLabel="Attendance filters" />
          </div>
          <ul className="divide-y divide-line px-6">
            {rows.map((r) =>
            <li key={`${r.date}-${r.className}`} className="flex flex-wrap items-center justify-between gap-3 py-4">
                <div className="min-w-0">
                  <p className="text-small font-medium text-ink">{r.className}</p>
                  <p className="text-caption text-ink-soft">
                    {r.date} · marked by {r.markedBy}
                    {r.note ? ` · ${r.note}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <AttendanceBadge status={r.status} />
                  <button className="text-caption font-medium text-forest underline underline-offset-4">
                    Query
                  </button>
                </div>
              </li>
            )}
          </ul>
          <div className="flex items-center justify-between gap-3 border-t border-line px-6 py-4">
            <Badge tone="neutral">Showing {rows.length} sessions</Badge>
            <PrototypeNote>Attendance events are sample records.</PrototypeNote>
          </div>
        </section>
      </div>
    </AppShell>);

}

function ClassRate({ name, rate }: {name: string;rate: number;}) {
  return (
    <li>
      <div className="flex items-center justify-between text-small">
        <span className="text-ink">{name}</span>
        <span className="text-ink-soft">{rate}%</span>
      </div>
      <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
        <div
          className={`h-full rounded-full ${rate >= 90 ? 'bg-forest' : 'bg-state-warn'}`}
          style={{ width: `${rate}%` }} />
        
      </div>
    </li>);

}