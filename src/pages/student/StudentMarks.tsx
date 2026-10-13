import React from 'react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { AttendanceBadge, Badge } from '../../components/ui/Badge';
import { attendanceRecords, marks, studentProfile } from '../../data/people';

export function StudentMarks() {
  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title="Marks & attendance"
      subtitle="The same records your parent sees — no surprises at the end of term">
      
      <div className="grid gap-6 lg:grid-cols-2">
        <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
          <h2 className="border-b border-line px-6 py-4 text-h3 text-ink">Marks</h2>
          <ul className="divide-y divide-line">
            {marks.map((m) =>
            <li key={m.id} className="flex items-center justify-between gap-3 px-6 py-4">
                <div>
                  <p className="text-small font-medium text-ink">{m.assessment}</p>
                  <p className="text-caption text-ink-soft">
                    {m.subject} · {m.date}
                  </p>
                </div>
                <div className="text-right">
                  <p className="text-h3 text-ink">
                    {m.score}
                    <span className="text-ink-faint"> / {m.outOf}</span>
                  </p>
                  <Badge tone={m.score >= m.classAverage ? 'success' : 'warn'}>
                    avg {m.classAverage}
                  </Badge>
                </div>
              </li>
            )}
          </ul>
        </section>

        <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
          <h2 className="border-b border-line px-6 py-4 text-h3 text-ink">Attendance</h2>
          <ul className="divide-y divide-line">
            {attendanceRecords.map((r) =>
            <li key={`${r.date}-${r.className}`} className="flex items-center justify-between gap-3 px-6 py-4">
                <div>
                  <p className="text-small font-medium text-ink">{r.className}</p>
                  <p className="text-caption text-ink-soft">
                    {r.date}
                    {r.note ? ` · ${r.note}` : ''}
                  </p>
                </div>
                <AttendanceBadge status={r.status} />
              </li>
            )}
          </ul>
        </section>
      </div>
    </AppShell>);

}