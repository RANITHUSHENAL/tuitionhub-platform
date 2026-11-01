import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, MegaphoneIcon, TicketIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Badge, PaymentBadge } from '../../components/ui/Badge';
import { Button, LinkButton } from '../../components/ui/Button';
import { LoadingLine } from '../../components/ui/FormMessage';
import { EmptyState, ErrorState } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import { useApiErrors } from '../../hooks/useApiErrors';
import { guardianApi, type LinkedChild } from '../../lib/api';
import { announcements, invoices, upcomingSessions } from '../../data/people';
import { formatDate } from '../../utils/dates';
import { lkr } from '../../utils/format';

const relationshipLabel: Record<LinkedChild['relationship'], string> = {
  mother: 'Mother',
  father: 'Father',
  guardian: 'Guardian',
  aunt: 'Aunt',
  uncle: 'Uncle'
};

/**
 * Parent home. The children, and each child's classes, are real: GET /parents/me/children
 * (contract §9.5), active links only, re-read on every visit. Fees, upcoming classes and
 * announcements are not on the API yet, so they stay as sample widgets, each labelled
 * "Sample data", and never attached to a real child.
 */
export function ParentOverview() {
  const { user, account } = useAuth();
  const describe = useApiErrors();
  const [children, setChildren] = useState<LinkedChild[] | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [reload, setReload] = useState(0);
  const [selected, setSelected] = useState<string | null>(null);

  // Re-fetch for a different signed-in account; never show another account's children.
  const accountId = account?.user.uuid;

  useEffect(() => {
    let alive = true;
    setChildren(null);
    setLoadError(null);
    guardianApi.
    myChildren({ per_page: 100 }).
    then((page) => alive && setChildren(page.data)).
    catch((error: unknown) => alive && setLoadError(describe(error)));
    return () => {
      alive = false;
    };
  }, [accountId, reload, describe]);

  const child = children?.find((c) => c.student.uuid === selected) ?? children?.[0] ?? null;
  const firstName = user?.name.split(' ')[0] || 'there';
  const due = invoices.filter((i) => i.status === 'pending' || i.status === 'failed');

  return (
    <AppShell
      role="Parent"
      accountName={user?.name ?? 'Parent'}
      accountDetail={children ? `${children.length} ${children.length === 1 ? 'child' : 'children'} linked` : 'Parent'}
      initials={user?.initials ?? 'PA'}
      nav={parentNav}
      title={`Hi ${firstName}`}
      subtitle="Here is where each child stands."
      actions={
      children && children.length > 1 ?
      <div className="flex rounded-xl border border-line bg-white p-1">
            {children.map((c) =>
        <button
          key={c.student.uuid}
          onClick={() => setSelected(c.student.uuid)}
          aria-pressed={c.student.uuid === child?.student.uuid}
          className={`rounded-lg px-3 py-1.5 text-small font-medium transition-colors duration-150 ease-out ${
          c.student.uuid === child?.student.uuid ? 'bg-forest text-white' : 'text-ink-soft hover:text-ink'}`
          }>

                {c.student.full_name.split(' ')[0]}
              </button>
        )}
          </div> :
      undefined
      }>

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-6">
          {loadError ?
          <ErrorState
            title="Your children couldn’t be loaded"
            body={loadError}
            action={<Button variant="secondary" size="sm" onClick={() => setReload((n) => n + 1)}>Try again</Button>} /> :

          !children ?
          <LoadingLine label="Loading your children…" /> :
          !child ?
          <EmptyState
            icon={<TicketIcon className="h-5 w-5" />}
            title="No child linked yet"
            body="Your child's institute will provide an invitation code to link your account."
            action={<LinkButton to="/parent/family">Link my child</LinkButton>} /> :


          <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
              <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
                <div>
                  <h2 className="text-h3 text-ink">{child.student.full_name}</h2>
                  <p className="text-caption text-ink-soft">
                    {child.student.grade.name} · {relationshipLabel[child.relationship]} · linked {formatDate(child.linked_at)}
                  </p>
                </div>
                <Badge tone="neutral">
                  {child.active_enrollments.length} {child.active_enrollments.length === 1 ? 'class' : 'classes'} enrolled
                </Badge>
              </div>
              <div className="px-6 py-4">
                <p className="text-caption uppercase tracking-wide text-ink-faint">Classes</p>
                {child.active_enrollments.length ?
              <ul className="mt-2 divide-y divide-line">
                    {child.active_enrollments.map((e) =>
                <li key={e.uuid} className="py-2.5">
                        <p className="text-small font-medium text-ink">{e.class.title}</p>
                        <p className="text-caption text-ink-soft">{e.institute.name}</p>
                      </li>
                )}
                  </ul> :

              <p className="mt-2 text-small text-ink-soft">Not enrolled in any class yet.</p>
              }
              </div>
            </section>
          }

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <h2 className="flex items-center gap-2 text-h3 text-ink">
                Needs your attention <Badge tone="neutral">Sample data</Badge>
              </h2>
              <Link to="/parent/fees" className="text-small font-medium text-forest">
                All fees
              </Link>
            </div>
            <ul className="mt-4 space-y-3">
              {due.map((inv) =>
              <li
                key={inv.id}
                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line px-4 py-3">

                  <div className="min-w-0">
                    <p className="text-small font-medium text-ink">{inv.className}</p>
                    <p className="text-caption text-ink-soft">
                      {inv.month} · {inv.institute} · {lkr(inv.amount)}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <PaymentBadge status={inv.status} />
                    <LinkButton to="/parent/fees" size="sm">
                      {inv.status === 'failed' ? 'Retry payment' : 'Pay now'}
                    </LinkButton>
                  </div>
                </li>
              )}
            </ul>
          </section>

          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="flex items-center gap-2 text-h3 text-ink">
              Upcoming classes <Badge tone="neutral">Sample data</Badge>
            </h2>
            <ul className="mt-4 divide-y divide-line">
              {upcomingSessions.map((s) =>
              <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 py-3">
                  <div>
                    <p className="text-small font-medium text-ink">{s.className}</p>
                    <p className="text-caption text-ink-soft">
                      {s.institute} · {s.where}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="text-small text-ink">{s.when}</p>
                    <p className="text-caption text-ink-faint">
                      {s.mode}
                      {s.link ? ` · ${s.link}` : ''}
                    </p>
                  </div>
                </li>
              )}
            </ul>
          </section>
        </div>

        <div className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-wrap items-center gap-2">
              <MegaphoneIcon className="h-4 w-4 text-forest" aria-hidden="true" />
              <h2 className="text-h3 text-ink">Announcements</h2>
              <Badge tone="neutral">Sample data</Badge>
            </div>
            <ul className="mt-4 space-y-4">
              {announcements.map((a) =>
              <li key={a.id}>
                  <p className="text-small font-medium text-ink">{a.title}</p>
                  <p className="mt-0.5 text-caption text-ink-soft">{a.body}</p>
                  <p className="mt-1 text-caption text-ink-faint">
                    {a.from} · {a.time} · {a.channel}
                  </p>
                </li>
              )}
            </ul>
            <Link
              to="/parent/announcements"
              className="mt-4 inline-flex items-center gap-1.5 text-small font-medium text-forest">

              All announcements
              <ArrowRightIcon className="h-3.5 w-3.5" />
            </Link>
          </section>

          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <h2 className="text-h3 text-ink">How TuitionHub shows information</h2>
            <p className="mt-2 text-small text-ink-soft">
              You see the children linked to your account and their classes. You do not see your child's location.
            </p>
            <p className="mt-2 text-caption text-ink-faint">
              Your children and their classes are real. Fees, upcoming classes and announcements are sample data for now.
            </p>
          </section>
        </div>
      </div>
    </AppShell>);

}
