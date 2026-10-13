import React from 'react';
import { MegaphoneIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Badge } from '../../components/ui/Badge';
import { announcements, parentProfile } from '../../data/people';

export function ParentAnnouncements() {
  return (
    <AppShell
      role="Parent"
      accountName={parentProfile.name}
      accountDetail="2 children · Nugegoda"
      initials="CP"
      nav={parentNav}
      title="Announcements"
      subtitle="From the institutes and teachers of the classes you pay for">
      
      <div className="max-w-3xl space-y-4">
        {announcements.map((a) =>
        <article key={a.id} className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex items-center gap-2.5">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-forest-50 text-forest">
                  <MegaphoneIcon className="h-4 w-4" aria-hidden="true" />
                </span>
                <div>
                  <h2 className="text-h3 text-ink">{a.title}</h2>
                  <p className="text-caption text-ink-soft">
                    {a.from} · {a.time}
                  </p>
                </div>
              </div>
              <Badge tone={a.channel === 'App + SMS' ? 'success' : 'neutral'}>
                {a.channel}
              </Badge>
            </div>
            <p className="mt-3 text-small text-ink-soft">{a.body}</p>
          </article>
        )}

        <p className="pt-2 text-caption text-ink-faint">
          Cancellations and hall changes are always sent by SMS as well, so a parent
          without the app still gets them.
        </p>
      </div>
    </AppShell>);

}