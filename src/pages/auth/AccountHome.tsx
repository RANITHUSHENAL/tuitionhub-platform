import { useState } from 'react';
import { Navigate, useNavigate } from 'react-router-dom';
import { BuildingIcon, ChevronRightIcon, TicketIcon } from 'lucide-react';
import { SiteHeader } from '../../components/site/SiteHeader';
import { SiteFooter } from '../../components/site/SiteFooter';
import { Button } from '../../components/ui/Button';
import { FormAlert } from '../../components/ui/FormMessage';
import { InstituteForm } from '../../components/institute/InstituteForm';
import { LinkChildForm } from '../../components/parent/LinkChildForm';
import { TeacherProfileCard } from '../../components/teacher/TeacherProfileCard';
import { useAuth } from '../../contexts/AuthContext';
import { roleHome, roleLabel } from '../../data/accounts';
import { instituteApi, type InstituteInput } from '../../lib/api';
import { identityFromAccount } from '../../lib/identity';
import { formatSriLankanMobile } from '../../lib/phone';
import type { AuthUser } from '../../types';

/**
 * The signed-in account (contract §4.4). Everything here is real: the roles the session reports,
 * registering an institute (§5.1), the caller's teacher profile (§6.1) and linking a child with an
 * invitation code (§9.2). Access is never chosen here — it comes from institutes and invitations.
 */
export function AccountHome() {
  const { account, status, profiles, signOut, refreshSession, switchProfile } = useAuth();
  const navigate = useNavigate();
  const [registering, setRegistering] = useState(false);
  const [linkedMessage, setLinkedMessage] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  if (status === 'loading') {
    return (
      <div className="min-h-screen bg-canvas">
        <SiteHeader />
        <p role="status" className="mx-auto max-w-[1200px] px-5 py-10 text-small text-ink-soft lg:px-8">
          Checking whether you’re signed in…
        </p>
      </div>);

  }

  if (!account) return <Navigate to="/auth?next=%2Faccount" replace />;
  if (account.onboarding_required) return <Navigate to="/onboarding" replace />;

  const isPlatformAdmin = account.roles.some((r) => r.role === 'super_admin');

  const open = (p: AuthUser) => {
    switchProfile(p.id, p.workspaces?.find((w) => w.status === 'active')?.id);
    navigate(roleHome[p.role]);
  };

  /** Re-reads the session, then opens the new profile it reports. False if it isn't there yet. */
  const openNewProfile = async (match: (p: AuthUser) => boolean, path: string) => {
    const next = await refreshSession();
    const profile = next ? identityFromAccount(next).profiles.find(match) : undefined;
    if (!profile) return false;
    switchProfile(profile.id);
    navigate(path);
    return true;
  };

  const register = async (input: InstituteInput) => {
    const created = await instituteApi.create(input);
    const opened = await openNewProfile(
      (p) => p.institute?.uuid === created.uuid && p.institute.role === 'institute_admin',
      '/institute/profile'
    );
    if (!opened) {
      // Created, but the session didn't reload: never leave the form up to be submitted twice.
      setRegistering(false);
      setNotice(`${created.name} is registered. Reload the page to open it.`);
    }
  };

  const logout = async () => {
    await signOut();
    navigate('/');
  };

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main className="mx-auto max-w-[880px] space-y-6 px-5 py-10 lg:px-8">
        <div>
          <h1 className="text-h1 text-ink">Your account</h1>
          <p className="mt-2 text-body text-ink-soft">
            Signed in as {account.user.name} · {formatSriLankanMobile(account.user.phone)}
          </p>
        </div>

        {isPlatformAdmin ?
        <FormAlert
          tone="success"
          message="Your account is a platform administrator. Those tools are not part of the web app in this version." /> :
        null}

        {profiles.length > 0 ?
        <section aria-labelledby="workspaces" className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <h2 id="workspaces" className="text-h3 text-ink">Your workspaces</h2>
            <ul className="mt-3 space-y-2">
              {profiles.map((p) =>
            <li key={p.id}>
                  <button
                onClick={() => open(p)}
                className="flex w-full items-center gap-3 rounded-xl border border-line px-4 py-3 text-left transition-[border-color,box-shadow] duration-150 ease-out hover:border-forest hover:shadow-card">

                    <span className="min-w-0 flex-1">
                      <span className="block text-small font-semibold text-ink">{roleLabel[p.role]}</span>
                      <span className="block truncate text-caption text-ink-soft">{p.detail}</span>
                    </span>
                    <ChevronRightIcon className="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true" />
                  </button>
                </li>
            )}
            </ul>
          </section> :

        <p className="text-small text-ink-soft">
            Your account isn’t connected to an institute, a class or a child yet. Institutes add their
            staff and teachers; parents link with an invitation code from their child’s institute.
          </p>
        }

        <section aria-labelledby="link-child" className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <div className="flex items-start gap-3">
            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-50 text-forest">
              <TicketIcon className="h-5 w-5" aria-hidden="true" />
            </span>
            <h2 id="link-child" className="pt-2 text-h3 text-ink">Link my child</h2>
          </div>
          <div className="mt-4 space-y-3">
            <FormAlert tone="success" message={linkedMessage} />
            <LinkChildForm
              onLinked={({ studentName }) => {
                setLinkedMessage(`You are now linked to ${studentName}.`);
                void openNewProfile((p) => p.role === 'parent', '/parent/family');
              }} />

          </div>
        </section>

        <section aria-labelledby="register" className="rounded-2xl border border-line bg-white p-5 shadow-card">
          <div className="flex items-start gap-3">
            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-50 text-forest">
              <BuildingIcon className="h-5 w-5" aria-hidden="true" />
            </span>
            <div className="min-w-0 flex-1">
              <h2 id="register" className="text-h3 text-ink">Register an institute</h2>
              <p className="mt-1 text-small text-ink-soft">
                You become its owner and admin. You can then add teachers, register students and invite
                their parents.
              </p>
            </div>
          </div>
          <div className="mt-4 space-y-3">
            <FormAlert tone="success" message={notice} />
            {registering ?
            <InstituteForm
              submitLabel="Register institute"
              busyLabel="Registering…"
              onSubmit={register}
              onCancel={() => setRegistering(false)} /> :


            <Button variant="secondary" onClick={() => setRegistering(true)}>
                Register an institute
              </Button>
            }
          </div>
        </section>

        <TeacherProfileCard />

        <Button variant="secondary" onClick={() => void logout()}>
          Sign out
        </Button>
      </main>
      <SiteFooter />
    </div>);

}
