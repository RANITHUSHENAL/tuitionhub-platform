import React, { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AnimatePresence, motion } from 'framer-motion';
import { BuildingIcon, CheckIcon, ChevronDownIcon, LogOutIcon, SettingsIcon, UserIcon } from 'lucide-react';
import { Avatar } from '../ui/Avatar';
import { useAuth } from '../../contexts/AuthContext';
import { roleHome, roleLabel } from '../../data/accounts';
import type { Role } from '../../types';

const settingsPath: Record<Role, string> = {
  student: '/student/profile',
  parent: '/parent/profile',
  teacher: '/teacher/workspaces',
  institute: '/institute/teachers'
};

/**
 * Shows who you are signed in as, and in which role. Role switching only
 * appears when this identity actually holds more than one role.
 */
export function AccountMenu({ placement = 'down', compact = false }: {placement?: 'down' | 'up';compact?: boolean;}) {
  const { user, profiles, activeWorkspace, switchProfile, switchWorkspace, signOut } = useAuth();
  const navigate = useNavigate();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', onDown);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDown);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  if (!user) return null;

  const workspaces = user.workspaces?.filter((w) => w.status === 'active') ?? [];
  const context = user.role === 'teacher' && activeWorkspace ? activeWorkspace.name : roleLabel[user.role];

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-haspopup="menu"
        className={`flex w-full items-center gap-2.5 rounded-xl border border-line bg-white text-left transition-colors duration-150 ease-out hover:border-line-strong ${
        compact ? 'px-1.5 py-1.5' : 'px-2.5 py-2'}`
        }>
        
        <Avatar initials={user.initials} size="sm" />
        {!compact ?
        <span className="min-w-0 flex-1">
            <span className="block truncate text-small font-medium text-ink">{user.name}</span>
            <span className="block truncate text-caption text-ink-soft">
              {roleLabel[user.role]}
              {user.role === 'teacher' && activeWorkspace ? ` · ${activeWorkspace.name}` : ''}
            </span>
          </span> :
        null}
        <ChevronDownIcon
          className={`h-4 w-4 shrink-0 text-ink-faint transition-transform duration-150 ease-out ${open ? 'rotate-180' : ''}`}
          aria-hidden="true" />
        
      </button>

      <AnimatePresence>
        {open ?
        <motion.div
          role="menu"
          initial={{ opacity: 0, scale: 0.97, y: placement === 'down' ? -4 : 4 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.97 }}
          transition={{ duration: 0.16, ease: [0.23, 1, 0.32, 1] }}
          className={`absolute z-50 w-72 rounded-2xl border border-line bg-white p-2 shadow-lift ${
          placement === 'down' ? 'right-0 top-full mt-2 origin-top-right' : 'bottom-full left-0 mb-2 origin-bottom-left'}`
          }>
          
            <div className="px-3 py-2.5">
              <p className="text-caption text-ink-faint">Signed in as</p>
              <p className="truncate text-small font-medium text-ink">{user.name}</p>
              <p className="mt-1 inline-flex items-center gap-1.5 rounded-md bg-forest-50 px-2 py-0.5 text-caption font-medium text-forest">
                {context === roleLabel[user.role] ? context : `${roleLabel[user.role]} · ${context}`}
              </p>
            </div>

            {workspaces.length > 1 ?
          <MenuGroup label="Workspace">
                {workspaces.map((w) =>
            <MenuButton
              key={w.id}
              active={w.id === activeWorkspace?.id}
              icon={<BuildingIcon className="h-4 w-4" />}
              label={w.name}
              detail={w.kind === 'independent' ? 'Independent' : 'Institute'}
              onClick={() => {
                switchWorkspace(w.id);
                setOpen(false);
                navigate('/teacher');
              }} />

            )}
              </MenuGroup> :
          null}

            {profiles.length > 1 ?
          <MenuGroup label="Switch role">
                {profiles.map((p) =>
            <MenuButton
              key={p.id}
              active={p.id === user.id}
              icon={<UserIcon className="h-4 w-4" />}
              label={roleLabel[p.role]}
              detail={p.detail}
              onClick={() => {
                switchProfile(p.id);
                setOpen(false);
                navigate(roleHome[p.role]);
              }} />

            )}
              </MenuGroup> :
          null}

            <div className="mt-1 border-t border-line pt-1">
              <Link
              role="menuitem"
              to={settingsPath[user.role]}
              onClick={() => setOpen(false)}
              className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-small text-ink-soft transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">
              
                <SettingsIcon className="h-4 w-4" />
                Profile & settings
              </Link>
              <button
              role="menuitem"
              onClick={() => {
                signOut();
                navigate('/');
              }}
              className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-small text-ink-soft transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">
              
                <LogOutIcon className="h-4 w-4" />
                Sign out
              </button>
            </div>
          </motion.div> :
        null}
      </AnimatePresence>
    </div>);

}

function MenuGroup({ label, children }: {label: string;children: React.ReactNode;}) {
  return (
    <div className="mt-1 border-t border-line pt-2">
      <p className="px-3 pb-1 text-caption text-ink-faint">{label}</p>
      {children}
    </div>);

}

function MenuButton({
  active,
  icon,
  label,
  detail,
  onClick






}: {active: boolean;icon: React.ReactNode;label: string;detail: string;onClick: () => void;}) {
  return (
    <button
      role="menuitemradio"
      aria-checked={active}
      onClick={onClick}
      className={`flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left transition-colors duration-150 ease-out ${
      active ? 'bg-surface-muted' : 'hover:bg-surface-muted'}`
      }>
      
      <span className="text-ink-faint" aria-hidden="true">
        {icon}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block text-small font-medium text-ink">{label}</span>
        <span className="block truncate text-caption text-ink-soft">{detail}</span>
      </span>
      {active ? <CheckIcon className="h-4 w-4 text-forest" aria-hidden="true" /> : null}
    </button>);

}