import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import type { Role } from '../../types';

/**
 * Wraps signed-in areas only. Public pages (home, search, class detail,
 * institutes, teachers, Study discovery) are never wrapped.
 *
 * Waits for the first session check, sends an account without a name to onboarding, and an
 * account without any role profile to /account. This only shapes navigation: the API authorizes
 * every request itself.
 */
export function RequireAuth({
  roles,
  action = 'dashboard',
  children




}: {roles?: Role[];action?: string;children: React.ReactElement;}) {
  const { user, account, status } = useAuth();
  const location = useLocation();
  const next = encodeURIComponent(location.pathname + location.search);

  if (status === 'loading') {
    return (
      <p role="status" className="px-5 py-10 text-center text-small text-ink-soft">
        Checking whether you’re signed in…
      </p>);

  }

  if (account?.onboarding_required) {
    return <Navigate to={`/onboarding?next=${next}&action=${action}`} replace />;
  }

  if (account && !user) {
    return <Navigate to="/account" replace />;
  }

  if (!user || roles && !roles.includes(user.role)) {
    const roleHint = roles ? `&role=${roles[0]}` : '';
    return <Navigate to={`/auth?next=${next}&action=${action}${roleHint}`} replace />;
  }
  return children;
}
