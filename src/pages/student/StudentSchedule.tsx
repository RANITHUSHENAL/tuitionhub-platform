import React from 'react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { studentProfile } from '../../data/people';

const week = [
{ day: 'Monday', items: [] as {title: string;time: string;place: string;kind: string;}[] },
{
  day: 'Tuesday',
  items: [
  { title: 'A/L Physics — Theory', time: '6:00 PM', place: 'Nugegoda Hall B', kind: 'Class' }]

},
{
  day: 'Wednesday',
  items: [
  { title: 'Waves Study Session', time: '6:00 PM', place: 'Near Nugegoda', kind: 'Study' }]

},
{
  day: 'Thursday',
  items: [
  { title: 'Integration Practice', time: '5:00 PM', place: 'Near Pannipitiya', kind: 'Study' }]

},
{
  day: 'Friday',
  items: [
  { title: 'A/L Chemistry — Revision', time: '5:30 PM', place: 'Colombo 05', kind: 'Class' }]

},
{
  day: 'Saturday',
  items: [
  { title: 'Combined Maths — Theory', time: '8:00 AM', place: 'Nugegoda Main Hall', kind: 'Class' }]

},
{ day: 'Sunday', items: [] }];


export function StudentSchedule() {
  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title="Schedule"
      subtitle="Classes and study sessions in one week view">
      
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {week.map((d) =>
        <section key={d.day} className="rounded-2xl border border-line bg-white p-5">
            <h2 className="text-h3 text-ink">{d.day}</h2>
            {d.items.length === 0 ?
          <p className="mt-3 text-small text-ink-faint">Nothing scheduled</p> :

          <ul className="mt-3 space-y-3">
                {d.items.map((item) =>
            <li
              key={item.title}
              className={`rounded-xl border-l-2 bg-surface-muted px-3.5 py-3 ${
              item.kind === 'Class' ? 'border-forest' : 'border-clay'}`
              }>
              
                    <p className="text-small font-medium text-ink">{item.title}</p>
                    <p className="text-caption text-ink-soft">
                      {item.time} · {item.place}
                    </p>
                    <div className="mt-2">
                      <Badge tone={item.kind === 'Class' ? 'success' : 'neutral'}>
                        {item.kind}
                      </Badge>
                    </div>
                  </li>
            )}
              </ul>
          }
          </section>
        )}
      </div>
    </AppShell>);

}