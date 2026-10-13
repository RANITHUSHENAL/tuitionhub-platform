import React from 'react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { PrototypeNote } from '../../components/ui/States';
import { marks, parentProfile } from '../../data/people';

export function ParentMarks() {
  const physics = marks.filter((m) => m.subject === 'Physics');

  return (
    <AppShell
      role="Parent"
      accountName={parentProfile.name}
      accountDetail="2 children · Nugegoda"
      initials="CP"
      nav={parentNav}
      title="Marks"
      subtitle="Kasun Perera · every assessment entered by the class teacher">
      
      <div className="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
        <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
          <h2 className="border-b border-line px-6 py-4 text-h3 text-ink">
            Recent assessments
          </h2>
          <ul className="divide-y divide-line">
            {marks.map((m) => {
              const pct = Math.round(m.score / m.outOf * 100);
              const above = m.score >= m.classAverage;
              return (
                <li key={m.id} className="px-6 py-4">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-small font-medium text-ink">{m.assessment}</p>
                      <p className="text-caption text-ink-soft">
                        {m.subject} · {m.date}
                      </p>
                    </div>
                    <div className="text-right">
                      <p className="text-h3 text-ink">
                        {m.score} <span className="text-ink-faint">/ {m.outOf}</span>
                      </p>
                      <Badge tone={above ? 'success' : 'warn'}>
                        Class average {m.classAverage}
                      </Badge>
                    </div>
                  </div>
                  <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                    <div
                      className={`h-full rounded-full ${above ? 'bg-forest' : 'bg-state-warn'}`}
                      style={{ width: `${pct}%` }} />
                    
                  </div>
                </li>);

            })}
          </ul>
        </section>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Physics trend</h2>
            <p className="mt-1 text-caption text-ink-soft">Model papers, this term</p>
            <div className="mt-5 flex h-36 items-end gap-3">
              {physics.
              slice().
              reverse().
              map((m) =>
              <div key={m.id} className="flex flex-1 flex-col items-center gap-2">
                    <div className="flex h-full w-full items-end">
                      <div
                    className="w-full rounded-t-lg bg-forest"
                    style={{ height: `${m.score}%` }}
                    role="img"
                    aria-label={`${m.assessment}: ${m.score} out of ${m.outOf}`} />
                  
                    </div>
                    <span className="text-caption text-ink-soft">{m.score}</span>
                  </div>
              )}
            </div>
            <p className="mt-3 text-small text-ink-soft">
              Up 12 marks since February, and now above the class average.
            </p>
          </section>

          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <h2 className="text-h3 text-ink">Talking about marks</h2>
            <p className="mt-2 text-small text-ink-soft">
              Marks are shown with the class average so a number can be read in context
              before it becomes an argument at home.
            </p>
            <PrototypeNote>Assessment records are fictional.</PrototypeNote>
          </section>
        </aside>
      </div>
    </AppShell>);

}