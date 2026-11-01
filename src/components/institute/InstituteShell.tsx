import React from 'react';
import { BuildingIcon } from 'lucide-react';
import { AppShell } from '../app/AppShell';
import { instituteNav } from '../app/navConfig';
import { EmptyState } from '../ui/States';
import { LinkButton } from '../ui/Button';
import { useAuth } from '../../contexts/AuthContext';
import { initialsOf } from '../../utils/format';

/**
 * Shell for the API-backed institute screens. The institute comes from the active profile, which
 * the session reports (contract §4.4); the API still authorizes every request for it.
 */
export function InstituteShell({
  title,
  subtitle,
  actions,
  children





}: {title: string;subtitle?: string;actions?: React.ReactNode;children: React.ReactNode;}) {
  const { user } = useAuth();
  const membership = user?.institute;

  return (
    <AppShell
      role="Institute"
      accountName={membership?.name ?? 'Institute'}
      accountDetail={membership ? membership.role === 'institute_admin' ? 'Admin' : 'Staff' : ''}
      initials={initialsOf(membership?.name ?? 'Institute') || 'IN'}
      nav={instituteNav}
      title={title}
      subtitle={subtitle}
      actions={membership ? actions : undefined}
      sampleData={false}>

      {membership ?
      children :
      <EmptyState
        icon={<BuildingIcon className="h-5 w-5" />}
        title="No institute selected"
        body="Open an institute from your account to manage its teachers and students."
        action={<LinkButton to="/account">Your account</LinkButton>} />
      }
    </AppShell>);

}
