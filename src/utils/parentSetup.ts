import type { AuthUser } from '../types';

export type SetupStatus = 'done' | 'todo' | 'waiting' | 'locked';

export interface SetupStep {
  /** 'verification' is the retired guardian ID-check step, kept only so its hidden prototype screen compiles. */
  id: 'account' | 'profile' | 'verification' | 'children';
  label: string;
  status: SetupStatus;
  statusLabel: string;
  to: string;
  action: string;
  why: string;
}

/** C6 wording for every place a parent is told how a child gets linked. */
export const CHILD_LINK_NOTE = "Your child's institute will provide an invitation code to link your account.";

/**
 * Parent setup is three stages (C6): the account, the parent profile, then a child linked with
 * an invitation code from the child's institute. There is no ID document or face check, and the
 * parent never creates or claims a child profile.
 */
export function parentSetupSteps(user: AuthUser | null): SetupStep[] {
  const profileDone = !!user?.profileComplete || user?.verification === 'verified';
  const children = user?.childrenLinked ?? 0;

  return [
  {
    id: 'account',
    label: 'Parent account',
    status: 'done',
    statusLabel: 'Signed in',
    to: '/parent/profile',
    action: '',
    why: 'Created with your mobile number.'
  },
  {
    id: 'profile',
    label: 'Parent profile',
    status: profileDone ? 'done' : 'todo',
    statusLabel: profileDone ? 'Complete' : 'Incomplete',
    to: '/parent/profile',
    action: 'Complete parent profile',
    why: 'Your relationship to your children and where you are. About a minute.'
  },
  {
    id: 'children',
    label: 'Children',
    status: children > 0 ? 'done' : 'waiting',
    statusLabel: children > 0 ? `${children} linked` : 'No child linked yet',
    to: '/parent/family',
    action: '',
    why: CHILD_LINK_NOTE
  }];

}

export function nextSetupStep(user: AuthUser | null) {
  return parentSetupSteps(user).find((s) => s.status === 'todo') ?? null;
}

export function isParentSetupComplete(user: AuthUser | null) {
  return parentSetupSteps(user).every((s) => s.status === 'done');
}
