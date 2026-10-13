import React from 'react';
import { AppShell } from './AppShell';
import { studentNav } from './navConfig';
import { SiteHeader } from '../site/SiteHeader';
import { SiteFooter } from '../site/SiteFooter';
import { useAuth } from '../../contexts/AuthContext';

/**
 * Study discovery is public. Guests see it inside the public site chrome;
 * signed-in students see the same content inside their app shell.
 */
export function FlexibleShell({
  title,
  subtitle,
  actions,
  children





}: {title: string;subtitle?: string;actions?: React.ReactNode;children: React.ReactNode;}) {
  const { user } = useAuth();

  if (user?.role === 'student') {
    return (
      <AppShell
        role="Student"
        accountName={user.name}
        accountDetail={user.detail}
        initials={user.initials}
        nav={studentNav}
        title={title}
        subtitle={subtitle}
        actions={actions}>
        
        {children}
      </AppShell>);

  }

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <div className="mx-auto max-w-[1400px] px-5 lg:px-8">
        <div className="flex flex-wrap items-start justify-between gap-4 py-6">
          <div>
            <h1 className="text-h2 text-ink">{title}</h1>
            {subtitle ? <p className="mt-1 text-small text-ink-soft">{subtitle}</p> : null}
          </div>
          {actions}
        </div>
        <main className="pb-12">{children}</main>
      </div>
      <SiteFooter />
    </div>);

}