import React from 'react';
import { Link } from 'react-router-dom';
import { ClockIcon, LockIcon } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';
import { useCountdown } from '../../hooks/useCountdown';

/** Friendly, non-threatening guardian-connection reminder with countdown. */
export function GuardianBanner() {
  const { user, guardianState } = useAuth();
  const { hours, minutes, seconds } = useCountdown(
    guardianState === 'pending' ? user?.guardianDeadline : undefined
  );

  if (guardianState !== 'pending' && guardianState !== 'restricted') return null;

  if (guardianState === 'restricted') {
    return (
      <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-white p-5 shadow-card">
        <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-sunk text-ink-soft">
          <LockIcon className="h-5 w-5" aria-hidden="true" />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-small font-medium text-ink">Pending guardian connection</p>
          <p className="text-small text-ink-soft">
            Your account and everything in it is saved. Enrolling, joining study sessions
            and messaging are paused until a parent or guardian connects.
          </p>
        </div>
        <Link
          to="/student/connect-guardian"
          className="inline-flex h-10 items-center rounded-xl bg-forest px-4 text-small font-medium text-white transition-colors duration-150 ease-out hover:bg-forest-600">
          
          Connect a parent
        </Link>
      </div>);

  }

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-2xl border border-state-warn/25 bg-state-warnBg/60 p-5">
      <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white text-state-warn">
        <ClockIcon className="h-5 w-5" aria-hidden="true" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-small font-medium text-ink">
          Connect a parent or guardian to keep your account active and unlock full
          TuitionHub features.
        </p>
        <p className="mt-0.5 text-caption text-ink-soft">
          Time remaining{' '}
          <span className="font-semibold tabular-nums text-ink">
            {String(hours).padStart(2, '0')}h {String(minutes).padStart(2, '0')}m{' '}
            {String(seconds).padStart(2, '0')}s
          </span>
        </p>
      </div>
      <Link
        to="/student/connect-guardian"
        className="inline-flex h-10 items-center rounded-xl bg-forest px-4 text-small font-medium text-white transition-colors duration-150 ease-out hover:bg-forest-600">
        
        Connect now
      </Link>
    </div>);

}