import React, { useState } from 'react';
import { Button } from '../ui/Button';
import { Label, TextInput } from '../ui/Field';
import { FieldError, FormAlert } from '../ui/FormMessage';
import { useApiErrors } from '../../hooks/useApiErrors';
import { ApiError, guardianApi } from '../../lib/api';
import { CHILD_LINK_NOTE } from '../../utils/parentSetup';

/** The contract's single generic message for every unusable code (§9.2). */
const GENERIC_CODE_ERROR = 'The invitation code is invalid or has expired.';

/** Upper-case letters and digits only, shown as XXXX-XXXX while typing. */
function formatInviteCode(raw: string): string {
  const chars = raw.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
  return chars.length > 4 ? `${chars.slice(0, 4)}-${chars.slice(4)}` : chars;
}

export interface LinkedResult {
  studentName: string;
}

/**
 * "Link my child" (contract §9.2). The code is held only in this input while typing, is sent in
 * the POST body (never a URL), and is cleared as soon as the request finishes. Every unusable
 * code — unknown, used, expired, revoked, or for another phone — gets the same generic message.
 */
export function LinkChildForm({ onLinked }: {onLinked: (result: LinkedResult) => void;}) {
  const describe = useApiErrors();
  const [code, setCode] = useState('');
  const [fieldError, setFieldError] = useState<string | undefined>();
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setMessage(null);
    if (code.replace('-', '').length !== 8) {
      setFieldError('Enter the 8-character code from your child’s institute (XXXX-XXXX).');
      return;
    }

    setFieldError(undefined);
    setBusy(true);
    try {
      const linked = await guardianApi.accept(code);
      setCode('');
      onLinked({ studentName: linked.student.full_name });
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setFieldError(GENERIC_CODE_ERROR);
      } else {
        setMessage(describe(error, { conflicts: { link_already_active: 'You are already linked to this child.' } }));
      }
      setCode('');
    } finally {
      setBusy(false);
    }
  };

  return (
    <form onSubmit={submit} className="space-y-3" noValidate autoComplete="off">
      <p className="text-small text-ink-soft">{CHILD_LINK_NOTE}</p>
      <div>
        <Label htmlFor="invite-code">Invitation code</Label>
        <TextInput
          id="invite-code"
          value={code}
          onChange={(e) => setCode(formatInviteCode(e.target.value))}
          placeholder="XXXX-XXXX"
          autoComplete="one-time-code"
          autoCapitalize="characters"
          spellCheck={false}
          maxLength={9}
          className="max-w-xs font-mono tracking-widest"
          aria-invalid={fieldError ? true : undefined}
          aria-describedby={fieldError ? 'invite-code-error' : 'invite-code-hint'} />

        {fieldError ?
        <FieldError id="invite-code-error" message={fieldError} /> :
        <p id="invite-code-hint" className="mt-1.5 text-caption text-ink-faint">
            The code works once, for the mobile number your institute invited.
          </p>}
      </div>
      <FormAlert message={message} />
      <Button type="submit" disabled={busy}>
        {busy ? 'Linking…' : 'Link my child'}
      </Button>
    </form>);

}
