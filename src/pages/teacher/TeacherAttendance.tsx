import React, { useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { SessionAttendance } from '../../components/teacher/SessionAttendance';
import { Badge } from '../../components/ui/Badge';
import { useTeacherScope } from '../../hooks/useTeacherScope';

/** Attendance for the teacher's OWN sessions only. */
export function TeacherAttendance() {
  const { mySessions, myClasses } = useTeacherScope();
  const [params] = useSearchParams();
  const [selected, setSelected] = useState(
    params.get('session') ?? mySessions.find((s) => s.state === 'next')?.id ?? mySessions[0]?.id
  );
  const [closed, setClosed] = useState<Record<string, string>>({});
  const session = mySessions.find((s) => s.id === selected);

  return (
    <TeacherShell title="Attendance" subtitle="Your sessions today — start, let students scan, close.">
      <div className="grid gap-6 lg:grid-cols-[0.34fr_0.66fr]">
        <section className="h-fit rounded-2xl border border-line bg-white p-4 shadow-card">
          <h2 className="px-1 text-h3 text-ink">Today</h2>
          <ul className="mt-3 space-y-2">
            {mySessions.map((s) =>
            <li key={s.id}>
                <button
                onClick={() => setSelected(s.id)}
                aria-current={s.id === selected}
                className={`w-full rounded-xl border px-3.5 py-3 text-left transition-colors duration-150 ease-out ${
                s.id === selected ? 'border-forest bg-forest-50' : 'border-line hover:border-line-strong'}`
                }>
                
                  <p className="text-small font-medium text-ink">{s.name}</p>
                  <p className="text-caption text-ink-soft">
                    {s.time} · {s.place}
                  </p>
                  <div className="mt-2">
                    {closed[s.id] ?
                  <Badge tone="success">{closed[s.id]}</Badge> :
                  s.state === 'done' ?
                  <Badge tone="success">
                        {s.present} / {s.total} present
                      </Badge> :

                  <Badge tone="neutral">Not started</Badge>
                  }
                  </div>
                </button>
              </li>
            )}
          </ul>
          <p className="mt-4 px-1 text-caption text-ink-faint">
            Other sessions this week: {myClasses.length - mySessions.length > 0 ? `${myClasses.length - mySessions.length} more classes` : 'none'}.
          </p>
        </section>

        {session ?
        <SessionAttendance
          key={session.id}
          sessionName={session.name}
          sessionMeta={`Today · ${session.time} · ${session.place}`}
          initialStage={session.state === 'done' ? 'closed' : 'ready'}
          onClosed={(p, t) => setClosed((c) => ({ ...c, [session.id]: `${p} / ${t} present` }))} /> :


        <p className="rounded-2xl border border-dashed border-line-strong px-5 py-10 text-center text-small text-ink-soft">
            No sessions today.
          </p>
        }
      </div>
    </TeacherShell>);

}