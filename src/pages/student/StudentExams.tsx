import React from 'react';
import { ClockIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { LinkButton } from '../../components/ui/Button';
import { useAuth } from '../../contexts/AuthContext';
import { exams } from '../../data/exams';
import type { ExamStatus } from '../../types';

const statusBadge: Record<ExamStatus, {tone: 'success' | 'info' | 'neutral' | 'warn' | 'forest';label: string;}> = {
  open: { tone: 'success', label: 'Open now' },
  upcoming: { tone: 'info', label: 'Upcoming' },
  submitted: { tone: 'warn', label: 'Submitted · awaiting marks' },
  graded: { tone: 'neutral', label: 'Result released' },
  draft: { tone: 'neutral', label: 'Draft' }
};

export function StudentExams() {
  const { user } = useAuth();
  const list = exams.filter((e) => e.status !== 'draft');

  return (
    <AppShell
      role="Student"
      accountName={user?.name ?? 'Kasun Perera'}
      accountDetail={user?.detail ?? 'Grade 12 · A/L 2027'}
      initials={user?.initials ?? 'KP'}
      nav={studentNav}
      title="Exams"
      subtitle="Online papers set by your teachers">
      
      <ul className="max-w-4xl space-y-3">
        {list.map((e) => {
          const s = statusBadge[e.status];
          return (
            <li
              key={e.id}
              className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white px-5 py-4 shadow-card">
              
              <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                  <h2 className="text-h3 text-ink">{e.title}</h2>
                  <Badge tone={s.tone}>{s.label}</Badge>
                </div>
                <p className="mt-1 text-caption text-ink-soft">
                  {e.className} · {e.institute}
                </p>
                <p className="mt-1 inline-flex items-center gap-1.5 text-caption text-ink-faint">
                  <ClockIcon className="h-3.5 w-3.5" aria-hidden="true" />
                  {e.durationMins} min · {e.totalMarks} marks · {e.windowLabel}
                </p>
              </div>
              {e.status === 'open' ?
              <LinkButton to={`/student/exams/${e.id}`} size="sm">
                  Start
                </LinkButton> :
              e.status === 'graded' ?
              <div className="text-right">
                  <p className="text-h3 text-ink">
                    {e.score} <span className="text-ink-faint">/ {e.totalMarks}</span>
                  </p>
                  <p className="text-caption text-ink-soft">See Results</p>
                </div> :

              <span className="text-caption text-ink-faint">Not open yet</span>
              }
            </li>);

        })}
      </ul>
    </AppShell>);

}