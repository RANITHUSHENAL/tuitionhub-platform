import React from 'react';
import { AppShell } from '../app/AppShell';
import { teacherNavFor } from '../app/navConfig';
import { useAuth } from '../../contexts/AuthContext';

/** Teacher pages: teacher-only navigation, scoped to the active workspace. */
export function TeacherShell({
  title,
  subtitle,
  actions,
  children





}: {title: string;subtitle?: string;actions?: React.ReactNode;children: React.ReactNode;}) {
  const { user, activeWorkspace } = useAuth();
  return (
    <AppShell
      role="Teacher"
      accountName={user?.name ?? 'Nimal Perera'}
      accountDetail={activeWorkspace?.name ?? 'Teacher'}
      initials={user?.initials ?? 'NP'}
      nav={teacherNavFor(activeWorkspace)}
      context={activeWorkspace?.name}
      title={title}
      subtitle={subtitle}
      actions={actions}>
      
      {children}
    </AppShell>);

}