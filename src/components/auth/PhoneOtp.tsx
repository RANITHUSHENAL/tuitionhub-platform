import React, { useEffect, useRef, useState } from 'react';
import { InfoIcon } from 'lucide-react';
import { Button } from '../ui/Button';
import { Label } from '../ui/Field';
import { formatSriLankanMobile, toSriLankanMobile } from '../../lib/phone';

/** What the live sign-in flow learns when a code is sent (contract §4.1). */
export interface CodeSent {
  /** True only for the local `log` OTP driver: no SMS is sent. */
  devMode: boolean;
  /** When the server allows the next resend (ISO 8601 UTC). */
  resendAvailableAt: string;
}

/**
 * Live mode: both handlers call the API. Without them the component keeps its prototype
 * behaviour (any six digits pass), which the not-yet-wired signup pages still use.
 */
interface LiveHandlers {
  requestCode: (phone: string) => Promise<CodeSent>;
  /** Resolves when the code is accepted; rejects with a message to show. */
  verifyCode: (phone: string, code: string) => Promise<void>;
}

const PROTOTYPE_RESEND_SECONDS = 30;

function secondsUntil(iso: string): number {
  return Math.max(0, Math.ceil((Date.parse(iso) - Date.now()) / 1000));
}

function messageOf(error: unknown): string {
  return error instanceof Error ? error.message : 'Something went wrong. Please try again.';
}

/**
 * Shared phone → one-time-code step used by sign-in and every signup. `onVerified` receives the
 * number for display, e.g. `+94 77 123 4567`.
 */
