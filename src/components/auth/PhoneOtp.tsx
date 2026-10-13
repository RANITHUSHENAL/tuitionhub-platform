import React, { useEffect, useRef, useState } from 'react';
import { Button } from '../ui/Button';
import { Label } from '../ui/Field';

/** Shared phone → one-time-code step used by sign-in and every signup. */
export function PhoneOtp({
  onVerified,
  cta = 'Continue'



}: {onVerified: (phone: string) => void;cta?: string;}) {
  const [phone, setPhone] = useState('');
  const [stage, setStage] = useState<'phone' | 'code'>('phone');
  const [code, setCode] = useState(['', '', '', '', '', '']);
  const [error, setError] = useState('');
  const [resendIn, setResendIn] = useState(30);
  const refs = useRef<(HTMLInputElement | null)[]>([]);

  useEffect(() => {
    if (stage !== 'code' || resendIn === 0) return;
    const id = window.setTimeout(() => setResendIn((s) => s - 1), 1000);
    return () => window.clearTimeout(id);
  }, [stage, resendIn]);

  const sendCode = (e: React.FormEvent) => {
    e.preventDefault();
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 9) {
      setError('Enter a 9-digit Sri Lankan mobile number.');
      return;
    }
    setError('');
    setStage('code');
    setResendIn(30);
    window.setTimeout(() => refs.current[0]?.focus(), 50);
  };

  const setDigit = (i: number, v: string) => {
    const d = v.replace(/\D/g, '').slice(-1);
    const nextCode = [...code];
    nextCode[i] = d;
    setCode(nextCode);
    if (d && i < 5) refs.current[i + 1]?.focus();
  };

  const verify = (e: React.FormEvent) => {
    e.preventDefault();
    if (code.join('').length !== 6) {
      setError('Enter all six digits.');
      return;
    }
    if (code.join('') === '000000') {
      setError('That code did not match. Check the latest SMS and try again.');
      return;
    }
    setError('');
    onVerified(`+94 ${phone}`);
  };

  if (stage === 'phone') {
    return (
      <form onSubmit={sendCode} className="space-y-4">
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
              className="h-full w-full bg-transparent px-3.5 text-body text-ink placeholder:text-ink-faint focus:outline-none" />
            
          </div>
          {error ? <p className="mt-2 text-caption text-state-danger">{error}</p> : null}
        </div>
        <Button type="submit" size="lg" className="w-full">
          Send code
        </Button>
        <p className="text-caption text-ink-faint">
          We will send a 6-digit code by SMS. Standard SMS charges do not apply.
        </p>
      </form>);

  }

  return (
    <form onSubmit={verify} className="space-y-4">
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
            aria-label={`Digit ${i + 1}`}
            onChange={(e) => setDigit(i, e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Backspace' && !code[i] && i > 0) refs.current[i - 1]?.focus();
            }}
            className="h-12 w-full rounded-xl border border-line bg-white text-center text-h3 text-ink focus:border-forest focus:outline-none focus:ring-2 focus:ring-forest/15" />

          )}
        </div>
        {error ? <p className="mt-2 text-caption text-state-danger">{error}</p> : null}
        <p className="mt-2 text-caption text-ink-faint">
          Prototype: any six digits work (000000 shows the error state).
        </p>
      </div>
      <Button type="submit" size="lg" className="w-full">
        {cta}
      </Button>
      <div className="flex items-center justify-between text-caption">
        <button
          type="button"
          onClick={() => setStage('phone')}
          className="text-ink-soft underline underline-offset-4">
          
          Change number
        </button>
        <button
          type="button"
          disabled={resendIn > 0}
          onClick={() => setResendIn(30)}
          className="text-forest disabled:text-ink-faint">
          
          {resendIn > 0 ? `Resend in ${resendIn}s` : 'Resend code'}
        </button>
      </div>
    </form>);

}