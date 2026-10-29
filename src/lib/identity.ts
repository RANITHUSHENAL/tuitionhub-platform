import type { AccountSession } from './api';
import { formatSriLankanMobile } from './phone';
import { roleHome } from '../data/accounts';
import type { AuthUser, Identity } from '../types';

function initialsOf(name: string): string {
  const parts = name.split(' ').filter(Boolean);
  const letters = parts.length > 1 ? [parts[0], parts[parts.length - 1]] : parts;

  return letters.map((p) => Array.from(p)[0] ?? '').join('').toUpperCase() || '?';
}

/**
 * Maps the API session onto the prototype's role profiles (contract §3, §4.4):
 * - each stored institute role (institute_admin / institute_staff) → an `institute` profile;
 * - active teaching assignments → one `teacher` profile with a workspace per institute;
 * - active guardian links → a `parent` profile.
 * super_admin has no screen in this app, and a new account may hold no profile at all.
 * These are display hints only: the API re-checks access on every request.
 */
export function identityFromAccount(account: AccountSession): Identity {
  const name = account.user.name ?? '';
  const initials = initialsOf(name);
  const phone = formatSriLankanMobile(account.user.phone);
  const base = { name, initials, phone, phoneVerified: true };
  const profiles: AuthUser[] = [];

  for (const role of account.roles) {
    if (role.role === 'super_admin' || role.institute_uuid === null) continue;

    profiles.push({
      ...base,
      id: `institute:${role.institute_uuid}:${role.role}`,
      role: 'institute',
      detail: `${role.institute_name ?? 'Institute'} · ${role.role === 'institute_admin' ? 'Admin' : 'Staff'}`
    });
  }

  if (account.teaching_at.length > 0) {
    profiles.push({
      ...base,
      id: 'teacher',
      role: 'teacher',
      detail: account.teaching_at.map((t) => t.institute_name).join(', '),
      workspaces: account.teaching_at.map((t) => ({
        id: t.institute_uuid,
        kind: 'institute',
        name: t.institute_name,
        detail: 'Institute teacher',
        status: 'active'
      }))
    });
  }

  if (account.active_child_link_count > 0) {
    const count = account.active_child_link_count;

    profiles.push({
      ...base,
      id: 'parent',
      role: 'parent',
      detail: `${count} ${count === 1 ? 'child' : 'children'} linked`,
      childrenLinked: count,
      // Guardian links are created only through an institute invitation code (contract §9).
      verification: 'verified',
      profileComplete: true
    });
  }

  return { id: account.user.uuid, name, initials, phone, profiles };
}

/**
 * Where a profile lands after sign-in. `next` is honoured only if it is a public page or belongs
 * to the chosen role's area.
 */
export function destinationFor(profile: AuthUser, next: string | null): string {
  const home = roleHome[profile.role];

  if (!next || !next.startsWith('/')) return home;
  if (!/^\/(student|parent|teacher|institute)/.test(next) || next.startsWith(home)) return next;

  return home;
}
