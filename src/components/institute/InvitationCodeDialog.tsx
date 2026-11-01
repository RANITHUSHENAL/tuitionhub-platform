import { useEffect } from 'react';
import { KeyRoundIcon, XIcon } from 'lucide-react';
import { Button } from '../ui/Button';
import { CopyButton } from '../ui/CopyButton';
import { formatDateTime } from '../../utils/dates';

export interface IssuedInvitation {
  /** Plaintext one-time code from §9.1. Lives only in the parent's state while this dialog is open. */
  code: string;
  studentName: string;
  phoneMasked: string;
  expiresAt: string;
}

/**
 * Shows a new guardian invitation code once (contract §9.1). Deliberately not animated: when it
 * closes it unmounts at once, so the code does not linger in the DOM during an exit animation.
 * The caller clears its state in `onClose`; the code is never stored, logged or put in a URL.
 */
export function InvitationCodeDialog({ invitation, onClose }: {invitation: IssuedInvitation;onClose: () => void;}) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[55] flex items-end justify-center sm:items-center sm:p-6">
      <div className="absolute inset-0 bg-ink/30" onClick={onClose} aria-hidden="true" />
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="invite-code-title"
        className="relative z-10 w-full max-w-lg overflow-hidden rounded-t-3xl border border-line bg-white shadow-panel sm:rounded-2xl">

        <div className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
          <h2 id="invite-code-title" className="text-h3 text-ink">Invitation code</h2>
          <button
            onClick={onClose}
            aria-label="Close"
            className="rounded-lg p-1.5 text-ink-soft transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">

            <XIcon className="h-5 w-5" />
          </button>
        </div>
        <div className="space-y-4 px-5 py-5">
          <p className="text-small text-ink-soft">
            For a parent of <strong className="text-ink">{invitation.studentName}</strong> · {invitation.phoneMasked}
          </p>
          <div className="flex flex-col items-center rounded-2xl border border-line bg-surface-muted px-4 py-5 text-center">
            <KeyRoundIcon className="h-5 w-5 text-forest" aria-hidden="true" />
            <p className="mt-2 select-all font-mono text-display tracking-widest text-ink" aria-label={`Code ${invitation.code.split('').join(' ')}`}>
              {invitation.code}
            </p>
            <div className="mt-3">
              <CopyButton value={invitation.code} label="Copy code" />
            </div>
          </div>
          <p role="note" className="rounded-xl bg-state-warnBg px-3.5 py-3 text-small font-medium text-ink">
            This code is shown only once. Give it to the parent now; it can’t be shown again.
          </p>
          <p className="text-caption text-ink-soft">
            It works once, only for that mobile number, until {formatDateTime(invitation.expiresAt)}. The parent signs in
            with that number and enters it under <strong className="text-ink">Link my child</strong>.
          </p>
          {/* Focus moves into the dialog that just opened. */}
          <Button autoFocus className="w-full" onClick={onClose}>
            Done
          </Button>
        </div>
      </div>
    </div>);

}
