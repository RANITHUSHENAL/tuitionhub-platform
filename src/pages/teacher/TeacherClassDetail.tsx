import React, { useState } from 'react';
import { useParams } from 'react-router-dom';
import { LayersIcon, PlusIcon, SearchIcon } from 'lucide-react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { SessionAttendance } from '../../components/teacher/SessionAttendance';
import { MarksEntry } from '../../components/teacher/MarksEntry';
import { ClassAnnouncementForm } from '../../components/teacher/ClassAnnouncementForm';
import { Avatar } from '../../components/ui/Avatar';
import { Badge } from '../../components/ui/Badge';
import { LinkButton } from '../../components/ui/Button';
import { EmptyState } from '../../components/ui/States';
import { Tabs } from '../../components/ui/Tabs';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import { classStudents } from '../../data/teaching';
import { initialsOf } from '../../utils/format';

export function TeacherClassDetail() {
  const { id = '' } = useParams();
  const { myClasses, myExams, mySessions } = useTeacherScope();
  const cls = myClasses.find((c) => c.id === id);
  const [tab, setTab] = useState('students');
  const [query, setQuery] = useState('');

  if (!cls) {
    return (
      <TeacherShell title="Class">
        <EmptyState
          icon={<LayersIcon className="h-5 w-5" />}
          title="Not one of your classes"
          body="You can only open classes assigned to you in this workspace. Switch workspace from your account menu if it belongs elsewhere."
          action={<LinkButton to="/teacher/classes">My classes</LinkButton>} />
        
      </TeacherShell>);

  }

  const session = mySessions.find((s) => s.classId === cls.id);
  const exams = myExams.filter((e) => e.className === cls.name);
  const students = classStudents.filter((s) => s.name.toLowerCase().includes(query.toLowerCase()));

  return (
    <TeacherShell title={cls.name} subtitle={`${cls.schedule} · ${cls.place} · ${cls.students} students`}>
      <Tabs
        ariaLabel="Class sections"
        active={tab}
        onChange={setTab}
        items={[
        { id: 'students', label: 'Students', count: cls.students },
        { id: 'attendance', label: 'Attendance' },
        { id: 'marks', label: 'Marks' },
        { id: 'exams', label: 'Exams', count: exams.length },
        { id: 'announce', label: 'Announcements' }]
        } />
      
      <div className="mt-6">
        {tab === 'students' ?
        <section className="rounded-2xl border border-line bg-white shadow-card">
            <div className="flex items-center gap-2.5 border-b border-line px-5 py-3">
              <SearchIcon className="h-4 w-4 text-ink-faint" aria-hidden="true" />
              <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search students"
              aria-label="Search students"
              className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
            
            </div>
            <ul className="divide-y divide-line">
              {students.map((s) =>
            <li key={s.id} className="flex items-center justify-between gap-3 px-5 py-3">
                  <span className="flex items-center gap-3">
                    <Avatar initials={initialsOf(s.name)} size="sm" />
                    <span>
                      <span className="block text-small font-medium text-ink">{s.name}</span>
                      <span className="block text-caption text-ink-soft">Last mark {s.lastMark}</span>
                    </span>
                  </span>
                  <Badge tone={s.attendance >= 85 ? 'success' : 'warn'}>{s.attendance}% attendance</Badge>
                </li>
            )}
            </ul>
            <p className="border-t border-line px-5 py-3 text-caption text-ink-faint">
              Showing a sample of {classStudents.length}. You see names, attendance and marks — guardian
              contact details and fees stay with {cls.workspaceId === 'ws-independent' ? 'you in Payments' : 'the institute'}.
            </p>
          </section> :
        null}

        {tab === 'attendance' ?
        <SessionAttendance
          sessionName={session ? `Today · ${session.time}` : `Next session · ${cls.nextSession}`}
          sessionMeta={`${cls.name} · ${cls.place}`}
          initialStage={session?.state === 'done' ? 'closed' : 'ready'} /> :

        null}

        {tab === 'marks' ? <MarksEntry classes={myClasses} fixedClassId={cls.id} /> : null}

        {tab === 'exams' ?
        <section className="space-y-3">
            <div className="flex justify-end">
              <LinkButton to={`/teacher/exams/new?class=${cls.id}`} size="sm">
                <PlusIcon className="h-4 w-4" />
                Create exam for this class
              </LinkButton>
            </div>
            {exams.length ?
          exams.map((e) =>
          <div key={e.id} className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-white px-5 py-4">
                  <div>
                    <p className="text-small font-medium text-ink">{e.title}</p>
                    <p className="text-caption text-ink-soft">
                      {e.windowLabel} · {e.submissions ?? 0} submissions
                    </p>
                  </div>
                  <Badge tone={e.status === 'open' ? 'success' : 'neutral'}>{e.status}</Badge>
                </div>
          ) :

          <p className="rounded-2xl border border-dashed border-line-strong px-5 py-8 text-center text-small text-ink-soft">
                No exams for this class yet.
              </p>
          }
          </section> :
        null}

        {tab === 'announce' ? <ClassAnnouncementForm classes={myClasses} fixedClassId={cls.id} /> : null}
      </div>
    </TeacherShell>);

}