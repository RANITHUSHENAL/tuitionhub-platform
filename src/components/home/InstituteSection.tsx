import React from 'react';
import { LinkButton } from '../ui/Button';
import { Badge } from '../ui/Badge';
import { SectionHeading } from './SectionHeading';
import { lkr } from '../../utils/format';

const assurances = [
{
  title: 'You keep control of your listing',
  body: 'Classes are listed under your institute. Teacher names are shown publicly only if you switch it on.'
},
{
  title: 'Money settles to you daily',
  body: 'Collections are settled to the institute account on the next working day, with an exportable reconciliation report.'
},
{
  title: 'Add it alongside what you have',
  body: 'Run TuitionHub for parents and payments first. Migrate the rest of your admin whenever you are ready — data export is always available.'
}];


export function InstituteSection() {
  return (
    <section id="institutes" className="scroll-mt-20 border-b border-line/60 bg-surface-muted">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <SectionHeading
          eyebrow="For institutes"
          title="Run your institute more efficiently"
          body="Classes, students, teachers, fees and payments in one place. Your teachers mark attendance and set exams for their own classes; you get the overview, the reports and the settlements."
          action={
          <LinkButton to="/auth?role=institute" variant="secondary">
              Institute sign-in
            </LinkButton>
          } />
        

        <div className="mt-10 grid gap-8 lg:grid-cols-[0.85fr_1.15fr] lg:items-start">
          <div className="space-y-6">
            {assurances.map((a) =>
            <div key={a.title} className="border-l-2 border-forest-100 pl-4">
                <h3 className="text-h3 text-ink">{a.title}</h3>
                <p className="mt-1.5 text-small text-ink-soft">{a.body}</p>
              </div>
            )}
          </div>

          <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <div className="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
              <div>
                <p className="text-h3 text-ink">Sarasavi Academy</p>
                <p className="text-caption text-ink-soft">Nugegoda (main) · Growth plan</p>
              </div>
              <Badge tone="success">Verified institute</Badge>
            </div>

            <div className="grid grid-cols-2 gap-px bg-line lg:grid-cols-4">
              <Metric label="Students" value="1,284" />
              <Metric label="Classes today" value="7" />
              <Metric label="Attendance today" value="86%" detail="5 of 7 marked" />
              <Metric label="Outstanding" value={lkr(412000)} detail="38 students" />
            </div>

            <div className="px-6 py-5">
              <div className="flex items-center justify-between">
                <p className="text-caption uppercase tracking-wide text-ink-faint">
                  Collected this month
                </p>
                <p className="text-caption text-ink-soft">Next settlement: tomorrow 9:00 AM</p>
              </div>
              <p className="mt-1.5 text-h1 text-ink">{lkr(3186000)}</p>
              <div className="mt-3 h-2 overflow-hidden rounded-full bg-surface-sunk">
                <div className="h-full w-[88%] rounded-full bg-forest" />
              </div>
              <p className="mt-2 text-caption text-ink-soft">
                88% of billed fees collected · LKR 412,000 outstanding across 38 students
              </p>
            </div>

            <div className="border-t border-line bg-surface-muted px-6 py-4">
              <p className="text-caption uppercase tracking-wide text-ink-faint">
                Teacher activity
              </p>
              <ul className="mt-2 space-y-1.5 text-small text-ink-soft">
                <li>Mr. R. Jayasinghe marked attendance · Combined Maths 79/84 · 12 min ago</li>
                <li>Mrs. S. Fernando entered marks · O/L Maths Paper 6 · 1 hour ago</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </section>);

}

function Metric({
  label,
  value,
  detail




}: {label: string;value: string;detail?: string;}) {
  return (
    <div className="bg-white px-5 py-4">
      <p className="text-caption uppercase tracking-wide text-ink-faint">{label}</p>
      <p className="mt-1 text-h3 text-ink">{value}</p>
      {detail ? <p className="text-caption text-ink-soft">{detail}</p> : null}
    </div>);

}