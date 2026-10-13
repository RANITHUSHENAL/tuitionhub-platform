import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import type { AuthUser, Identity, TeachingWorkspace } from '../types';

const STORAGE_KEY = 'th-auth-session';
const WINDOW_MS = 72 * 60 * 60 * 1000;

export type GuardianState = 'not-required' | 'linked' | 'pending' | 'restricted';

interface Session {
  identity: Identity;
  activeProfileId: string;
}

interface AuthContextValue {
  /** The active role profile */
  user: AuthUser | null;
  /** The one identity behind the phone number */
  identity: Identity | null;
  /** Every role profile this identity holds */
  profiles: AuthUser[];
  activeWorkspace: TeachingWorkspace | null;
  /** Sign in with a single profile (new signups) or a full identity */
  signIn: (user: AuthUser, identity?: Identity) => void;
  switchProfile: (profileId: string, workspaceId?: string) => void;
  switchWorkspace: (workspaceId: string) => void;
  signOut: () => void;
  update: (patch: Partial<AuthUser>) => void;
  /**
   * Returns true when the user may continue. Otherwise sends a guest to sign-in,
   * carrying the page and intent so the original action resumes afterwards.
   */
  requireAuth: (action: string, returnTo: string) => boolean;
  guardianState: GuardianState;
}

const AuthContext = createContext<AuthContextValue | null>(null);

function load(): Session | null {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) as Session : null;
  } catch {
    return null;
  }
}

export function guardianStateOf(user: AuthUser | null, now = Date.now()): GuardianState {
  if (!user || user.role !== 'student' || !user.isMinor) return 'not-required';
  if (user.guardianLink === 'linked') return 'linked';
  if (user.guardianDeadline && now > user.guardianDeadline) return 'restricted';
  return 'pending';
}

function withWindow(p: AuthUser): AuthUser {
  return p.role === 'student' && p.isMinor && p.guardianLink === 'pending' && !p.guardianDeadline ?
  { ...p, guardianDeadline: Date.now() + WINDOW_MS - 21 * 60 * 60 * 1000 } :
  p;
}

export function AuthProvider({ children }: {children: React.ReactNode;}) {
  const navigate = useNavigate();
  const [session, setSession] = useState<Session | null>(load);

  useEffect(() => {
    if (session) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(session));else
    window.localStorage.removeItem(STORAGE_KEY);
  }, [session]);

  const signIn = useCallback((next: AuthUser, identity?: Identity) => {
    const base: Identity = identity ?? {
      id: `id-${next.id}`,
      name: next.name,
      initials: next.initials,
      phone: next.phone,
      profiles: [next]
    };
    const profiles = base.profiles.map((p) => p.id === next.id ? withWindow(next) : withWindow(p));
    setSession({ identity: { ...base, profiles }, activeProfileId: next.id });
  }, []);

  const patchProfile = useCallback((id: string, patch: Partial<AuthUser>) => {
    setSession((s) =>
    s ?
    {
      ...s,
      identity: {
        ...s.identity,
        profiles: s.identity.profiles.map((p) => p.id === id ? { ...p, ...patch } : p)
      }
    } :
    s
    );
  }, []);

  const switchProfile = useCallback(
    (profileId: string, workspaceId?: string) => {
      setSession((s) => s ? { ...s, activeProfileId: profileId } : s);
      if (workspaceId) patchProfile(profileId, { activeWorkspaceId: workspaceId });
    },
    [patchProfile]
  );

  const signOut = useCallback(() => setSession(null), []);

  const update = useCallback(
    (patch: Partial<AuthUser>) => {
      if (session) patchProfile(session.activeProfileId, patch);
    },
    [session, patchProfile]
  );

  const switchWorkspace = useCallback(
    (workspaceId: string) => update({ activeWorkspaceId: workspaceId }),
    [update]
  );

  const user = session?.identity.profiles.find((p) => p.id === session.activeProfileId) ?? null;

  const activeWorkspace = useMemo(() => {
    if (!user?.workspaces?.length) return null;
    const active = user.workspaces.filter((w) => w.status === 'active');
    return active.find((w) => w.id === user.activeWorkspaceId) ?? active[0] ?? null;
  }, [user]);

  const requireAuth = useCallback(
    (action: string, returnTo: string) => {
      if (user) return true;
      navigate(`/auth?next=${encodeURIComponent(returnTo)}&action=${action}`);
      return false;
    },
    [user, navigate]
  );

  const value = useMemo(
    () => ({
      user,
      identity: session?.identity ?? null,
      profiles: session?.identity.profiles ?? [],
      activeWorkspace,
      signIn,
      switchProfile,
      switchWorkspace,
      signOut,
      update,
      requireAuth,
      guardianState: guardianStateOf(user)
    }),
    [user, session, activeWorkspace, signIn, switchProfile, switchWorkspace, signOut, update, requireAuth]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}

export const GUARDIAN_WINDOW_MS = WINDOW_MS;