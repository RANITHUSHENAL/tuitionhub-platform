import React from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { UserRoundIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { VerificationFlow } from '../../components/auth/VerificationFlow';
import { ParentStages } from '../../components/parent/ParentStages';
import { LinkButton } from '../../components/ui/Button';
import { useAuth } from '../../contexts/AuthContext';
import { parentSetupSteps } from '../../utils/parentSetup';

/** Stage 3 — guardian verification, only after the parent profile exists. */
export function GuardianVerification() {
  const { user, update } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const next = params.get('next') ?? '/parent/family';
  const steps = parentSetupSteps(user);
  const profileDone = steps[1].status === 'done';

  return (
    <AppShell
      role="Parent"
      accountName={user?.name ?? 'Parent'}
      accountDetail={user?.detail ?? 'Parent account'}
      initials={user?.initials ?? 'PA'}
      nav={parentNav}
      title="Guardian verification"
      subtitle="Confirms you are a real guardian before you can connect a child or see their records">
      
      <div className="max-w-5xl space-y-6">
        <ParentStages steps={steps} current="verification" />

        <div className="grid gap-6 lg:grid-cols-[1.1fr_0.9fr]">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            {profileDone ?
            <VerificationFlow
              initial={user?.verification ?? 'none'}
              onChange={(s) => update({ verification: s })}
              onDone={() => navigate(next)} /> :


            <div className="flex flex-col items-start gap-4">
                <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-forest-50 text-forest">
                  <UserRoundIcon className="h-5 w-5" aria-hidden="true" />
                </span>
                <div>
                  <p className="text-h3 text-ink">First, your parent profile</p>
                  <p className="mt-1 text-small text-ink-soft">
                    Add your relationship and area — about a minute. Then you’ll come straight back
                    here to verify.
                  </p>
                </div>
                <LinkButton to="/parent/profile?next=/parent/verification">Complete parent profile</LinkButton>
              </div>
            }
          </section>
          <aside className="space-y-4">
            <section className="rounded-2xl border border-line bg-surface-muted p-6">
              <h2 className="text-h3 text-ink">How decisions are made</h2>
              <ul className="mt-3 space-y-2 text-small text-ink-soft">
                <li>Document reading extracts the name and ID number so you do not type them.</li>
                <li>A face check confirms the person holding the phone matches the document photo.</li>
                <li>A trained TuitionHub reviewer confirms every guardian verification. Software never approves or rejects on its own.</li>
                <li>Unclear cases go to manual review; we may call you on your registered number.</li>
              </ul>
            </section>
            <section className="rounded-2xl border border-line bg-white p-6">
              <h2 className="text-h3 text-ink">What we keep</h2>
              <p className="mt-2 text-small text-ink-soft">
                The verified result and your ID number (masked). Document images are deleted
                after review. Institutes only see that you are a verified guardian — never your
                document.
              </p>
            </section>
          </aside>
        </div>
      </div>
    </AppShell>);

}