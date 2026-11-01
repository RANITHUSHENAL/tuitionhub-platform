import { useCallback, useEffect, useState } from 'react';
import { TicketIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { LinkChildForm } from '../../components/parent/LinkChildForm';
import { Avatar } from '../../components/ui/Avatar';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { FormAlert, LoadingLine } from '../../components/ui/FormMessage';
import { Pagination } from '../../components/ui/Pagination';
import { EmptyState, ErrorState } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import { useApiErrors } from '../../hooks/useApiErrors';
import { guardianApi, type LinkedChild, type Page } from '../../lib/api';
import { formatDate } from '../../utils/dates';
import { initialsOf } from '../../utils/format';

const relationshipLabel: Record<LinkedChild['relationship'], string> = {
  mother: 'Mother',
  father: 'Father',
  guardian: 'Guardian',
  aunt: 'Aunt',
  uncle: 'Uncle'
};

/**
 * Children linked to this account, from GET /parents/me/children (contract §9.5): only active
 * links, re-read on every visit, so a revoked link disappears on the next load. Linking uses an
 * invitation code from the child's institute (§9.2). Nothing on this page is sample data.
 */
export function ParentFamily() {
  const { user, account, refreshSession } = useAuth();
  const describe = useApiErrors();
  const [page, setPage] = useState(1);
  const [result, setResult] = useState<Page<LinkedChild> | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reload, setReload] = useState(0);
  const [linked, setLinked] = useState<string | null>(null);

  // Re-fetch for a different signed-in account; never show another account's children.
  const accountId = account?.user.uuid;

  useEffect(() => {
    let alive = true;
    setResult(null);
    setLoadError(null);
    guardianApi.
    myChildren({ page }).
    then((data) => alive && setResult(data)).
    catch((error: unknown) => alive && setLoadError(describe(error)));
    return () => {
      alive = false;
    };
  }, [page, reload, accountId, describe]);

  const onLinked = useCallback(
    ({ studentName }: {studentName: string;}) => {
      setLinked(`You are now linked to ${studentName}.`);
      setPage(1);
      setReload((n) => n + 1);
      // The session's link count drives the parent profile and menus.
      void refreshSession();
    },
    [refreshSession]
  );

  const count = result?.meta.total ?? 0;

  return (
    <AppShell
      role="Parent"
      accountName={user?.name ?? 'Parent'}
      accountDetail={result ? `${count} ${count === 1 ? 'child' : 'children'} linked` : 'Parent'}
      initials={user?.initials ?? 'PA'}
      nav={parentNav}
      title="Family"
      subtitle="Children linked to your account"
      sampleData={false}>

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-4">
          {loadError ?
          <ErrorState
            title="Your children couldn’t be loaded"
            body={loadError}
            action={<Button variant="secondary" size="sm" onClick={() => setReload((n) => n + 1)}>Try again</Button>} /> :

          !result ?
          <LoadingLine label="Loading linked children…" /> :
          result.data.length === 0 ?
          <EmptyState
            icon={<TicketIcon className="h-5 w-5" />}
            title="No child linked yet"
            body="Your child's institute will provide an invitation code to link your account. Enter it under Link my child." /> :


          <>
              <ul className="grid gap-4 md:grid-cols-2">
                {result.data.map((c) =>
              <li key={c.student.uuid} className="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-card">
                    <div className="flex items-center gap-3">
                      <Avatar initials={initialsOf(c.student.full_name) || 'C'} />
                      <div className="min-w-0">
                        <p className="truncate text-h3 text-ink">{c.student.full_name}</p>
                        <p className="truncate text-caption text-ink-soft">{c.student.grade.name}</p>
                      </div>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                      <Badge tone="success">Linked</Badge>
                      <Badge tone="neutral">{relationshipLabel[c.relationship]}</Badge>
                    </div>
                    <p className="mt-3 text-caption text-ink-faint">Linked {formatDate(c.linked_at)}</p>
                    <div className="mt-3 border-t border-line pt-3">
                      <p className="text-caption uppercase tracking-wide text-ink-faint">Classes</p>
                      {c.active_enrollments.length ?
                  <ul className="mt-1.5 space-y-1">
                          {c.active_enrollments.map((e) =>
                    <li key={e.uuid} className="text-small text-ink">
                              {e.class.title}
                              <span className="text-caption text-ink-soft"> · {e.institute.name}</span>
                            </li>
                    )}
                        </ul> :

                  <p className="mt-1.5 text-small text-ink-soft">Not enrolled in any class yet.</p>
                  }
                    </div>
                  </li>
              )}
              </ul>
              <Pagination meta={result.meta} onPage={setPage} label="Children" />
            </>
          }
        </div>

        <aside className="space-y-4">
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <h2 className="mb-3 text-h3 text-ink">Link my child</h2>
            <div className="space-y-3">
              <FormAlert tone="success" message={linked} />
              <LinkChildForm onLinked={onLinked} />
            </div>
          </section>
          <p className="px-1 text-caption text-ink-faint">
            If your child’s institute removes a link, the child disappears from this page the next time it loads.
          </p>
        </aside>
      </div>
    </AppShell>);

}
