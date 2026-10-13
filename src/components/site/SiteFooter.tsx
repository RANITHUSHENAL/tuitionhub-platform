import React from 'react';
import { Link } from 'react-router-dom';

const columns = [
{
  title: 'Discover',
  links: [
  { to: '/search', label: 'Find a class' },
  { to: '/class/cls-01', label: 'Class profile' },
  { to: '/study', label: 'Study activities' },
  { to: '/study/circles/cir-01', label: 'Study Circles' }]

},
{
  title: 'Families',
  links: [
  { to: '/parent', label: 'Parent dashboard' },
  { to: '/parent/fees', label: 'Fees & payments' },
  { to: '/parent/attendance', label: 'Attendance' },
  { to: '/student', label: 'Student app' }]

},
{
  title: 'Institutes',
  links: [
  { to: '/institute', label: 'Institute dashboard' },
  { to: '/institute/classes', label: 'Classes' },
  { to: '/institute/attendance', label: 'Attendance' },
  { to: '/institute/fees', label: 'Fees & settlements' }]

},
{
  title: 'Product',
  links: [
  { to: '/blueprint', label: 'Product blueprint' },
  { to: '/design-system', label: 'Design system' },
  { to: '/study/create', label: 'Create an activity' },
  { to: '/messages', label: 'Messages' }]

}];


export function SiteFooter() {
  return (
    <footer className="border-t border-line bg-surface-muted">
      <div className="mx-auto max-w-[1200px] px-5 py-12 lg:px-8">
        <div className="grid gap-10 md:grid-cols-[1.4fr_repeat(4,1fr)]">
          <div>
            <p className="text-h3 font-bold tracking-tight text-ink">
              Tuition<span className="text-forest">Hub</span>
            </p>
            <p className="mt-2 max-w-xs text-small text-ink-soft">
              Discover your class. Manage your tuition. Track progress. Pay with confidence.
              Find people to learn with.
            </p>
            <p className="mt-4 text-caption text-ink-faint">
              Early-stage prototype. Names, classes, institutes and figures shown are
              fictional placeholders.
            </p>
          </div>
          {columns.map((col) =>
          <nav key={col.title} aria-label={col.title}>
              <h2 className="text-caption font-semibold uppercase tracking-wide text-ink-faint">
                {col.title}
              </h2>
              <ul className="mt-3 space-y-2">
                {col.links.map((l) =>
              <li key={l.to + l.label}>
                    <Link
                  to={l.to}
                  className="text-small text-ink-soft transition-colors duration-150 ease-out hover:text-forest">
                  
                      {l.label}
                    </Link>
                  </li>
              )}
              </ul>
            </nav>
          )}
        </div>
        <div className="mt-10 flex flex-col gap-2 border-t border-line pt-6 text-caption text-ink-faint sm:flex-row sm:items-center sm:justify-between">
          <span>TuitionHub · Sri Lanka · සිංහල | தமிழ் | English</span>
          <span>Support line and settlement schedule shown in the institute portal.</span>
        </div>
      </div>
    </footer>);

}