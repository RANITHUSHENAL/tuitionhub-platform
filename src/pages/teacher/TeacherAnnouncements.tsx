import React from 'react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { ClassAnnouncementForm } from '../../components/teacher/ClassAnnouncementForm';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import { teacherAnnouncements } from '../../data/teaching';

export function TeacherAnnouncements() {
  const { myClasses } = useTeacherScope();
  const ids = myClasses.map((c) => c.id);
  const sent = teacherAnnouncements.filter((a) => ids.includes(a.classId));

  return (
    <TeacherShell title="Announcements" subtitle="Messages to one of your classes and their guardians">
      <div className="grid gap-6 lg:grid-cols-[1fr_0.9fr]">
        <ClassAnnouncementForm classes={myClasses} />
        <section className="h-fit rounded-2xl border border-line bg-white p-5 shadow-card">
          <h2 className="text-h3 text-ink">Sent by you</h2>
          {sent.length ?
          <ul className="mt-3 divide-y divide-line">
              {sent.map((a) =>
            <li key={a.id} className="py-3">
                  <p className="text-small font-medium text-ink">{a.title}</p>
                  <p className="mt-0.5 text-caption text-ink-soft">{a.body}</p>
                  <p className="mt-1 text-caption text-ink-faint">
                    {myClasses.find((c) => c.id === a.classId)?.name} · {a.sent} · reached {a.reach}
                  </p>
                </li>
            )}
            </ul> :

          <p className="mt-3 text-small text-ink-soft">Nothing sent from this workspace yet.</p>
          }
        </section>
      </div>
    </TeacherShell>);

}