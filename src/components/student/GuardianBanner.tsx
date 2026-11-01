import React from 'react';
import { InfoIcon } from 'lucide-react';
import { useAuth } from '../../contexts/AuthContext';

/**
 * Calm note for a minor whose parent has not linked yet. No countdown and no
 * lock-out (C6): the institute registers students; parents link by invitation code.
 */
export function GuardianBanner() {
  const { guardianState } = useAuth();

  if (guardianState !== 'pending') return null;

  return (
    <div
      role="note"
      className="flex flex-wrap items-center gap-4 rounded-2xl border border-line bg-white p-5 shadow-card">

      <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-surface-sunk text-ink-soft">
        <InfoIcon className="h-5 w-5" aria-hidden="true" />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-small font-medium text-ink">No parent or guardian linked yet</p>
        <p className="text-small text-ink-soft">
          Your institute registers students; a parent links using an invitation code.
        </p>
      </div>
    </div>);

}
