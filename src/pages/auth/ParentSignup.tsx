import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CheckCircle2Icon } from 'lucide-react';
import { AuthLayout, useAuthParams } from '../../components/auth/AuthLayout';
import { PhoneOtp } from '../../components/auth/PhoneOtp';
import { Button } from '../../components/ui/Button';
import { Label, TextInput } from '../../components/ui/Field';
import { useAuth } from '../../contexts/AuthContext';
import { initialsOf } from '../../utils/format';

type Step = 'phone' | 'name' | 'created';

/**
 * Basic parent account only: phone → code → name → in.
 * Profile details, guardian verification and children come later, inside
 * the product, when a feature actually needs them.
 */
export function ParentSignup() {
  const { next } = useAuthParams();
  const navigate = useNavigate();
  const { signIn } = useAuth();
  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('');
  const [name, setName] = useState('');

  const create = (e: React.FormEvent) => {
    e.preventDefault();
    signIn({
      id: `par-${Date.now()}`,
      role: 'parent',
      name: name.trim(),
      initials: initialsOf(name) || 'PA',
      detail: 'Parent account',
      phone,
      phoneVerified: true,
      verification: 'none',
      profileComplete: false,
      childrenLinked: 0
    });
    setStep('created');
  };

  return (
    <AuthLayout
      title={step === 'phone' ? 'Create a parent account' : step === 'name' ? 'What should we call you?' : 'Your account is ready'}
      subtitle={
      step === 'phone' ?
      'Just your mobile number to start. You can add your children and verify as a guardian later.' :
      step === 'name' ?
      'This is the name teachers and institutes will see.' :
      undefined
      }
      step={step === 'created' ? undefined : { current: step === 'phone' ? 1 : 2, total: 2, label: `Step ${step === 'phone' ? 1 : 2} of 2` }}>
      
      {step === 'phone' ?
      <PhoneOtp
        onVerified={(p) => {
          setPhone(p);
          setStep('name');
        }} /> :

      null}

      {step === 'name' ?
      <form className="space-y-5" onSubmit={create}>
          <div>
            <Label htmlFor="pname">Your name</Label>
            <TextInput
            id="pname"
            required
            autoFocus
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="Chamari Perera" />
          
          </div>
          <Button type="submit" size="lg" className="w-full" disabled={!name.trim()}>
            Create account
          </Button>
          <p className="text-caption text-ink-faint">
            No ID or child details needed now.
          </p>
        </form> :
      null}

      {step === 'created' ?
      <div className="space-y-6">
          <div className="flex gap-3 rounded-2xl border border-state-success/25 bg-state-successBg/60 p-5">
            <CheckCircle2Icon className="mt-0.5 h-5 w-5 shrink-0 text-state-success" aria-hidden="true" />
            <div>
              <p className="text-small font-medium text-ink">Welcome, {name.trim().split(' ')[0]}</p>
              <p className="mt-1 text-small text-ink-soft">
                Your parent account is created and your phone is verified. You can explore
                TuitionHub right away.
              </p>
            </div>
          </div>
          <div className="rounded-2xl border border-line bg-white p-5">
            <p className="text-small font-medium text-ink">Later, when you’re ready</p>
            <ul className="mt-2 space-y-1.5 text-caption text-ink-soft">
              <li>1. Complete your parent profile</li>
              <li>2. Verify as a guardian</li>
              <li>3. Connect your child</li>
            </ul>
          </div>
          <Button size="lg" className="w-full" onClick={() => navigate(next ?? '/parent', { replace: true })}>
            Enter TuitionHub
          </Button>
        </div> :
      null}
    </AuthLayout>);

}