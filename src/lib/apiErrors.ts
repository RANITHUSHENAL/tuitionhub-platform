import { ApiError } from './api';

/**
 * User-facing text for API errors (contract §1.3, §1.4). Messages never echo identifiers or say
 * whether a hidden resource exists: every 404 is the same "Not found".
 */

/** Approved `409` reason codes used by the F3 screens. Screens may override for their context. */
const conflictMessages: Record<string, string> = {
  already_active: 'This teacher is already actively assigned to this institute.',
  invitation_pending:
  'There is already a pending invitation for this student and phone number. Revoke it, or wait until it expires, before sending another.',
  invitation_not_pending: 'This invitation is no longer pending (it was accepted, revoked or has expired), so it can’t be revoked.',
  link_already_active: 'You are already linked to this child.'
};

export interface ErrorOverrides {
  /** Replaces the message for specific `409` reasons. */
  conflicts?: Record<string, string>;
  /** Message for a `422` when the screen shows the field errors itself. */
  validation?: string;
}

export function apiErrorMessage(error: unknown, overrides: ErrorOverrides = {}): string {
  if (!(error instanceof ApiError)) return 'Something went wrong. Please try again.';

  switch (error.status) {
    case 0:
      return error.message;
    case 401:
      return 'Your session has ended. Please sign in again.';
    case 403:
      if (error.reason === 'institute_suspended') {
        return 'This institute is suspended. Its records can still be viewed, but changes are not allowed.';
      }
      if (error.reason === 'not_eligible') {
        return 'Your account can’t do this yet. Make sure your name is set, then try again.';
      }
      if (error.message === 'This account is suspended.') {
        return 'This account is suspended. Please contact TuitionHub support.';
      }
      return 'You don’t have permission to do this.';
    case 404:
      return 'Not found.';
    case 409:
      return (
        (error.reason && (overrides.conflicts?.[error.reason] ?? conflictMessages[error.reason])) ??
        'This conflicts with the current state. Refresh and try again.');
    case 419:
      return 'Your session expired. Please try again.';
    case 422:
      return overrides.validation ?? 'Please check the highlighted fields.';
    case 429:
      return error.retryAfter ?
      `Too many attempts. Please try again in ${waitText(error.retryAfter)}.` :
      'Too many attempts. Please wait a few minutes and try again.';
    case 503:
      return 'TuitionHub is temporarily unavailable. Please try again in a moment.';
    default:
      return 'Something went wrong. Please try again.';
  }
}

/** First message per field from a `422`; empty for anything else. */
export function fieldErrorsOf(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.status !== 422) return {};

  return Object.fromEntries(
    Object.entries(error.fieldErrors).
    filter(([, messages]) => Array.isArray(messages) && messages.length > 0).
    map(([field, messages]) => [field, messages[0]])
  );
}

function waitText(seconds: number): string {
  if (seconds < 60) return `${seconds} ${seconds === 1 ? 'second' : 'seconds'}`;
  const minutes = Math.ceil(seconds / 60);
  return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'}`;
}
