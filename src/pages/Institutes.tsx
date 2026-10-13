import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { MapPinIcon, SearchIcon } from 'lucide-react';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import { Badge, VerifiedBadge } from '../components/ui/Badge';
import { EmptyState } from '../components/ui/States';
import { institutes } from '../data/directory';

export function Institutes() {
  const [query, setQuery] = useState('');
  const rows = institutes.filter((i) =>
  `${i.name} ${i.city} ${i.subjects.join(' ')}`.toLowerCase().includes(query.toLowerCase())
  );

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main className="mx-auto max-w-[1200px] px-5 py-10 lg:px-8">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div className="max-w-2xl">
            <h1 className="text-h1 text-ink">Institutes</h1>
            <p className="mt-2 text-body text-ink-soft">
              Institutes currently listing classes on TuitionHub. “Verified institute” means
              the business registration and bank details were checked during onboarding.
            </p>
          </div>
          <div className="flex w-full max-w-sm items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5">
            <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
            <input
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Institute, area or subject"
              aria-label="Search institutes"
              className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
            
          </div>
        </div>

        {rows.length === 0 ?
        <div className="mt-8">
            <EmptyState
            icon={<SearchIcon className="h-5 w-5" />}
            title="No institutes match"
            body="More institutes are joining the Nugegoda–Maharagama corridor. Try a subject instead." />
          
          </div> :

        <ul className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            {rows.map((i) =>
          <li
            key={i.id}
            className="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-card">
            
                <h2 className="text-h3 text-ink">{i.name}</h2>
                <p className="mt-1 inline-flex items-center gap-1.5 text-small text-ink-soft">
                  <MapPinIcon className="h-3.5 w-3.5 text-ink-faint" aria-hidden="true" />
                  {i.city}
                </p>
                <div className="mt-1.5 min-h-[1.25rem]">
                  {i.verified ?
              <VerifiedBadge /> :

              <span className="text-caption text-ink-faint">Verification in progress</span>
              }
                </div>
                <p className="mt-3 text-small text-ink-soft">{i.about}</p>
                <div className="mt-4 flex flex-wrap gap-1.5">
                  {i.subjects.map((s) =>
              <Badge key={s}>{s}</Badge>
              )}
                </div>
                <div className="mt-auto flex items-center justify-between gap-3 pt-5">
                  <p className="text-caption text-ink-faint">
                    {i.classCount} classes · {i.mediums.join(', ')} · {i.modes.join(', ')}
                  </p>
                  <Link
                to={`/search?q=${encodeURIComponent(i.name)}`}
                className="text-small font-medium text-forest underline underline-offset-4">
                
                    View classes
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