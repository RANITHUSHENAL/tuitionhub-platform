import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon } from 'lucide-react';
import { institutes, teachers } from '../../data/directory';

const subjectLinks = ['Physics', 'Chemistry', 'Combined Mathematics', 'Biology', 'ICT', 'Accounting', 'English', 'Science'];
const examLinks = ['O/L 2027', 'O/L 2028', 'A/L 2027', 'A/L 2028'];
const locationLinks = ['Colombo', 'Nugegoda', 'Maharagama', 'Dehiwala', 'Kandy', 'Galle', 'Online'];

/** Compact discovery shortcuts — deliberately secondary to real listings. */
export function ExploreSection() {
  return (
    <section className="border-b border-line/60">
      <div className="mx-auto max-w-[1200px] px-5 py-14 lg:px-8 lg:py-16">
        <h2 className="text-h2 text-ink">Explore tuition your way</h2>
        <div className="mt-8 grid gap-x-10 gap-y-8 md:grid-cols-2 lg:grid-cols-[1.3fr_0.9fr_1fr]">
          <Group title="By subject">
            {subjectLinks.map((s) =>
            <Chip key={s} to={`/search?q=${encodeURIComponent(s)}`}>
                {s}
              </Chip>
            )}
          </Group>
          <Group title="By exam">
            {examLinks.map((e) =>
            <Chip key={e} to={`/search?q=${encodeURIComponent(e)}`}>
                {e}
              </Chip>
            )}
          </Group>
          <Group title="By location">
            {locationLinks.map((l) =>
            <Chip key={l} to={`/search?where=${encodeURIComponent(l)}`}>
                {l}
              </Chip>
            )}
          </Group>
        </div>
        <div className="mt-8 grid gap-3 border-t border-line pt-8 sm:grid-cols-2">
          <DirectoryLink
            to="/institutes"
            title="Browse institutes"
            body={`${institutes.length} institutes · by city and district`} />
          
          <DirectoryLink
            to="/teachers"
            title="Browse teachers"
            body={`${teachers.length} teachers · and the classes they run`} />
          
        </div>
      </div>
    </section>);

}

function Group({ title, children }: {title: string;children: React.ReactNode;}) {
  return (
    <div>
      <h3 className="text-small font-semibold text-ink">{title}</h3>
      <div className="mt-3 flex flex-wrap gap-2">{children}</div>
    </div>);

}

function Chip({ to, children }: {to: string;children: React.ReactNode;}) {
  return (
    <Link
      to={to}
      className="rounded-full border border-line bg-white px-3.5 py-1.5 text-small text-ink-soft transition-colors duration-150 ease-out hover:border-forest hover:text-forest">
      
      {children}
    </Link>);

}

function DirectoryLink({ to, title, body }: {to: string;title: string;body: string;}) {
  return (
    <Link
      to={to}
      className="group flex items-center justify-between gap-4 rounded-2xl border border-line bg-white px-5 py-4 transition-colors duration-150 ease-out hover:border-forest">
      
      <span>
        <span className="block text-small font-semibold text-ink">{title}</span>
        <span className="block text-caption text-ink-soft">{body}</span>
      </span>
      <ArrowRightIcon className="h-4 w-4 text-ink-faint transition-transform duration-150 ease-out group-hover:translate-x-0.5" aria-hidden="true" />
    </Link>);

}