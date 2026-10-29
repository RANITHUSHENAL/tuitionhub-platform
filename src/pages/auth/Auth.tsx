import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BuildingIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  GraduationCapIcon,
  PresentationIcon,
  UsersIcon } from
'lucide-react';
import { AuthLayout, useAuthParams } from '../../components/auth/AuthLayout';
import { PhoneOtp, type CodeSent } from '../../components/auth/PhoneOtp';
import { useAuth } from '../../contexts/AuthContext';
import { roleLabel, roleSummary } from '../../data/accounts';
import { ApiError, authApi } from '../../lib/api';
import { destinationFor, identityFromAccount } from '../../lib/identity';
import type { AuthUser, Identity, Role } from '../../types';

type Stage = 'phone' | 'role' | 'workspace';

const roleIcon: Record<Role, React.ReactNode> = {
  student: <GraduationCapIcon className="h-5 w-5" />,
  parent: <UsersIcon className="h-5 w-5" />,
  teacher: <PresentationIcon className="h-5 w-5" />,
  institute: <BuildingIcon className="h-5 w-5" />
};

/** User-facing text for OTP errors (contract §4.1, §4.2). Never reveals whether a number is registered. */
function otpErrorMessage(error: unknown, step: 'request' | 'verify'): string {
  if (!(error instanceof ApiError)) return 'Something went wrong. Please try again.';

  switch (error.status) {
    case 0:
      return error.message;
    case 422:
      return step === 'request' ?
      error.fieldError('phone') ?? 'Enter a Sri Lankan mobile number, for example 77 123 4567.' :
      'That code is incorrect or has expired. Check it, or request a new code.';
    case 429:
      return step === 'request' ?
      'Please wait a little before requesting another code.' :
      'Too many attempts. Request a new code and try again in a few minutes.';
    case 403:
      return error.message === 'This account is suspended.' ?
      'This account is suspended. Please contact your institute.' :
      'Sign-in only works from the TuitionHub web app.';
    case 401:
      // Verified, but the session cookie did not stick (cookies blocked, or 127.0.0.1 vs localhost).
      return 'We couldn’t keep you signed in. Please allow cookies for this site and try again.';
    case 419:
      return 'Your session expired. Please try again.';
    case 503:
      return 'Sign-in codes are temporarily unavailable. Please try again later.';
    default:
      return 'Something went wrong. Please try again.';
  }
}

/**
 * One phone number = one account (contract §4). After the OTP is verified the API session decides
 * what happens next: a new account first chooses its name (/onboarding); an account with no
 * institute, class or child goes to /account; one role goes straight in; several show "Continue as";
 * a teacher at several institutes then picks a workspace.
 *
 * There is no separate sign-up: signing in with a new number creates the account (§4.2). The
 * prototype signup screens (StudentSignup, ParentSignup, TeacherSignup) accept any code, so they
 * are kept as files but are not reachable from the app.
 */
