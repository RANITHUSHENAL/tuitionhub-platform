import type { AuthUser } from '../types';

export type SetupStatus = 'done' | 'todo' | 'waiting' | 'locked';

export interface SetupStep {
  id: 'account' | 'profile' | 'verification' | 'children';
  label: string;
  status: SetupStatus;
  statusLabel: string;
  to: string;
  action: string;
  why: string;
}

/**
 * Parent setup is four separate stages. Only the first happens at signup;
 * the rest are completed later, when a feature actually needs them.
 */
export function parentSetupSteps(user: AuthUser | null): SetupStep[] {
  const v = user?.verification ?? 'none';
  const verified = v === 'verified';
  const profileDone = !!user?.profileComplete || verified;
  const children = user?.childrenLinked ?? 0;

  return [
  {
    id: 'account',
    label: 'Parent account',
    status: 'done',
    statusLabel: 'Phone verified',
    to: '/parent/profile',
    action: '',
    why: 'Created with your mobile number.'
  },
  {
    id: 'profile',
    label: 'Parent profile',
    status: profileDone ? 'done' : 'todo',
    statusLabel: profileDone ? 'Complete' : 'Incomplete',
    to: '/parent/profile?next=/parent/verification',
    action: 'Complete parent profile',
    why: 'Your relationship to your children and where you are. About a minute.'
  },
  {
    id: 'verification',
    label: 'Guardian verification',
    status: verified ?
    'done' :
    v === 'in-progress' || v === 'review' ?
    'waiting' :
    profileDone ?
    'todo' :
    'locked',
    statusLabel: verified ?
    'Verified' :
    v === 'in-progress' ?
    'In review' :
    v === 'review' ?
    'Needs review' :
    v === 'retry' ?
    'Try again' :
    'Not verified',
    to: '/parent/verification?next=/parent/family',
    action: v === 'retry' ? 'Retake ID photo' : 'Verify as guardian',
    why: 'An ID check reviewed by a person. Needed before you can connect a child.'
  },
  {
    id: 'children',
    label: 'Children',
    status: children > 0 ? 'done' : verified ? 'todo' : 'locked',
    statusLabel: children > 0 ? `${children} connected` : 'No child connected',
    to: '/parent/family',
    action: 'Connect a child',
    why: 'See their attendance, marks and fees.'
  }];

}

export function nextSetupStep(user: AuthUser | null) {
  return parentSetupSteps(user).find((s) => s.status === 'todo') ?? null;
}

/** Where "Connect a child" should take the parent right now. */
export function connectChildPath(user: AuthUser | null) {
  const steps = parentSetupSteps(user);
  if (steps[1].status !== 'done') return '/parent/profile?next=/parent/verification';
  if (steps[2].status !== 'done') return '/parent/verification?next=/parent/family';
  return '/parent/family';
}

export function isParentSetupComplete(user: AuthUser | null) {
  return parentSetupSteps(user).every((s) => s.status === 'done');
}