import React from 'react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { institute, instituteClasses } from '../../data/institute';
import { exams } from '../../data/exams';
import type { ExamStatus } from '../../types';

const tone: Record<ExamStatus, {tone: 'success' | 'info' | 'neutral' | 'warn';label: string;}> = {
  open: { tone: 'success', label: 'Open' },
  upcoming: { tone: 'info', label: 'Scheduled' },
  submitted: { tone: 'warn', label: 'Teacher marking' },
  graded: { tone: 'neutral', label: 'Results published' },
  draft: { tone: 'neutral', label: 'Draft' }
};

/**
 * Oversight only. Exams are written, marked and published by the teacher of
 * each class; the institute sees the record and class-level results.
 */
export function InstituteExams() {
  const teacherFor = (className: string) =>
  instituteClasses.find((c) => c.name === className)?.teacher ?? 'Partner teacher';
  const graded = exams.filter((e) => e.status === 'graded' && typeof e.score === 'number');

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Exams & academic"
      subtitle="Every exam published in your classes, and who set it">
      
      <div className="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
        <section className="overflow-x-auto rounded-2xl border border-line bg-white shadow-card">
          <table className="w-full min-w-[42rem] text-left">
            <thead className="border-b border-line bg-surface-muted">
              <tr className="text-caption uppercase tracking-wide text-ink-faint">
                <th scope="col" className="px-5 py-3 font-medium">Exam</th>
                <th scope="col" className="px-5 py-3 font-medium">Set by</th>
                <th scope="col" className="px-5 py-3 font-medium">Submissions</th>
                <th scope="col" className="px-5 py-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line text-small">
              {exams.map((e) =>
              <tr key={e.id}>
                  <td className="px-5 py-4">
                    <p className="font-medium text-ink">{e.title}</p>
                    <p className="text-caption text-ink-soft">
                      {e.className} · {e.windowLabel}
                    </p>
                  </td>
                  <td className="px-5 py-4 text-ink-soft">{teacherFor(e.className)}</td>
                  <td className="px-5 py-4 tabular-nums text-ink-soft">{e.submissions ?? 0}</td>
                  <td className="px-5 py-4">
                    <Badge tone={tone[e.status].tone}>{tone[e.status].label}</Badge>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </section>

        <aside className="space-y-4">
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <h2 className="text-h3 text-ink">Published results</h2>
            {graded.length ?
            <ul className="mt-3 space-y-2.5">
                {graded.map((e) =>
              <li key={e.id} className="flex items-center justify-between gap-3">
                    <span className="text-small text-ink">{e.title}</span>
                    <span className="text-caption text-ink-soft">{e.totalMarks} marks</span>
                  </li>
              )}
              </ul> :

            <p className="mt-2 text-small text-ink-soft">No results published this term yet.</p>
            }
          </section>
          <p className="px-1 text-caption text-ink-faint">
            Teachers create and mark exams for the classes you assign them. You can see every
            exam and its results here, but you don’t need to set papers yourself.
          </p>
        </aside>
      </div>
    </AppShell>);

}