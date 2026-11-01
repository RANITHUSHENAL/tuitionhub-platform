import React from 'react';
import { ShieldCheckIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { Avatar } from '../../components/ui/Avatar';
import { Badge } from '../../components/ui/Badge';
import { studentProfile } from '../../data/people';

/** Study discovery, Study Circles and direct-message settings are hidden: those features are out of sprint scope (C7). */
export function StudentProfile() {
  return (
    <AppShell
      role="Student"
      accountName={studentProfile.name}
      accountDetail={`${studentProfile.grade} · ${studentProfile.examTarget}`}
      initials={studentProfile.initials}
      nav={studentNav}
      title="Profile & privacy"
      subtitle="Your profile and what your parent can see">
      
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
        </section>

        <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <h2 className="text-h3 text-ink">Privacy</h2>
          <div className="mt-4 rounded-xl bg-surface-muted px-4 py-3">
            <p className="text-caption text-ink-soft">
              Your parent's account shows attendance and payments for the classes they
              pay for. It does not show your profile.
            </p>
          </div>
        </section>
      </div>
    </AppShell>);

}