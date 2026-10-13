import React from 'react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { institute, instituteStats, roster, todaysClasses } from '../../data/institute';

/**
 * Institute view of attendance is oversight: which sessions were marked, by
 * whom, and who needs follow-up. Teachers mark attendance from their own
 * workspace; the front desk can still correct a record.
 */
export function InstituteAttendance() {
  const lowAttendance = roster.filter((r) => r.attendance < 80);
  const notMarked = todaysClasses.filter((c) => c.attendance !== 'marked');

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Attendance overview"
      subtitle="Marked by teachers during each session. You see the whole institute here.">
      
      <div className="space-y-6">
        <dl className="grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-3">
          <div className="bg-white px-6 py-5">
            <dt className="text-caption text-ink-faint">Today, across all classes</dt>
            <dd className="mt-1 text-h1 text-ink">{instituteStats.attendanceToday}%</dd>
          </div>
          <div className="bg-white px-6 py-5">
            <dt className="text-caption text-ink-faint">Sessions marked</dt>
            <dd className="mt-1 text-h1 text-ink">
              {instituteStats.attendanceMarked}
              <span className="text-h3 text-ink-faint"> / {instituteStats.classesToday}</span>
            </dd>
          </div>
          <div className="bg-white px-6 py-5">
            <dt className="text-caption text-ink-faint">Students below 80% this term</dt>
            <dd className="mt-1 text-h1 text-ink">{lowAttendance.length}</dd>
          </div>
        </dl>

        <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
          <section className="overflow-x-auto rounded-2xl border border-line bg-white shadow-card">
            <table className="w-full min-w-[36rem] text-left">
              <thead className="border-b border-line bg-surface-muted">
                <tr className="text-caption uppercase tracking-wide text-ink-faint">
                  <th scope="col" className="px-5 py-3 font-medium">Session today</th>
                  <th scope="col" className="px-5 py-3 font-medium">Teacher</th>
                  <th scope="col" className="px-5 py-3 font-medium">Present</th>
                  <th scope="col" className="px-5 py-3 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line text-small">
                {todaysClasses.map((c) =>
                <tr key={c.id}>
                    <td className="px-5 py-3.5">
                      <p className="font-medium text-ink">{c.name}</p>
                      <p className="text-caption text-ink-soft">
                        {c.time} · {c.hall}
                      </p>
                    </td>
                    <td className="px-5 py-3.5 text-ink-soft">{c.teacher}</td>
                    <td className="px-5 py-3.5 tabular-nums text-ink">
                      {c.attendance === 'not-marked' ? '—' : `${c.present} / ${c.enrolled}`}
                    </td>
                    <td className="px-5 py-3.5">
                      {c.attendance === 'marked' ?
                    <Badge tone="success">Closed by teacher</Badge> :
                    c.attendance === 'partial' ?
                    <Badge tone="warn">Open now</Badge> :

                    <Badge tone="neutral">Starts {c.time}</Badge>
                    }
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </section>

          <aside className="space-y-4">
            <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <h2 className="text-h3 text-ink">Needs follow-up</h2>
              <ul className="mt-3 space-y-2.5">
                {lowAttendance.map((r) =>
                <li key={r.id} className="flex items-center justify-between gap-3">
                    <span>
                      <span className="block text-small font-medium text-ink">{r.name}</span>
                      <span className="block text-caption text-ink-soft">Guardian: {r.guardian}</span>
                    </span>
                    <Badge tone="warn">{r.attendance}%</Badge>
                  </li>
                )}
              </ul>
            </section>
            <p className="px-1 text-caption text-ink-faint">
              {notMarked.length} sessions still open or upcoming. If a teacher is absent, the front
              desk (Staff role) can mark a session manually — it is recorded as “Institute office”.
            </p>
          </aside>
        </div>
      </div>
    </AppShell>);

}