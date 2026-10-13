import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { SearchIcon } from 'lucide-react';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import { Badge, VerifiedBadge } from '../components/ui/Badge';
import { Avatar } from '../components/ui/Avatar';
import { EmptyState } from '../components/ui/States';
import { teachers } from '../data/directory';
import { initialsOf } from '../utils/format';

export function Teachers() {
  const [query, setQuery] = useState('');
  const rows = teachers.filter((t) =>
  `${t.name} ${t.subject} ${t.institute}`.toLowerCase().includes(query.toLowerCase())
  );

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main className="mx-auto max-w-[1200px] px-5 py-10 lg:px-8">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div className="max-w-2xl">
            <h1 className="text-h1 text-ink">Teachers</h1>
            <p className="mt-2 text-body text-ink-soft">
              Teachers appear here only when their institute has chosen to list them.
              Every class stays attributed to the institute that runs it.
            </p>
          </div>
          <div className="flex w-full max-w-sm items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5">
            <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Teacher, subject or institute"
              aria-label="Search teachers"
              className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
            
          </div>
        </div>

        {rows.length === 0 ?
        <div className="mt-8">
            <EmptyState
            icon={<SearchIcon className="h-5 w-5" />}
            title="No teachers match"
            body="Try searching by subject, or browse classes directly." />
          
          </div> :

        <ul className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            {rows.map((t) =>
          <li key={t.id} className="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-card">
                <div className="flex items-center gap-3">
                  <Avatar initials={initialsOf(t.name.replace(/^(Mr|Mrs|Ms|Dr)\.\s/, ''))} />
                  <div className="min-w-0">
                    <h2 className="truncate text-h3 text-ink">{t.name}</h2>
                    <p className="truncate text-small text-ink-soft">
                      {t.subject} · {t.institute}
                    </p>
                  </div>
                </div>
                <div className="mt-2 min-h-[1.25rem]">
                  {t.verified ? <VerifiedBadge label="Verified teacher" /> : null}
                </div>
                <p className="mt-2 text-small text-ink-soft">{t.about}</p>
                <div className="mt-4 flex flex-wrap gap-1.5">
                  {t.examTargets.map((e) =>
              <Badge key={e}>{e}</Badge>
              )}
                  <Badge>{t.medium}</Badge>
                </div>
                <div className="mt-auto pt-5">
                  <Link
                to={`/search?q=${encodeURIComponent(t.subject)}`}
                className="text-small font-medium text-forest underline underline-offset-4">
                
                    View {t.subject} classes
                  </Link>
                </div>
              </li>
          )}
          </ul>
        }
      </main>
      <SiteFooter />
    </div>);

}