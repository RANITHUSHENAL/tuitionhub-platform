import React from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowRightIcon,
  ClipboardCheckIcon,
  ClockIcon,
  FileQuestionIcon,
  MapPinIcon,
  MegaphoneIcon,
  PencilLineIcon } from
'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { Badge } from '../../components/ui/Badge';
import { LinkButton } from '../../components/ui/Button';
import { EmptyState } from '../../components/ui/States';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import { teacherActivity } from '../../data/teaching';

const quickActions = [
{ to: '/teacher/attendance', label: 'Attendance', icon: ClipboardCheckIcon },
{ to: '/teacher/marks', label: 'Marks', icon: PencilLineIcon },
{ to: '/teacher/exams/new', label: 'Exam', icon: FileQuestionIcon },
{ to: '/teacher/announcements', label: 'Announcement', icon: MegaphoneIcon }];


function greeting() {
  const h = new Date().getHours();
  return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
}

export function TeacherHome() {
  const { workspace, myClasses, mySessions, firstName, user } = useTeacherScope();
  const pending = user?.workspaces?.filter((w) => w.status === 'pending') ?? [];

  if (!workspace) {
    return (
      <TeacherShell title={`${greeting()}, ${firstName}`}>
        <div className="max-w-xl">
          <EmptyState
            icon={<ClockIcon className="h-5 w-5" />}
            title={pending.length ? `Waiting for ${pending[0].name}` : 'No teaching workspace yet'}
            body={
            pending.length ?
            'Your classes appear here once the institute approves your request and assigns them. You can also start independent classes meanwhile.' :
            'Link to an institute or start teaching independently.'
            }
            action={<LinkButton to="/teacher/workspaces">Manage workspaces</LinkButton>} />
          
        </div>
      </TeacherShell>);

  }

  const next = mySessions.find((s) => s.state === 'next') ?? mySessions[0];
  const nextClass = myClasses.find((c) => c.id === next?.classId);

  return (
    <TeacherShell
      title={`${greeting()}, ${firstName}`}
      subtitle={`${mySessions.length} ${mySessions.length === 1 ? 'session' : 'sessions'} today · ${workspace.name}`}>
      
      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-6">
          {next && nextClass ?
          <section className="rounded-2xl bg-forest p-6 text-white shadow-card">
              <p className="text-caption text-forest-100">Your next session</p>
              <h2 className="mt-1.5 text-h2 text-white">{nextClass.name}</h2>
              <p className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-small text-forest-100">
                <span className="inline-flex items-center gap-1.5">
                  <ClockIcon className="h-4 w-4" /> Today · {next.time}
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <MapPinIcon className="h-4 w-4" /> {next.place}
                </span>
                <span>{next.total} students</span>
              </p>
              <div className="mt-5 flex flex-wrap gap-2">
                <Link
                to={`/teacher/attendance?session=${next.id}`}
                className="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-small font-medium text-forest transition-colors duration-150 ease-out hover:bg-forest-50">
                
                  <ClipboardCheckIcon className="h-4 w-4" />
                  Start attendance
                </Link>
                <Link
                to={`/teacher/classes/${nextClass.id}`}
                className="inline-flex h-10 items-center rounded-xl border border-white/25 px-4 text-small font-medium text-white transition-colors duration-150 ease-out hover:bg-white/10">
                
                  Open class
                </Link>
              </div>
            </section> :
          null}

          <section>
            <h2 className="text-h3 text-ink">Quick actions</h2>
            <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
              {quickActions.map((a) =>
              <Link
                key={a.label}
                to={a.to}
                className="flex flex-col items-start gap-3 rounded-2xl border border-line bg-white p-4 transition-[border-color,box-shadow] duration-150 ease-out hover:border-forest hover:shadow-card">
                
                  <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-forest-50 text-forest">
                    <a.icon className="h-4.5 w-4.5" aria-hidden="true" />
                  </span>
                  <span className="text-small font-medium text-ink">{a.label}</span>
                </Link>
              )}
            </div>
          </section>

          <section className="rounded-2xl border border-line bg-white shadow-card">
            <div className="flex items-center justify-between border-b border-line px-5 py-4">
              <h2 className="text-h3 text-ink">Today's classes</h2>
              <Link to="/teacher/classes" className="text-small font-medium text-forest">
                All my classes
              </Link>
            </div>
            <ul className="divide-y divide-line">
              {mySessions.map((s) =>
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                  <div>
                    <p className="text-small font-medium text-ink">{s.name}</p>
                    <p className="text-caption text-ink-soft">
                      {s.time} · {s.place}
                    </p>
                  </div>
                  {s.state === 'done' ?
                <Badge tone="success">
                      {s.present} / {s.total} present
                    </Badge> :

                <Badge tone="info">Attendance not started</Badge>
                }
                </li>
              )}
            </ul>
          </section>
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <h2 className="text-h3 text-ink">Recent activity</h2>
            <ul className="mt-3 space-y-3">
              {teacherActivity.map((a) =>
              <li key={a.id}>
                  <Link to={a.to} className="group block">
                    <p className="text-small text-ink group-hover:text-forest">{a.text}</p>
                    <p className="text-caption text-ink-faint">{a.time}</p>
                  </Link>
                </li>
              )}
            </ul>
          </section>
          <section className="rounded-2xl border border-line bg-surface-muted p-5">
            <p className="text-small font-medium text-ink">
              {workspace.kind === 'institute' ? `Teaching at ${workspace.name}` : 'Your independent classes'}
            </p>
            <p className="mt-1 text-caption text-ink-soft">
              {workspace.kind === 'institute' ?
              `You see the ${myClasses.length} classes ${workspace.name} assigned to you. Fees and institute-wide reports stay with the institute.` :
              'You own these classes — schedule, fees and payments are yours to manage.'}
            </p>
            <Link to="/teacher/workspaces" className="mt-3 inline-flex items-center gap-1 text-caption font-medium text-forest">
              Workspaces <ArrowRightIcon className="h-3.5 w-3.5" />
            </Link>
          </section>
        </aside>
      </div>
    </TeacherShell>);

}