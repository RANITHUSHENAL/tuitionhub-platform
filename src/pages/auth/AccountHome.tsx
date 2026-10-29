import { Navigate, useNavigate } from 'react-router-dom';
import { UserIcon } from 'lucide-react';
import { SiteHeader } from '../../components/site/SiteHeader';
import { SiteFooter } from '../../components/site/SiteFooter';
import { Button } from '../../components/ui/Button';
import { EmptyState } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import { formatSriLankanMobile } from '../../lib/phone';

/**
 * Signed in, but nothing to open yet: no institute role, no teaching assignment and no linked child
 * (a new account, or a platform admin, whose tools are not in this app). Access is granted by
 * institutes and invitation codes, never chosen here (contract §3).
 */
export function AccountHome() {
  const { account, status, profiles, signOut } = useAuth();
  const navigate = useNavigate();

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
  // Has a role after all (e.g. an institute added them since): choose it on the sign-in page.
  if (profiles.length > 0) return <Navigate to="/auth" replace />;

  const isPlatformAdmin = account.roles.some((r) => r.role === 'super_admin');

  const logout = async () => {
    await signOut();
    navigate('/');
  };

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main className="mx-auto max-w-[720px] px-5 py-10 lg:px-8">
        <h1 className="text-h1 text-ink">Your account</h1>
        <p className="mt-2 text-body text-ink-soft">
          Signed in as {account.user.name} · {formatSriLankanMobile(account.user.phone)}
        </p>

        <div className="mt-8">
          <EmptyState
            icon={<UserIcon className="h-5 w-5" />}
            title={isPlatformAdmin ? 'Platform admin tools aren’t in this app yet' : 'Nothing to show yet'}
            body={
            isPlatformAdmin ?
            'Your account is a platform administrator. Those tools are not part of the web app in this version.' :
            'Your account is ready, but it isn’t connected to an institute, a class or a child yet. Institutes add their staff and teachers; parents are linked with an invitation code from their child’s institute.'
            }
            action={
            <Button variant="secondary" onClick={() => void logout()}>
                Sign out
              </Button>
            } />

        </div>
      </main>
      <SiteFooter />
    </div>);

}
