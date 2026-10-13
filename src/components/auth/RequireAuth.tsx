import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import type { Role } from '../../types';

/**
 * Wraps signed-in areas only. Public pages (home, search, class detail,
 * institutes, teachers, Study discovery) are never wrapped.
 */
export function RequireAuth({
  roles,
  action = 'dashboard',
  children




}: {roles?: Role[];action?: string;children: React.ReactElement;}) {
  const { user } = useAuth();
  const location = useLocation();
  const next = encodeURIComponent(location.pathname + location.search);

  if (!user || roles && !roles.includes(user.role)) {
    const roleHint = roles ? `&role=${roles[0]}` : '';
    return <Navigate to={`/auth?next=${next}&action=${action}${roleHint}`} replace />;
  }
  return children;
}