export function PhoneOtp({
  onVerified,
  cta = 'Continue',
  live




}: {onVerified: (phone: string) => void;cta?: string;live?: LiveHandlers;}) {
  const [phone, setPhone] = useState('');
  const [e164, setE164] = useState('');
  const [stage, setStage] = useState<'phone' | 'code'>('phone');
  const [code, setCode] = useState(['', '', '', '', '', '']);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const [devMode, setDevMode] = useState(false);
  const [resendIn, setResendIn] = useState(PROTOTYPE_RESEND_SECONDS);
  const refs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (stage !== 'code' || resendIn === 0) return;
    const id = window.setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => window.clearTimeout(id);
  }, [stage, resendIn]);

  const send = async (number: string) => {
    if (!live) {
      setResendIn(PROTOTYPE_RESEND_SECONDS);
      return true;
    }

    setBusy(true);
    try {
      const sent = await live.requestCode(number);
      setDevMode(sent.devMode);
      setResendIn(secondsUntil(sent.resendAvailableAt));
      return true;
    } catch (e) {
      setError(messageOf(e));
      return false;
    } finally {
      setBusy(false);
    }
  };

  const sendCode = async (e: React.FormEvent) => {
    e.preventDefault();
    const number = toSriLankanMobile(phone);
    if (!number) {
      setError('Enter a Sri Lankan mobile number, for example 77 123 4567.');
      return;
    }
    setError('');
    if (!(await send(number))) return;
    setE164(number);
    setCode(['', '', '', '', '', '']);
    setStage('code');
    window.setTimeout(() => refs.current[0]?.focus(), 50);
  };

  const resend = async () => {
    setError('');
    if (await send(e164)) {
      setCode(['', '', '', '', '', '']);
      refs.current[0]?.focus();
    }
  };

  const setDigit = (i: number, v: string) => {
    const d = v.replace(/\D/g, '').slice(-1);
    const nextCode = [...code];
    nextCode[i] = d;
    setCode(nextCode);
    if (d && i < 5) refs.current[i + 1]?.focus();
  };

  const verify = async (e: React.FormEvent) => {
    e.preventDefault();
    const entered = code.join('');
    if (entered.length !== 6) {
      setError('Enter all six digits.');
      return;
    }

    if (!live) {
      if (entered === '000000') {
        setError('That code did not match. Check the latest SMS and try again.');
        return;
      }
      setError('');
      onVerified(formatSriLankanMobile(e164));
      return;
    }

    setError('');
    setBusy(true);
    try {
      await live.verifyCode(e164, entered);
      onVerified(formatSriLankanMobile(e164));
    } catch (err) {
      setError(messageOf(err));
      setCode(['', '', '', '', '', '']);
      refs.current[0]?.focus();
    } finally {
      setBusy(false);
    }
  };

  if (stage === 'phone') {
    return (
      <form onSubmit={sendCode} className="space-y-4" noValidate>
        <div>
          <Label htmlFor="phone">Mobile number</Label>
          <div className="flex h-12 items-center rounded-xl border border-line bg-white focus-within:border-forest focus-within:ring-2 focus-within:ring-forest/15">
            <span className="border-r border-line px-3.5 text-small text-ink-soft">+94</span>
            <input
              id="phone"
              inputMode="tel"
              autoComplete="tel"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="77 123 4567"
              aria-invalid={error ? true : undefined}
              aria-describedby={error ? 'phone-error' : undefined}
              className="h-full w-full bg-transparent px-3.5 text-body text-ink placeholder:text-ink-faint focus:outline-none" />

          </div>
          {error ? <p id="phone-error" role="alert" className="mt-2 text-caption text-state-danger">{error}</p> : null}
        </div>
        <Button type="submit" size="lg" className="w-full" disabled={busy}>
          {busy ? 'Sending code…' : 'Send code'}
        </Button>
        <p className="text-caption text-ink-faint">
          {live ?
          'We’ll send a 6-digit code to this number.' :
          'We will send a 6-digit code by SMS. Standard SMS charges do not apply.'}
        </p>
      </form>);

  }

  return (
    <form onSubmit={verify} className="space-y-4" noValidate>
      {devMode ?
      <div role="note" className="flex gap-2.5 rounded-xl border border-state-warn/30 bg-state-warnBg px-3.5 py-3">
          <InfoIcon className="mt-0.5 h-4 w-4 shrink-0 text-state-warn" aria-hidden="true" />
          <p className="text-caption text-ink">
            <span className="font-semibold">DEV MODE — no SMS is sent.</span> The code is in the local
            API log (<code>storage/logs/otp.log</code>). This is not real phone verification.
          </p>
        </div> :
      null}
      <div>
        <Label>Code sent to +94 {phone}</Label>
        <div className="flex gap-2">
          {code.map((d, i) =>
          <input
            key={i}
            ref={(el) => {
              refs.current[i] = el;
            }}
            value={d}
            inputMode="numeric"
            autoComplete={i === 0 ? 'one-time-code' : 'off'}
            aria-label={`Digit ${i + 1}`}
            disabled={busy}
            onChange={(e) => setDigit(i, e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Backspace' && !code[i] && i > 0) refs.current[i - 1]?.focus();
            }}
            className="h-12 w-full rounded-xl border border-line bg-white text-center text-h3 text-ink focus:border-forest focus:outline-none focus:ring-2 focus:ring-forest/15" />

          )}
        </div>
        {error ? <p role="alert" className="mt-2 text-caption text-state-danger">{error}</p> : null}
        {!live ?
        <p className="mt-2 text-caption text-ink-faint">
            Prototype: any six digits work (000000 shows the error state).
          </p> :
        null}
      </div>
      <Button type="submit" size="lg" className="w-full" disabled={busy}>
        {busy ? 'Checking…' : cta}
      </Button>
      <div className="flex items-center justify-between text-caption">
        <button
          type="button"
          onClick={() => {
            setError('');
            setStage('phone');
          }}
          className="text-ink-soft underline underline-offset-4">

          Change number
        </button>
        <button
          type="button"
          disabled={resendIn > 0 || busy}
          onClick={() => void resend()}
          className="text-forest disabled:text-ink-faint">

          {resendIn > 0 ? `Resend in ${resendIn}s` : 'Resend code'}
        </button>
      </div>
    </form>);

}
