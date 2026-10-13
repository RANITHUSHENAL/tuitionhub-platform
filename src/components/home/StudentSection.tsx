import React from 'react';
import { CalendarIcon, ClipboardCheckIcon, FileQuestionIcon, LinkIcon } from 'lucide-react';
import { LinkButton } from '../ui/Button';
import { SectionHeading } from './SectionHeading';

const points = [
{
  icon: <CalendarIcon className="h-4.5 w-4.5" />,
  title: 'One schedule',
  body: 'Classes from every institute you attend, plus the study sessions you have joined.'
},
{
  icon: <LinkIcon className="h-4.5 w-4.5" />,
  title: 'Links that just work',
  body: 'Online class links on the class page, 30 minutes before start. No WhatsApp hunting.'
},
{
  icon: <ClipboardCheckIcon className="h-4.5 w-4.5" />,
  title: 'Scan in at the hall',
  body: 'Attendance by a session QR that changes every few seconds — no card to forget.'
},
{
  icon: <FileQuestionIcon className="h-4.5 w-4.5" />,
  title: 'Exams and results',
  body: 'Timed online papers from your teacher, with marks and the class average after.'
}];


export function StudentSection() {
  return (
    <section className="border-b border-line/60 bg-surface-muted">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <SectionHeading
          eyebrow="For students"
          title="Your tuition week, in one place"
          body="Your classes, schedule, attendance and results — plus classes recommended for your subjects and exam year, and study sessions near you. Sign up with just a phone number; under 18s connect a parent within 72 hours."
          action={
          <LinkButton to="/auth?mode=create&role=student" variant="secondary">
              Create a student account
            </LinkButton>
          } />
        
        <ul className="mt-10 grid gap-x-8 gap-y-8 sm:grid-cols-2 lg:grid-cols-4">
          {points.map((p) =>
          <li key={p.title}>
              <span className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-forest-50 text-forest">
                {p.icon}
              </span>
              <h3 className="mt-3 text-h3 text-ink">{p.title}</h3>
              <p className="mt-1.5 text-small text-ink-soft">{p.body}</p>
            </li>
          )}
        </ul>
      </div>
    </section>);

}