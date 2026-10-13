import React, { useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { BellIcon, ChevronDownIcon, ChevronLeftIcon } from 'lucide-react';
import { Avatar } from '../ui/Avatar';
import { AccountMenu } from './AccountMenu';
import { useAuth } from '../../contexts/AuthContext';

export interface ShellNavItem {
  to: string;
  label: string;
  icon: React.ReactNode;
  end?: boolean;
  /** Items with a group render under a collapsible secondary heading */
  group?: string;
}

export function AppShell({
  role,
  accountName,
  accountDetail,
  initials,
  nav,
  title,
  subtitle,
  actions,
  context,
  children












}: {role: string;accountName: string;accountDetail: string;initials: string;nav: ShellNavItem[];title: string;subtitle?: string;actions?: React.ReactNode; /** e.g. the teacher's current workspace, shown under the logo */context?: string;children: React.ReactNode;}) {
  const { user } = useAuth();
  const location = useLocation();
  const primary = nav.filter((n) => !n.group);
  const groups = Array.from(new Set(nav.filter((n) => n.group).map((n) => n.group as string)));
  const groupHasActive = (g: string) =>
  nav.some((n) => n.group === g && location.pathname.startsWith(n.to));
  const [openGroups, setOpenGroups] = useState<string[]>(() => groups.filter(groupHasActive));

  const signedInHere = user && user.role === role.toLowerCase();

  return (
    <div className="min-h-screen bg-canvas">
      <div className="mx-auto flex max-w-[1400px]">
        <aside className="sticky top-0 hidden h-screen w-64 shrink-0 flex-col border-r border-line bg-white px-4 py-5 lg:flex">
          <Link to="/" className="px-2 text-h3 font-bold tracking-tight text-ink">
            Tuition<span className="text-forest">Hub</span>
          </Link>
          <p className="mt-1 truncate px-2 text-caption text-ink-faint">
            <span className="uppercase tracking-wide">{role}</span>
            {context ? <span> · {context}</span> : null}
          </p>

          <nav aria-label={`${role} sections`} className="mt-6 flex-1 space-y-0.5 overflow-y-auto th-scroll">
            {primary.map((item) =>
            <SideLink key={item.to} item={item} />
            )}
            {groups.map((g) => {
              const open = openGroups.includes(g);
              return (
                <div key={g} className="pt-3">
                  <button
                    onClick={() =>
                    setOpenGroups((o) => o.includes(g) ? o.filter((x) => x !== g) : [...o, g])
                    }
                    aria-expanded={open}
                    className="flex w-full items-center justify-between rounded-lg px-3 py-1.5 text-caption font-medium uppercase tracking-wide text-ink-faint transition-colors duration-150 ease-out hover:text-ink-soft">
                    
                    {g}
                    <ChevronDownIcon
                      className={`h-3.5 w-3.5 transition-transform duration-150 ease-out ${open ? 'rotate-180' : ''}`}
                      aria-hidden="true" />
                    
                  </button>
                  {open ?
                  <div className="mt-0.5 space-y-0.5">
                      {nav.
                    filter((n) => n.group === g).
                    map((item) =>
                    <SideLink key={item.to} item={item} />
                    )}
                    </div> :
                  null}
                </div>);

            })}
          </nav>

          <div className="mt-6">
            {signedInHere ?
            <AccountMenu placement="up" /> :

            <div className="flex items-center gap-3 rounded-xl border border-line px-3 py-3">
                <Avatar initials={initials} size="sm" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-small font-medium text-ink">{accountName}</p>
                  <p className="truncate text-caption text-ink-soft">{accountDetail}</p>
                </div>
              </div>
            }
          </div>
          <Link
            to="/search"
            className="mt-3 inline-flex items-center gap-1.5 px-2 text-caption text-ink-faint transition-colors duration-150 ease-out hover:text-ink">
            
            <ChevronLeftIcon className="h-3.5 w-3.5" />
            Browse classes
          </Link>
        </aside>

        <div className="min-w-0 flex-1">
          <header className="sticky top-0 z-20 border-b border-line bg-canvas/92 px-5 py-4 backdrop-blur lg:px-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <p className="text-caption uppercase tracking-wide text-ink-faint lg:hidden">
                  {role}
                  {context ? ` · ${context}` : ''}
                </p>
                <h1 className="text-h2 text-ink">{title}</h1>
                {subtitle ? <p className="mt-1 text-small text-ink-soft">{subtitle}</p> : null}
              </div>
              <div className="flex items-center gap-2">
                {actions}
                <button
                  aria-label="Notifications"
                  className="relative rounded-xl border border-line bg-white p-2.5 text-ink-soft transition-colors duration-150 ease-out hover:text-ink">
                  
                  <BellIcon className="h-4.5 w-4.5" />
                  <span className="absolute right-2 top-2 h-1.5 w-1.5 rounded-full bg-clay" />
                </button>
                {signedInHere ?
                <div className="lg:hidden">
                    <AccountMenu compact />
                  </div> :
                null}
              </div>
            </div>
          </header>

          <nav
            aria-label={`${role} sections`}
            className="flex gap-1 overflow-x-auto border-b border-line bg-white px-4 py-2 lg:hidden">
            
            {nav.map((item) =>
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
              `whitespace-nowrap rounded-lg px-3 py-2 text-small font-medium transition-colors duration-150 ease-out ${
              isActive ? 'bg-forest-50 text-forest' : 'text-ink-soft'}`

              }>
              
                {item.label}
              </NavLink>
            )}
          </nav>

          <main className="px-5 py-6 lg:px-8 lg:py-8">{children}</main>
        </div>
      </div>
    </div>);

}

function SideLink({ item }: {item: ShellNavItem;}) {
  return (
    <NavLink
      to={item.to}
      end={item.end}
      className={({ isActive }) =>
      `flex items-center gap-2.5 rounded-xl px-3 py-2.5 text-small font-medium transition-colors duration-150 ease-out ${
      isActive ? 'bg-forest-50 text-forest' : 'text-ink-soft hover:bg-surface-muted hover:text-ink'}`

      }>
      
      <span aria-hidden="true">{item.icon}</span>
      {item.label}
    </NavLink>);

}