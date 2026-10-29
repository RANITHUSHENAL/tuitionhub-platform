import React, { useState } from 'react';
import { Navigate } from 'react-router-dom';
import { AuthLayout, useAuthParams } from '../../components/auth/AuthLayout';
import { Button } from '../../components/ui/Button';
import { Label, TextInput } from '../../components/ui/Field';
import { useAuth } from '../../contexts/AuthContext';
import { ApiError, authApi } from '../../lib/api';

/** Same bounds as the API (contract §4.5); the API's own rules are the final check. */
const NAME_MIN = 2;
const NAME_MAX = 100;

function nameErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) return 'Something went wrong. Please try again.';
  if (error.status === 422) return error.fieldError('name') ?? 'Check your name and try again.';
  if (error.status === 0) return error.message;
  if (error.status === 401 || error.status === 419) return 'Your session expired. Please sign in again.';
  if (error.status === 403) return 'This account is suspended. Please contact your institute.';
  return 'Something went wrong. Please try again.';
}

/**
 * First sign-in: the account has no name yet (contract §4.5). PATCH /api/v1/me sets it; the
 * response is the full session, so onboarding_required turns false straight away.
 */
export function Onboarding() {
  const { carry } = useAuthParams();
  const { account, status, establishSession } = useAuth();
  const [name, setName] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (status === 'loading') {
    return (
      <AuthLayout title="Welcome to TuitionHub">
        <p role="status" className="text-small text-ink-soft">Checking whether you’re signed in…</p>
      </AuthLayout>);

  }

  if (!account) return <Navigate to={`/auth${carry ? `?${carry}` : ''}`} replace />;
  // Done (or never needed): the sign-in page decides where this account goes next.
  if (!account.onboarding_required) return <Navigate to={`/auth${carry ? `?${carry}` : ''}`} replace />;

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const trimmed = name.trim().replace(/\s+/g, ' ');
    if (trimmed.length < NAME_MIN || trimmed.length > NAME_MAX) {
      setError(`Enter your name (${NAME_MIN}–${NAME_MAX} characters).`);
      return;
    }

    setError('');
    setBusy(true);
    try {
      establishSession(await authApi.updateName(trimmed));
    } catch (err) {
      setError(nameErrorMessage(err));
      setBusy(false);
    }
  };

  return (
    <AuthLayout
      title="What should we call you?"
      subtitle="Institutes and teachers you work with will see this name. You can change it later.">

      <form onSubmit={submit} className="space-y-4" noValidate>
        <div>
          <Label htmlFor="name">Your name</Label>
          <TextInput
            id="name"
            autoComplete="name"
            autoFocus
            maxLength={NAME_MAX + 20}
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Kasun Silva"
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? 'name-error' : 'name-hint'} />

          {error ?
          <p id="name-error" role="alert" className="mt-2 text-caption text-state-danger">{error}</p> :
          <p id="name-hint" className="mt-2 text-caption text-ink-faint">
              Letters in Sinhala, Tamil or English, with spaces, full stops, apostrophes and hyphens.
            </p>}
        </div>
        <Button type="submit" size="lg" className="w-full" disabled={busy}>
          {busy ? 'Saving…' : 'Continue'}
        </Button>
      </form>
    </AuthLayout>);

}
