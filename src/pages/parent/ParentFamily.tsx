import React, { useEffect } from 'react';
import { TicketIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Avatar } from '../../components/ui/Avatar';
import { LinkButton } from '../../components/ui/Button';
import { EmptyState } from '../../components/ui/States';
import { ParentSetupCard } from '../../components/parent/ParentSetupCard';
import { CHILD_LINK_NOTE } from '../../utils/parentSetup';
import { TrustBadges } from '../../components/ui/TrustBadges';
import { useAuth } from '../../contexts/AuthContext';
import { initialsOf } from '../../utils/format';

interface Child {
  id: string;
  name: string;
  grade: string;
  institutes: string[];
}

const initialChildren: Child[] = [
{ id: 'c1', name: 'Kasun Perera', grade: 'Grade 12 · A/L 2027', institutes: ['Sarasavi Academy', 'Lanka Science Centre'] },
{ id: 'c2', name: 'Nethmi Perera', grade: 'Grade 10 · O/L 2028', institutes: ['Horizon Learning Centre'] }];


/**
 * Children linked to this parent. Under C6 a parent links only with a single-use invitation code
 * issued by the child's institute; F3 adds the real "Link my child" step here. The legacy family
 * code, child claim codes, SMS invitations, approval requests and guardian ID checks are retired.
 */
export function ParentFamily() {
  const { user, update } = useAuth();
  const isNew = !!user && (user.childrenLinked ?? 0) === 0;
  const children = isNew ? [] : initialChildren;
  const profileDone = !!user?.profileComplete || user?.verification === 'verified';

  useEffect(() => {
    if (user && (user.childrenLinked ?? 0) !== children.length) update({ childrenLinked: children.length });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [children.length]);

  return (
    <AppShell
      role="Parent"
      accountName={user?.name ?? 'Mrs. Chamari Perera'}
      accountDetail={user?.detail ?? '2 children · Nugegoda'}
      initials={user?.initials ?? 'CP'}
      nav={parentNav}
      title="Family"
      subtitle="Every child linked to your account">

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-6">
          {children.length === 0 ?
          <>
              <EmptyState
              icon={<TicketIcon className="h-5 w-5" />}
              title="No child linked yet"
              body={CHILD_LINK_NOTE} />

              <ParentSetupCard />
            </> :

          <ul className="grid gap-4 md:grid-cols-2">
              {children.map((c) =>
            <li key={c.id} className="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-card">
                  <div className="flex items-center gap-3">
                    <Avatar initials={initialsOf(c.name)} />
                    <div className="min-w-0">
                      <p className="truncate text-h3 text-ink">{c.name}</p>
                      <p className="truncate text-caption text-ink-soft">{c.grade}</p>
                    </div>
                  </div>
                  <div className="mt-4">
                    <TrustBadges
                  flags={[
                  { label: 'Child linked', done: true },
                  { label: 'Enrollment verified', done: c.institutes.length > 0 }]
                  } />

                  </div>
                  <p className="mt-3 text-caption text-ink-faint">
                    {c.institutes.length ? c.institutes.join(' · ') : 'Not enrolled at any institute yet'}
                  </p>
                  <div className="mt-auto flex gap-2 pt-4">
                    <LinkButton to="/parent" size="sm" variant="secondary" className="flex-1">
                      Overview
                    </LinkButton>
                    <LinkButton to="/parent/fees" size="sm" variant="secondary" className="flex-1">
                      Fees
                    </LinkButton>
                  </div>
                </li>
            )}
            </ul>
          }
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Your account</h2>
            <div className="mt-3">
              <TrustBadges
                flags={[
                { label: 'Signed in with mobile', done: true },
                { label: 'Parent profile', done: profileDone },
                { label: 'Child linked', done: children.length > 0 }]
                } />

            </div>
          </section>
          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <h2 className="text-h3 text-ink">Linking a child</h2>
            <p className="mt-3 text-small text-ink-soft">{CHILD_LINK_NOTE}</p>
          </section>
        </aside>
      </div>
    </AppShell>);

}
