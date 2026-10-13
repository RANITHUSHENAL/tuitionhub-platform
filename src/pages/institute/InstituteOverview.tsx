import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpRightIcon, DownloadIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { Button, LinkButton } from '../../components/ui/Button';
import { PrototypeNote } from '../../components/ui/States';
import {
  institute,
  instituteStats,
  teacherActivity,
  todaysClasses } from
'../../data/institute';
import { lkr } from '../../utils/format';

export function InstituteOverview() {
  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Today at Sarasavi Academy"
      subtitle="Thursday, 19 March · 7 classes scheduled"
      actions={
      <Button variant="secondary" size="sm">
          <DownloadIcon className="h-4 w-4" />
          Export day report
        </Button>
      }>
      
      <div className="grid gap-6 xl:grid-cols-[1.45fr_0.55fr]">
        <div className="space-y-6">
          <section className="grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-4">
            <Metric label="Total students" value={instituteStats.totalStudents.toLocaleString()} detail="Across 5 classes" />
            <Metric label="Classes today" value={`${instituteStats.classesToday}`} detail={`${instituteStats.attendanceMarked} attendance marked`} />
            <Metric label="Attendance today" value={`${instituteStats.attendanceToday}%`} detail="Of expected students" />
            <Metric label="Outstanding fees" value={lkr(instituteStats.outstanding)} detail="38 students" tone="warn" />
          </section>

          <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <div className="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
              <h2 className="text-h3 text-ink">Today's classes</h2>
              <Link to="/institute/attendance" className="text-small font-medium text-forest">
                Attendance board
              </Link>
            </div>
            <ul className="divide-y divide-line">
              {todaysClasses.map((c) =>
              <li key={c.id} className="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                  <div className="min-w-0">
                    <p className="text-small font-medium text-ink">{c.name}</p>
                    <p className="text-caption text-ink-soft">
                      {c.teacher} · {c.time} · {c.hall} · {c.enrolled} enrolled
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    {c.attendance === 'marked' ?
                  <Badge tone="success">Marked · {c.present}/{c.enrolled}</Badge> :
                  c.attendance === 'partial' ?
                  <Badge tone="warn">In progress · {c.present}/{c.enrolled}</Badge> :

                  <Badge tone="neutral">Not marked</Badge>
                  }
                    <LinkButton to="/institute/attendance" variant="secondary" size="sm">
                      Open
                    </LinkButton>
                  </div>
                </li>
              )}
            </ul>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-caption uppercase tracking-wide text-ink-faint">
                  Collected this month
                </p>
                <p className="mt-1 text-display text-ink">{lkr(instituteStats.collectedThisMonth)}</p>
                <p className="mt-1 text-small text-ink-soft">
                  88% of billed fees · {lkr(instituteStats.outstanding)} outstanding
                </p>
              </div>
              <div className="rounded-xl border border-line px-4 py-3">
                <p className="text-caption uppercase tracking-wide text-ink-faint">
                  Next settlement
                </p>
                <p className="mt-0.5 text-small font-medium text-ink">
                  {instituteStats.nextSettlement}
                </p>
                <p className="text-caption text-ink-soft">
                  {lkr(instituteStats.settledYesterday)} settled yesterday
                </p>
              </div>
            </div>
            <div className="mt-4 h-2 overflow-hidden rounded-full bg-surface-sunk">
              <div className="h-full w-[88%] rounded-full bg-forest" />
            </div>
            <LinkButton to="/institute/fees" variant="secondary" size="sm" className="mt-5">
              Fees & settlements
              <ArrowUpRightIcon className="h-4 w-4" />
            </LinkButton>
          </section>
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Teacher activity</h2>
            <ul className="mt-4 space-y-4">
              {teacherActivity.map((t) =>
              <li key={t.id}>
                  <p className="text-small text-ink">
                    <span className="font-medium">{t.teacher}</span> {t.action.toLowerCase()}
                  </p>
                  <p className="text-caption text-ink-soft">{t.target}</p>
                  <p className="text-caption text-ink-faint">{t.time}</p>
                </li>
              )}
            </ul>
          </section>

          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <h2 className="text-h3 text-ink">You stay in control</h2>
            <ul className="mt-3 space-y-2 text-small text-ink-soft">
              <li>Public listings are attributed to the institute; teacher names are optional.</li>
              <li>Settlements land in your account on the next working day.</li>
              <li>Full export of students, attendance and payments, any time.</li>
            </ul>
            <div className="mt-4">
              <PrototypeNote>Figures are illustrative, not real traction.</PrototypeNote>
            </div>
          </section>
        </aside>
      </div>
    </AppShell>);

}

function Metric({
  label,
  value,
  detail,
  tone





}: {label: string;value: string;detail: string;tone?: 'warn';}) {
  return (
    <div className="bg-white px-5 py-5">
      <p className="text-caption uppercase tracking-wide text-ink-faint">{label}</p>
      <p className={`mt-1.5 text-h2 ${tone === 'warn' ? 'text-clay' : 'text-ink'}`}>{value}</p>
      <p className="mt-1 text-caption text-ink-soft">{detail}</p>
    </div>);

}