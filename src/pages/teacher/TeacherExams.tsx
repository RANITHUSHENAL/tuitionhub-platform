import React from 'react';
import { ClockIcon, PlusIcon } from 'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { Badge } from '../../components/ui/Badge';
import { Button, LinkButton } from '../../components/ui/Button';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import type { ExamStatus } from '../../types';

const status: Record<ExamStatus, {tone: 'success' | 'info' | 'neutral' | 'warn';label: string;}> = {
  open: { tone: 'success', label: 'Open' },
  upcoming: { tone: 'info', label: 'Scheduled' },
  submitted: { tone: 'warn', label: 'Needs marking' },
  graded: { tone: 'neutral', label: 'Results published' },
  draft: { tone: 'neutral', label: 'Draft' }
};

/** My exams — only for classes assigned to me. */
export function TeacherExams() {
  const { myExams, myClasses } = useTeacherScope();

  return (
    <TeacherShell
      title="My exams"
      subtitle="Create papers for your classes, mark written answers, publish results."
      actions={
      <LinkButton to="/teacher/exams/new" size="sm">
          <PlusIcon className="h-4 w-4" />
          Create exam
        </LinkButton>
      }>
      
      {myExams.length ?
      <ul className="max-w-4xl space-y-3">
          {myExams.map((e) => {
          const s = status[e.status];
          return (
            <li key={e.id} className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-white px-5 py-4 shadow-card">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-h3 text-ink">{e.title}</h2>
                    <Badge tone={s.tone}>{s.label}</Badge>
                  </div>
                  <p className="mt-1 text-caption text-ink-soft">{e.className}</p>
                  <p className="mt-1 inline-flex items-center gap-1.5 text-caption text-ink-faint">
                    <ClockIcon className="h-3.5 w-3.5" aria-hidden="true" />
                    {e.durationMins} min · {e.totalMarks} marks · {e.windowLabel}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-small text-ink-soft">{e.submissions ?? 0} submissions</span>
                  {e.status === 'open' || e.status === 'submitted' ?
                <Button variant="secondary" size="sm">
                      Mark written answers
                    </Button> :
                null}
                </div>
              </li>);

        })}
        </ul> :

      <p className="max-w-xl rounded-2xl border border-dashed border-line-strong px-5 py-10 text-center text-small text-ink-soft">
          No exams yet for your {myClasses.length} classes. Create one — objective questions mark themselves.
        </p>
      }
    </TeacherShell>);

}