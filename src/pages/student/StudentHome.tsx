import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, InfoIcon, LinkIcon, MegaphoneIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { GuardianBanner } from '../../components/student/GuardianBanner';
import { ClassCard } from '../../components/cards/ClassCard';
import { useAuth } from '../../contexts/AuthContext';
import { classById } from '../../data/classes';
import { myClassIds, recommended, sponsored } from '../../data/feed';
import { announcements, upcomingSessions } from '../../data/people';

/**
 * Signed-in student home: a personalised education feed. Fixed order, no
 * infinite scroll — each block answers one question the student has today.
 */
export function StudentHome() {
  const { user } = useAuth();
  const first = user?.name.split(' ')[0] ?? 'there';
  const next = upcomingSessions[0];

  return (
    <AppShell
      role="Student"
      accountName={user?.name ?? 'Kasun Perera'}
      accountDetail={user?.detail ?? 'Grade 12 · A/L 2027'}
      initials={user?.initials ?? 'KP'}
      nav={studentNav}
      title={`Hi ${first}`}
      subtitle="Here's your week — classes, attendance and announcements in one place.">

      <div className="space-y-10">
        <GuardianBanner />

        {/* Next class */}
        <section aria-labelledby="next-class">
          <div className="rounded-2xl border border-line bg-forest p-6 text-white shadow-card">
            <p id="next-class" className="text-caption uppercase tracking-wide text-forest-100">
              Next class
            </p>
            <h2 className="mt-1.5 text-h2 text-white">{next.className}</h2>
            <p className="mt-1 text-small text-forest-100">
              {next.when} · {next.where} · {next.institute}
            </p>
            <div className="mt-5 flex flex-wrap gap-2">
              <button className="inline-flex h-10 items-center gap-2 rounded-xl bg-white px-4 text-small font-medium text-forest transition-colors duration-150 ease-out hover:bg-forest-50">
                <LinkIcon className="h-4 w-4" />
                Online link opens 5:30 PM
              </button>
              <Link
                to="/student/attendance"
                className="inline-flex h-10 items-center rounded-xl border border-white/25 px-4 text-small font-medium text-white transition-colors duration-150 ease-out hover:bg-white/10">
                
                Scan in at the hall
              </Link>
            </div>
          </div>
        </section>

        {/* Recommended */}
        <FeedSection
          title="Recommended for you"
          note="Based on your subjects, exam year and area. Organic — no one paid for these."
          link={{ to: '/search', label: 'Browse all' }}>
          
          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {recommended.map((r) => {
              const c = classById(r.classId);
              return c ? <ClassCard key={c.id} item={c} reason={r.reason} compact /> : null;
            })}
          </div>
        </FeedSection>

        {/* Sponsored */}
        <FeedSection
          title="Sponsored"
          note="Paid placements from institutes. Only shown when they match your subjects or exam year.">
          
          <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {sponsored.map((s) => {
              const c = classById(s.classId);
              return c ?
              <div key={c.id} className="space-y-2">
                  <ClassCard item={c} sponsoredBy={s.advertiser} compact />
                  <p className="flex items-start gap-1.5 px-1 text-caption text-ink-faint">
                    <InfoIcon className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    {s.relevance}
                  </p>
                </div> :
              null;
            })}
          </div>
        </FeedSection>

        <div className="grid gap-6 lg:grid-cols-2">
          {/* Announcements */}
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex items-center gap-2">
              <MegaphoneIcon className="h-4 w-4 text-forest" aria-hidden="true" />
              <h2 className="text-h3 text-ink">Announcements</h2>
            </div>
            <ul className="mt-4 space-y-4">
              {announcements.slice(0, 2).map((a) =>
              <li key={a.id}>
                  <p className="text-small font-medium text-ink">{a.title}</p>
                  <p className="mt-0.5 text-caption text-ink-soft">{a.body}</p>
                  <p className="mt-1 text-caption text-ink-faint">
                    {a.from} · {a.time}
                  </p>
                </li>
              )}
            </ul>
          </section>
        </div>

        {/* My classes */}
        <FeedSection title="My classes" link={{ to: '/student/schedule', label: 'Schedule' }}>
          <ul className="grid gap-3 md:grid-cols-3">
            {myClassIds.map((id) => {
              const c = classById(id);
              if (!c) return null;
              return (
                <li key={id}>
                  <Link
                    to={`/class/${c.id}`}
                    className="block rounded-2xl border border-line bg-white px-5 py-4 transition-colors duration-150 ease-out hover:border-line-strong">
                    
                    <p className="text-small font-medium text-ink">{c.title}</p>
                    <p className="mt-0.5 text-caption text-ink-soft">
                      {c.institute} · {c.day} {c.startTime}
                    </p>
                  </Link>
                </li>);

            })}
          </ul>
        </FeedSection>
      </div>
    </AppShell>);

}

function FeedSection({
  title,
  note,
  link,
  children





}: {title: string;note?: string;link?: {to: string;label: string;};children: React.ReactNode;}) {
  return (
    <section>
      <div className="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h2 className="text-h2 text-ink">{title}</h2>
          {note ? <p className="mt-1 text-small text-ink-soft">{note}</p> : null}
        </div>
        {link ?
        <Link to={link.to} className="inline-flex items-center gap-1.5 text-small font-medium text-forest">
            {link.label}
            <ArrowRightIcon className="h-3.5 w-3.5" />
          </Link> :
        null}
      </div>
      {children}
    </section>);

}