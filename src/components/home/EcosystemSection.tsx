import React from 'react';
import {
  BanknoteIcon,
  CalendarCheckIcon,
  GraduationCapIcon,
  SearchIcon,
  UserPlusIcon,
  UsersIcon } from
'lucide-react';

const journey = [
{ icon: SearchIcon, title: 'Discover', body: 'Find a class by subject, exam year, area and fee.' },
{ icon: UserPlusIcon, title: 'Join', body: 'Enrol online. The institute or teacher confirms the seat.' },
{ icon: CalendarCheckIcon, title: 'Attend', body: 'Scan the session QR at the hall, or join the online link.' },
{ icon: GraduationCapIcon, title: 'Track progress', body: 'Marks and exam results land on the same enrolment.' },
{ icon: BanknoteIcon, title: 'Pay', body: 'The invoice names the class and who receives the money.' },
{ icon: UsersIcon, title: 'Study with others', body: 'Join study sessions with students learning the same thing.' }];


const responsibilities = [
{ area: 'Attendance', teacher: 'Marks it each session', student: 'Scans the session QR', parent: 'Sees it, or gets an SMS', institute: 'Reviews the overview' },
{ area: 'Marks & exams', teacher: 'Sets papers, enters marks', student: 'Takes exams, sees results', parent: 'Sees published results', institute: 'Oversees results' },
{ area: 'Announcements', teacher: 'Posts to their class', student: 'Receives', parent: 'Receives', institute: 'Posts institute-wide' },
{ area: 'Fees', teacher: 'Independent teachers collect', student: '—', parent: 'Pays, gets a receipt', institute: 'Collects, settles, reconciles' }];


export function EcosystemSection() {
  return (
    <section className="border-b border-line/60 bg-surface-muted">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <p className="text-caption font-medium uppercase tracking-wide text-clay">How TuitionHub works</p>
        <h2 className="mt-2 max-w-3xl text-h1 text-ink">From the first search to the last payment, one record.</h2>

        <ol className="mt-10 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2 lg:grid-cols-6">
          {journey.map((s, i) =>
          <li key={s.title} className={`flex flex-col p-5 ${i === journey.length - 1 ? 'bg-forest-50' : 'bg-white'}`}>
              <span className="text-caption font-medium text-ink-faint">{i + 1}</span>
              <s.icon className="mt-3 h-5 w-5 text-forest" aria-hidden="true" />
              <h3 className="mt-3 text-small font-semibold text-ink">{s.title}</h3>
              <p className="mt-1 text-caption text-ink-soft">{s.body}</p>
            </li>
          )}
        </ol>

        <div className="mt-10">
          <h3 className="text-h3 text-ink">Everyone does their part — and sees only their part</h3>
          <div className="mt-4 overflow-x-auto rounded-2xl border border-line bg-white">
            <table className="w-full min-w-[44rem] text-left text-small">
              <thead className="border-b border-line">
                <tr className="text-caption text-ink-faint">
                  <th scope="col" className="px-5 py-3 font-medium" />
                  <th scope="col" className="px-5 py-3 font-medium">Teacher</th>
                  <th scope="col" className="px-5 py-3 font-medium">Student</th>
                  <th scope="col" className="px-5 py-3 font-medium">Parent</th>
                  <th scope="col" className="px-5 py-3 font-medium">Institute</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {responsibilities.map((r) =>
                <tr key={r.area}>
                    <th scope="row" className="px-5 py-3 font-medium text-ink">{r.area}</th>
                    <td className="px-5 py-3 text-ink">{r.teacher}</td>
                    <td className="px-5 py-3 text-ink-soft">{r.student}</td>
                    <td className="px-5 py-3 text-ink-soft">{r.parent}</td>
                    <td className="px-5 py-3 text-ink-soft">{r.institute}</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>);

}