export function Auth() {
  const { next, carry, role: roleHint } = useAuthParams();
  const navigate = useNavigate();
  const { account, status, establishSession, switchProfile, signOut } = useAuth();
  const [stage, setStage] = useState<Stage>('phone');
  const [identity, setIdentity] = useState<Identity | null>(null);
  const [profile, setProfile] = useState<AuthUser | null>(null);
  const challengeId = useRef<string | null>(null);

  const enter = useCallback(
    (p: AuthUser, workspaceId?: string) => {
      switchProfile(p.id, workspaceId);
      navigate(destinationFor(p, next), { replace: true });
    },
    [switchProfile, navigate, next]
  );

  const chooseProfile = useCallback(
    (p: AuthUser) => {
      const active = p.workspaces?.filter((w) => w.status === 'active') ?? [];
      if (p.role === 'teacher' && active.length > 1) {
        setProfile(p);
        setStage('workspace');
        return;
      }
      enter(p, active[0]?.id);
    },
    [enter]
  );

  // Signed in (just now, or already when arriving here): route by what the API session says.
  useEffect(() => {
    if (status !== 'ready' || !account || stage !== 'phone') return;

    if (account.onboarding_required) {
      navigate(`/onboarding${carry ? `?${carry}` : ''}`, { replace: true });
      return;
    }

    const id = identityFromAccount(account);
    setIdentity(id);

    if (id.profiles.length === 0) {
      navigate('/account', { replace: true });
      return;
    }

    const hinted = id.profiles.filter((p) => p.role === roleHint);
    if (id.profiles.length === 1 || hinted.length === 1) {
      chooseProfile(hinted.length === 1 ? hinted[0] : id.profiles[0]);
      return;
    }

    setStage('role');
  }, [status, account, stage, carry, roleHint, navigate, chooseProfile]);

  const requestCode = async (phone: string): Promise<CodeSent> => {
    try {
      const sent = await authApi.requestOtp(phone);
      challengeId.current = sent.data.challenge_id;
      return { devMode: sent.meta.dev_mode, resendAvailableAt: sent.data.resend_available_at };
    } catch (error) {
      throw new Error(otpErrorMessage(error, 'request'));
    }
  };

  const verifyCode = async (phone: string, code: string) => {
    if (!challengeId.current) throw new Error('Request a code first.');

    try {
      await authApi.verifyOtp(challengeId.current, phone, code);
      // The session (roles, teaching, links) is read from the API, never assumed.
      establishSession(await authApi.session());
    } catch (error) {
      throw new Error(otpErrorMessage(error, 'verify'));
    }
  };

  const switchAccount = async () => {
    await signOut();
    setIdentity(null);
    setProfile(null);
    setStage('phone');
  };

  const titles: Record<Stage, string> = {
    phone: 'Sign in',
    role: 'Continue as',
    workspace: 'Choose workspace'
  };

  if (status === 'loading') {
    return (
      <AuthLayout title="Sign in">
        <p role="status" className="text-small text-ink-soft">Checking whether you’re signed in…</p>
      </AuthLayout>);

  }

  return (
    <AuthLayout
      title={titles[stage]}
      subtitle={
      stage === 'role' ?
      `${identity?.name}, this number has ${identity?.profiles.length} roles. You can switch any time from your account menu.` :
      stage === 'workspace' ?
      'You teach at more than one institute. Each workspace only shows its own classes.' :
      'Enter your mobile number to receive a one-time code.'
      }>

      {stage === 'phone' ?
      <>
          <p className="mb-6 rounded-xl border border-forest-100 bg-forest-50 px-4 py-3 text-small text-forest">
            Signing in with a new number creates an account.
          </p>
          <PhoneOtp
          cta="Sign in"
          live={{ requestCode, verifyCode }}
          // Routing happens in the effect above once the session is stored.
          onVerified={() => undefined} />

        </> :
      null}

      {stage === 'role' && identity ?
      <div>
          <ul className="space-y-2">
            {identity.profiles.map((p) =>
          <li key={p.id}>
                <OptionButton
              icon={roleIcon[p.role]}
              onClick={() => chooseProfile(p)}
              title={roleLabel[p.role]}
              body={roleSummary[p.role]}
              meta={p.detail} />

              </li>
          )}
          </ul>
          <SwitchAccount onClick={() => void switchAccount()} />
        </div> :
      null}

      {stage === 'workspace' && identity && profile ?
      <div>
          <ul className="space-y-2">
            {profile.workspaces?.
          filter((w) => w.status === 'active').
          map((w) =>
          <li key={w.id}>
                  <OptionButton
              icon={w.kind === 'independent' ? <PresentationIcon className="h-5 w-5" /> : <BuildingIcon className="h-5 w-5" />}
              onClick={() => enter(profile, w.id)}
              title={w.name}
              body={
              w.kind === 'independent' ?
              'Classes you run yourself — your students, fees and payments.' :
              'Classes the institute has assigned to you. The institute runs fees.'
              }
              meta={w.detail} />

                </li>
          )}
          </ul>
          {identity.profiles.length > 1 ?
        <BackLink onClick={() => setStage('role')} /> :
        <SwitchAccount onClick={() => void switchAccount()} />}
        </div> :
      null}

    </AuthLayout>);

}

function OptionButton({
  icon,
  title,
  body,
  meta,
  onClick






}: {icon?: React.ReactNode;title: string;body: string;meta?: string;onClick: () => void;}) {
  return (
    <button
      onClick={onClick}
      className="flex w-full items-start gap-3.5 rounded-2xl border border-line bg-white px-4 py-3.5 text-left transition-[border-color,box-shadow] duration-150 ease-out hover:border-forest hover:shadow-card">

      {icon ?
      <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-50 text-forest">
          {icon}
        </span> :
      null}
      <span className="min-w-0 flex-1">
        <span className="block text-small font-semibold text-ink">{title}</span>
        <span className="mt-0.5 block text-caption text-ink-soft">{body}</span>
        {meta ? <span className="mt-1 block truncate text-caption text-ink-faint">{meta}</span> : null}
      </span>
      <ChevronRightIcon className="mt-1 h-4 w-4 shrink-0 text-ink-faint" />
    </button>);

}

function BackLink({ onClick }: {onClick: () => void;}) {
  return (
    <button
      onClick={onClick}
      className="mt-4 inline-flex items-center gap-1 text-small text-ink-soft transition-colors duration-150 ease-out hover:text-ink">

      <ChevronLeftIcon className="h-4 w-4" />
      Back
    </button>);

}

function SwitchAccount({ onClick }: {onClick: () => void;}) {
  return (
    <button
      onClick={onClick}
      className="mt-4 text-small text-ink-soft underline underline-offset-4 transition-colors duration-150 ease-out hover:text-ink">

      Not you? Sign out and use another number
    </button>);

}
