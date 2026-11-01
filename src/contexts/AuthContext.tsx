import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { authApi, type AccountSession } from '../lib/api';
import { identityFromAccount } from '../lib/identity';
import type { AuthUser, Identity, TeachingWorkspace } from '../types';

/** Only the chosen role and workspace are remembered in the browser; never the session itself. */
const PREFERENCE_KEY = 'th-active-profile';

/**
 * There is no countdown or lock-out (C6): the institute registers students and a
 * parent links using an invitation code, so a minor is either linked or pending.
 */
export type GuardianState = 'not-required' | 'linked' | 'pending';

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
  /** The signed-in account from the API (null when signed out); the source of truth for the session. */
  account: AccountSession | null;
  /** `loading` until the first GET /auth/session has answered. */
  status: 'loading' | 'ready';
  /** Stores a session the API has just confirmed (after OTP verify or PATCH /me). */
  establishSession: (account: AccountSession) => Identity;
  /** Re-reads GET /auth/session; returns null when signed out. */
  refreshSession: () => Promise<AccountSession | null>;
  /** Prototype signup flows only: an in-memory profile with no API session behind it. */
  signIn: (user: AuthUser, identity?: Identity) => void;
  switchProfile: (profileId: string, workspaceId?: string) => void;
  switchWorkspace: (workspaceId: string) => void;
  /** Ends the API session (POST /auth/logout), then clears local state. */
  signOut: () => Promise<void>;
  update: (patch: Partial<AuthUser>) => void;
  /**
   * Returns true when the user may continue. Otherwise sends a guest to sign-in,
   * carrying the page and intent so the original action resumes afterwards.
   */
  requireAuth: (action: string, returnTo: string) => boolean;
  guardianState: GuardianState;
}

const AuthContext = createContext<AuthContextValue | null>(null);

interface Preference {
  profileId: string;
  workspaceId?: string;
}

function loadPreference(): Preference | null {
  try {
    const raw = window.localStorage.getItem(PREFERENCE_KEY);
    return raw ? JSON.parse(raw) as Preference : null;
  } catch {
    return null;
  }
}

function savePreference(preference: Preference | null) {
  try {
    if (preference) window.localStorage.setItem(PREFERENCE_KEY, JSON.stringify(preference));else
    window.localStorage.removeItem(PREFERENCE_KEY);
  } catch {
    // Storage may be unavailable (private mode); the preference is only a convenience.
  }
}

export function guardianStateOf(user: AuthUser | null): GuardianState {
  if (!user || user.role !== 'student' || !user.isMinor) return 'not-required';
  if (user.guardianLink === 'linked') return 'linked';
  return 'pending';
}

function sessionFrom(identity: Identity, preference: Preference | null): Session {
  const preferred = identity.profiles.find((p) => p.id === preference?.profileId);
  const active = preferred ?? identity.profiles[0];

  const profiles = identity.profiles.map((p) =>
  p.id === active?.id && preference?.workspaceId && p.workspaces?.some((w) => w.id === preference.workspaceId) ?
  { ...p, activeWorkspaceId: preference.workspaceId } :
  p
  );

  return { identity: { ...identity, profiles }, activeProfileId: active?.id ?? '' };
}

export function AuthProvider({ children }: {children: React.ReactNode;}) {
  const navigate = useNavigate();
  const [session, setSession] = useState<Session | null>(null);
  const [account, setAccount] = useState<AccountSession | null>(null);
  const [status, setStatus] = useState<'loading' | 'ready'>('loading');

  const establishSession = useCallback((next: AccountSession) => {
    const identity = identityFromAccount(next);
    setAccount(next);
    setSession(sessionFrom(identity, loadPreference()));
    return identity;
  }, []);

  const clear = useCallback(() => {
    setAccount(null);
    setSession(null);
    savePreference(null);
  }, []);

  const refreshSession = useCallback(async () => {
    try {
      const next = await authApi.session();
      establishSession(next);
      return next;
    } catch {
      // 401 (signed out), 403 (suspended) or the API is unreachable: treat as signed out.
      setAccount(null);
      setSession(null);
      return null;
    } finally {
      setStatus('ready');
    }
  }, [establishSession]);

  useEffect(() => {
    void refreshSession();
  }, [refreshSession]);

  useEffect(() => {
    if (!account || !session) return;
    const active = session.identity.profiles.find((p) => p.id === session.activeProfileId);
    if (active) savePreference({ profileId: active.id, workspaceId: active.activeWorkspaceId });
  }, [account, session]);

  const signIn = useCallback((next: AuthUser, identity?: Identity) => {
    const base: Identity = identity ?? {
      id: `id-${next.id}`,
      name: next.name,
      initials: next.initials,
      phone: next.phone,
      profiles: [next]
    };
    const profiles = base.profiles.map((p) => p.id === next.id ? next : p);
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

  const signOut = useCallback(async () => {
    try {
      if (account) await authApi.logout();
    } catch {
      // Already signed out on the server (401) or unreachable: clear locally either way.
    } finally {
      clear();
    }
  }, [account, clear]);

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
      account,
      status,
      establishSession,
      refreshSession,
      signIn,
      switchProfile,
      switchWorkspace,
      signOut,
      update,
      requireAuth,
      guardianState: guardianStateOf(user)
    }),
    [user, session, activeWorkspace, account, status, establishSession, refreshSession, signIn, switchProfile, switchWorkspace, signOut, update, requireAuth]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside AuthProvider');
  return ctx;
}
