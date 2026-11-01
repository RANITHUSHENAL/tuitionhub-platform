import { Navigate } from 'react-router-dom';
import { RequireAuth } from './RequireAuth';
import { useAuth } from '../../contexts/AuthContext';
import { roleHome } from '../../data/accounts';

function ToRoleHome() {
  const { user } = useAuth();
  // RequireAuth only renders this once a role profile exists; "/" is a safe fallback.
  return <Navigate to={user ? roleHome[user.role] : '/'} replace />;
}

/**
 * Retired screens (Exams/Marks, proposed F2c choice awaiting founder confirmation) send the
 * signed-in user to their own role home. RequireAuth keeps the usual sign-in, onboarding and
 * no-role redirects, and every role home is a different path, so this cannot loop.
 */
export function RoleHomeRedirect() {
  return (
    <RequireAuth>
      <ToRoleHome />
    </RequireAuth>);

}
