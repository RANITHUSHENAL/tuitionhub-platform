import { useCallback } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { ApiError } from '../lib/api';
import { apiErrorMessage, type ErrorOverrides } from '../lib/apiErrors';

/**
 * Turns an API error into a message for the screen. A `401` also re-reads the session, which clears
 * it locally; RequireAuth (or the account page) then sends the user to sign-in as usual.
 */
export function useApiErrors() {
  const { refreshSession } = useAuth();

  return useCallback(
    (error: unknown, overrides?: ErrorOverrides) => {
      if (error instanceof ApiError && error.status === 401) void refreshSession();
      return apiErrorMessage(error, overrides);
    },
    [refreshSession]
  );
}
