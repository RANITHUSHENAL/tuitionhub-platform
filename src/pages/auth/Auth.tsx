import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  BuildingIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  GraduationCapIcon,
  PresentationIcon,
  UsersIcon } from
'lucide-react';
import { AuthLayout, useAuthParams } from '../../components/auth/AuthLayout';
import { PhoneOtp } from '../../components/auth/PhoneOtp';
import { useAuth } from '../../contexts/AuthContext';
import { demoIdentities, roleHome, roleLabel, roleSummary } from '../../data/accounts';
import type { AuthUser, Identity, Role } from '../../types';

type Stage = 'phone' | 'number' | 'role' | 'workspace';

const roleIcon: Record<Role, React.ReactNode> = {
  student: <GraduationCapIcon className="h-5 w-5" />,
  parent: <UsersIcon className="h-5 w-5" />,
  teacher: <PresentationIcon className="h-5 w-5" />,
  institute: <BuildingIcon className="h-5 w-5" />
};

/**
 * One phone number = one identity. After OTP we look up the roles it holds:
 * one role goes straight in; several show "Continue as"; a teacher in several
 * workspaces then picks a workspace.
 */
export function Auth() {
  const { next, carry, mode, role: roleHint } = useAuthParams();
  const navigate = useNavigate();
  const { signIn } = useAuth();
  const [tab, setTab] = useState<'signin' | 'create'>(mode === 'create' ? 'create' : 'signin');
  const [stage, setStage] = useState<Stage>('phone');
  const [identity, setIdentity] = useState<Identity | null>(null);
  const [profile, setProfile] = useState<AuthUser | null>(null);

  const enter = (id: Identity, p: AuthUser, workspaceId?: string) => {
    signIn(workspaceId ? { ...p, activeWorkspaceId: workspaceId } : p, id);
    // Only honour `next` if it belongs to the chosen role or is a public page.
    const home = roleHome[p.role];
    const target = next && (!next.startsWith('/') || !/^\/(student|parent|teacher|institute)/.test(next) || next.startsWith(home)) ? next : home;
    navigate(target, { replace: true });
  };

  const chooseIdentity = (id: Identity) => {
    setIdentity(id);
    if (id.profiles.length === 1) return chooseProfile(id, id.profiles[0]);
    setStage('role');
  };

  const chooseProfile = (id: Identity, p: AuthUser) => {
    const active = p.workspaces?.filter((w) => w.status === 'active') ?? [];
    if (p.role === 'teacher' && active.length > 1) {
      setProfile(p);
      setStage('workspace');
      return;
    }
    enter(id, p, active[0]?.id);
  };

  const identities = roleHint ?
  [...demoIdentities].sort(
    (a, b) =>
    Number(b.identity.profiles.some((p) => p.role === roleHint)) -
    Number(a.identity.profiles.some((p) => p.role === roleHint))
  ) :
  demoIdentities;

  const titles: Record<Stage, string> = {
    phone: 'Sign in',
    number: 'Sign in',
    role: 'Continue as',
    workspace: 'Choose workspace'
  };

  return (
    <AuthLayout
      title={tab === 'create' ? 'Create your account' : titles[stage]}
      subtitle={
      tab === 'create' ?
      'One account per phone number. You can add more roles to it later.' :
      stage === 'role' ?
      `${identity?.name}, this number has ${identity?.profiles.length} roles. You can switch any time from your account menu.` :
      stage === 'workspace' ?
      'You teach in more than one place. Each workspace only shows its own classes.' :
      'Use the mobile number linked to your TuitionHub account.'
      }>
      
      {stage === 'phone' || stage === 'number' || tab === 'create' ?
      <div role="tablist" aria-label="Sign in or create account" className="mb-6 grid grid-cols-2 rounded-xl border border-line bg-white p-1">
          {(['signin', 'create'] as const).map((t) =>
        <button
          key={t}
          role="tab"
          aria-selected={tab === t}
          onClick={() => setTab(t)}
          className={`rounded-lg py-2 text-small font-medium transition-colors duration-150 ease-out ${
          tab === t ? 'bg-forest text-white' : 'text-ink-soft hover:text-ink'}`
          }>
          
              {t === 'signin' ? 'Sign in' : 'Create account'}
            </button>
        )}
        </div> :
      null}

      {tab === 'signin' && stage === 'phone' ?
      <PhoneOtp onVerified={() => setStage('number')} cta="Sign in" /> :
      null}

      {tab === 'signin' && stage === 'number' ?
      <div>
          <p className="rounded-xl bg-surface-muted px-3.5 py-2.5 text-caption text-ink-soft">
            Prototype: pick which number you just verified. In the real product the number
            itself decides this.
          </p>
          <ul className="mt-3 space-y-2">
            {identities.map((d) =>
          <li key={d.identity.id}>
                <OptionButton
              onClick={() => chooseIdentity(d.identity)}
              title={d.label}
              body={d.description}
              meta={d.identity.phone} />
            
              </li>
          )}
          </ul>
        </div> :
      null}

      {tab === 'signin' && stage === 'role' && identity ?
      <div>
          <ul className="space-y-2">
            {identity.profiles.map((p) =>
          <li key={p.id}>
                <OptionButton
              icon={roleIcon[p.role]}
              onClick={() => chooseProfile(identity, p)}
              title={roleLabel[p.role]}
              body={roleSummary[p.role]}
              meta={p.detail} />
            
              </li>
          )}
          </ul>
          <BackLink onClick={() => setStage('number')} />
        </div> :
      null}

      {tab === 'signin' && stage === 'workspace' && identity && profile ?
      <div>
          <ul className="space-y-2">
            {profile.workspaces?.
          filter((w) => w.status === 'active').
          map((w) =>
          <li key={w.id}>
                  <OptionButton
              icon={w.kind === 'independent' ? <PresentationIcon className="h-5 w-5" /> : <BuildingIcon className="h-5 w-5" />}
              onClick={() => enter(identity, profile, w.id)}
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
          <BackLink onClick={() => setStage(identity.profiles.length > 1 ? 'role' : 'number')} />
        </div> :
      null}

      {tab === 'create' ?
      <ul className="space-y-3">
          <RoleOption
          to={`/auth/student?${carry}`}
          icon={<GraduationCapIcon className="h-5 w-5" />}
          title="I'm a student"
          body="Create your own account now. If you're under 18, connect a parent within 72 hours." />
        
          <RoleOption
          to={`/auth/parent?${carry}`}
          icon={<UsersIcon className="h-5 w-5" />}
          title="I'm a parent or guardian"
          body="Verify as a guardian, add your children, and see attendance, marks and fees." />
        
          <RoleOption
          to={`/auth/teacher?${carry}`}
          icon={<PresentationIcon className="h-5 w-5" />}
          title="I'm a teacher"
          body="Run your own classes, or link to the institute you teach at. No institute needed." />
        
          <li className="flex items-start gap-4 rounded-2xl border border-dashed border-line-strong bg-surface-muted p-5">
            <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-forest">
              <BuildingIcon className="h-5 w-5" />
            </span>
            <span>
              <span className="block text-h3 text-ink">I run an institute</span>
              <span className="mt-1 block text-small text-ink-soft">
                Institutes are onboarded with us in person — we set up your classes, team
                and settlement account together.
              </span>
              <Link to="/#institutes" className="mt-2 inline-block text-small font-medium text-forest underline underline-offset-4">
                What institutes get
              </Link>
            </span>
          </li>
          <li className="px-1 text-caption text-ink-faint">
            Already on TuitionHub in another role? Sign in with the same number — you can add
            a role from your account instead of creating a second account.
          </li>
        </ul> :
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

function RoleOption({
  to,
  icon,
  title,
  body





}: {to: string;icon: React.ReactNode;title: string;body: string;}) {
  return (
    <li>
      <Link
        to={to}
        className="group flex items-start gap-4 rounded-2xl border border-line bg-white p-5 transition-[border-color,box-shadow] duration-150 ease-out hover:border-forest hover:shadow-card">
        
        <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-50 text-forest">
          {icon}
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-h3 text-ink">{title}</span>
          <span className="mt-1 block text-small text-ink-soft">{body}</span>
        </span>
        <ChevronRightIcon className="mt-1 h-4 w-4 text-ink-faint" />
      </Link>
    </li>);

}