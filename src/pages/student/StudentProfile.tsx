import React, { useState } from 'react';
import { ShieldCheckIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { Avatar } from '../../components/ui/Avatar';
import { Badge } from '../../components/ui/Badge';
import { studentProfile } from '../../data/people';

const toggles = [
{
  id: 'discover',
  label: 'Let other students find me in Study',
  detail: 'Shows your first name, grade and subjects only — never your school or exact location.',
  initial: true
},
{
  id: 'circles',
  label: 'Allow Study Circle invites',
  detail: 'Hosts of circles for your exam year can invite you.',
  initial: true
},
{
  id: 'dm',
  label: 'Allow direct messages',
  detail: 'Only from students you have shared an activity with.',
  initial: false
}];


export function StudentProfile() {
  const [state, setState] = useState<Record<string, boolean>>(
    Object.fromEntries(toggles.map((t) => [t.id, t.initial]))
  );

  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title="Profile & privacy"
      subtitle="What other students can see about you">
      
      <div className="grid max-w-4xl gap-6 lg:grid-cols-[0.8fr_1.2fr]">
        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex items-center gap-4">
            <Avatar initials={studentProfile.initials} size="lg" />
            <div>
              <p className="text-h3 text-ink">{studentProfile.name}</p>
              <p className="text-caption text-ink-soft">
                {studentProfile.grade} · {studentProfile.examTarget}
              </p>
              <div className="mt-2">
                <Badge tone="success">
                  <ShieldCheckIcon className="h-3.5 w-3.5" aria-hidden="true" />
                  Verified through Sarasavi Academy
                </Badge>
              </div>
            </div>
          </div>
          <div className="mt-5">
            <p className="text-caption uppercase tracking-wide text-ink-faint">Subjects</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {studentProfile.subjects.map((s) =>
              <Badge key={s}>{s}</Badge>
              )}
            </div>
          </div>
          <p className="mt-5 text-caption text-ink-soft">
            Students who are enrolled in a class through a verified institute get a
            verified marker in Study. It is not an identity check, only a signal that
            the account is attached to a real enrolment.
          </p>
        </section>

        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <h2 className="text-h3 text-ink">Privacy</h2>
          <ul className="mt-4 divide-y divide-line">
            {toggles.map((t) =>
            <li key={t.id} className="flex items-start justify-between gap-4 py-4">
                <div>
                  <p className="text-small font-medium text-ink">{t.label}</p>
                  <p className="mt-0.5 text-caption text-ink-soft">{t.detail}</p>
                </div>
                <button
                role="switch"
                aria-checked={state[t.id]}
                aria-label={t.label}
                onClick={() => setState((s) => ({ ...s, [t.id]: !s[t.id] }))}
                className={`mt-1 h-6 w-11 shrink-0 rounded-full border transition-colors duration-150 ease-out ${
                state[t.id] ? 'border-forest bg-forest' : 'border-line bg-surface-sunk'}`
                }>
                
                  <span
                  className={`block h-5 w-5 rounded-full bg-white transition-transform duration-150 ease-out ${
                  state[t.id] ? 'translate-x-5' : 'translate-x-0.5'}`
                  } />
                
                </button>
              </li>
            )}
          </ul>
          <div className="mt-4 rounded-xl bg-surface-muted px-4 py-3">
            <p className="text-caption text-ink-soft">
              Your parent's account shows attendance, marks and payments for the classes
              they pay for. It does not show your Study activities, messages or profile.
            </p>
          </div>
        </section>
      </div>
    </AppShell>);

}