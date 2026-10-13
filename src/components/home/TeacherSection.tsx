import React from 'react';
import { BuildingIcon, CheckIcon, ClipboardCheckIcon, FileQuestionIcon, MegaphoneIcon, PencilLineIcon, PresentationIcon } from 'lucide-react';
import { LinkButton } from '../ui/Button';
import { Badge } from '../ui/Badge';

const independent = ['Create your own classes and list them publicly', 'Set schedules and fees', 'Collect payments, settled to you', 'Attendance, marks, exams and announcements'];
const institute = ['Request to join your institute — they approve', 'See only the classes assigned to you', 'Mark attendance, enter marks, set exams', 'The institute keeps fees and admin'];

/** Teachers as a first-class stakeholder, independent of institutes. */
export function TeacherSection() {
  return (
    <section id="teachers" className="scroll-mt-20 border-b border-line/60">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <div className="grid gap-12 lg:grid-cols-[1fr_1fr] lg:items-center">
          <div>
            <p className="text-caption font-medium uppercase tracking-wide text-clay">For teachers</p>
            <h2 className="mt-2 text-h1 text-ink">Built for teachers</h2>
            <p className="mt-3 max-w-xl text-body text-ink-soft">
              Your classes, your students, your marks — organised in one place, whether you run
              your own classes, teach at an institute, or both.
            </p>

            <div className="mt-8 grid gap-4 sm:grid-cols-2">
              <Track icon={<PresentationIcon className="h-5 w-5" />} title="Independent teacher" lead="Manage your own classes." items={independent} />
              <Track icon={<BuildingIcon className="h-5 w-5" />} title="Institute teacher" lead="Work seamlessly with your institute." items={institute} />
            </div>

            <div className="mt-8 flex flex-wrap items-center gap-3">
              <LinkButton to="/auth/teacher" size="lg">
                I’m a teacher
              </LinkButton>
              <span className="text-caption text-ink-faint">Free to start. No institute needed.</span>
            </div>
          </div>

          <TeacherPreview />
        </div>
      </div>
    </section>);

}

function Track({ icon, title, lead, items }: {icon: React.ReactNode;title: string;lead: string;items: string[];}) {
  return (
    <div className="flex h-full flex-col rounded-2xl border border-line bg-white p-5">
      <span className="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-forest-50 text-forest">{icon}</span>
      <h3 className="mt-3 text-h3 text-ink">{title}</h3>
      <p className="mt-1 text-small text-ink-soft">{lead}</p>
      <ul className="mt-4 space-y-2">
        {items.map((i) =>
        <li key={i} className="flex gap-2 text-caption text-ink-soft">
            <CheckIcon className="mt-0.5 h-3.5 w-3.5 shrink-0 text-forest" aria-hidden="true" />
            {i}
          </li>
        )}
      </ul>
    </div>);

}

function TeacherPreview() {
  return (
    <div className="rounded-3xl border border-line bg-surface-muted p-4 shadow-lift sm:p-6" aria-hidden="true">
      <div className="flex items-center justify-between">
        <p className="text-small font-semibold text-ink">Good evening, Nimal</p>
        <Badge tone="neutral">Sarasavi Academy</Badge>
      </div>
      <div className="mt-4 rounded-2xl bg-forest p-5 text-white">
        <p className="text-caption text-forest-100">Your next session</p>
        <p className="mt-1 text-h3 text-white">A/L Physics — Theory & Revision</p>
        <p className="mt-1 text-caption text-forest-100">Today · 6:00 PM · Hall B · 32 students</p>
        <div className="mt-4 inline-flex h-9 items-center gap-2 rounded-xl bg-white px-3.5 text-caption font-medium text-forest">
          <ClipboardCheckIcon className="h-4 w-4" /> Start attendance
        </div>
      </div>
      <div className="mt-4 grid grid-cols-4 gap-2">
        {[
        { icon: ClipboardCheckIcon, label: 'Attendance' },
        { icon: PencilLineIcon, label: 'Marks' },
        { icon: FileQuestionIcon, label: 'Exam' },
        { icon: MegaphoneIcon, label: 'Notice' }].
        map((a) =>
        <div key={a.label} className="flex flex-col items-center gap-1.5 rounded-xl border border-line bg-white px-2 py-3">
            <a.icon className="h-4 w-4 text-forest" />
            <span className="text-caption text-ink-soft">{a.label}</span>
          </div>
        )}
      </div>
      <div className="mt-4 rounded-2xl border border-line bg-white p-4">
        <p className="text-caption text-ink-faint">Recent</p>
        <p className="mt-1 text-small text-ink">18 new submissions — Waves Unit Test</p>
        <p className="mt-2 text-small text-ink">O/L Science closed · 11 of 12 present</p>
      </div>
    </div>);

}