import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BuildingIcon, ClockIcon, PresentationIcon } from 'lucide-react';
import { AuthLayout, useAuthParams } from '../../components/auth/AuthLayout';
import { PhoneOtp } from '../../components/auth/PhoneOtp';
import { InstituteLinkForm } from '../../components/teacher/InstituteLinkForm';
import { Button } from '../../components/ui/Button';
import { Label, TextInput } from '../../components/ui/Field';
import { useAuth } from '../../contexts/AuthContext';
import { subjects } from '../../data/classes';
import type { TeachingWorkspace } from '../../types';
import { initialsOf } from '../../utils/format';

type Step = 'phone' | 'details' | 'how' | 'institute' | 'pending';
type How = 'independent' | 'institute';

export function TeacherSignup() {
  const { next } = useAuthParams();
  const navigate = useNavigate();
  const { signIn } = useAuth();
  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('');
  const [name, setName] = useState('');
  const [picked, setPicked] = useState<string[]>(['Physics']);
  const [how, setHow] = useState<How[]>(['independent']);
  const [requested, setRequested] = useState<string | null>(null);

  const order: Step[] = ['phone', 'details', 'how', 'institute'];
  const current = Math.min(order.indexOf(step === 'pending' ? 'institute' : step) + 1, 4);

  const finish = (instituteName: string | null) => {
    const workspaces: TeachingWorkspace[] = [];
    if (how.includes('independent'))
    workspaces.push({ id: 'ws-independent', kind: 'independent', name: 'Independent classes', detail: 'Your own classes', status: 'active' });
    if (instituteName)
    workspaces.push({ id: 'ws-req', kind: 'institute', name: instituteName, detail: 'Waiting for approval', status: 'pending' });
    signIn({
      id: `tch-${Date.now()}`,
      role: 'teacher',
      name,
      initials: initialsOf(name) || 'TE',
      detail: `${picked.join(', ')} teacher`,
      phone,
      phoneVerified: true,
      workspaces
    });
    navigate(next ?? (workspaces.some((w) => w.status === 'active') ? '/teacher' : '/teacher/workspaces'), { replace: true });
  };

  const toggleHow = (h: How) =>
  setHow((cur) => cur.includes(h) ? cur.filter((x) => x !== h) : [...cur, h]);

  return (
    <AuthLayout
      title={
      step === 'phone' ?
      'Teach on TuitionHub' :
      step === 'details' ?
      'About you' :
      step === 'how' ?
      'How do you teach?' :
      step === 'institute' ?
      'Find your institute' :
      'Request sent'
      }
      subtitle={
      step === 'phone' ?
      'Your phone number is your account. If you are also a parent or student here, this adds a teacher role to the same account.' :
      step === 'how' ?
      'Choose one or both. Many teachers run their own classes and also teach at an institute.' :
      step === 'institute' ?
      'The institute has to approve you before you can see any of its classes.' :
      undefined
      }
      step={{ current, total: 4, label: `Step ${current} of 4` }}>
      
      {step === 'phone' ?
      <PhoneOtp
        onVerified={(p) => {
          setPhone(p);
          setStep('details');
        }} /> :

      null}

      {step === 'details' ?
      <form
        className="space-y-5"
        onSubmit={(e) => {
          e.preventDefault();
          setStep('how');
        }}>
        
          <div>
            <Label htmlFor="tname">Full name</Label>
            <TextInput id="tname" required value={name} onChange={(e) => setName(e.target.value)} placeholder="Nimal Perera" />
          </div>
          <fieldset>
            <legend className="mb-2 text-small font-medium text-ink">Subjects you teach</legend>
            <div className="flex flex-wrap gap-2">
              {subjects.map((s) => {
              const on = picked.includes(s);
              return (
                <button
                  key={s}
                  type="button"
                  aria-pressed={on}
                  onClick={() => setPicked((p) => on ? p.filter((x) => x !== s) : [...p, s])}
                  className={`rounded-full border px-3 py-1.5 text-caption font-medium transition-colors duration-150 ease-out ${
                  on ? 'border-forest bg-forest text-white' : 'border-line bg-white text-ink-soft hover:border-forest'}`
                  }>
                  
                    {s}
                  </button>);

            })}
            </div>
          </fieldset>
          <Button type="submit" size="lg" className="w-full" disabled={!name || picked.length === 0}>
            Continue
          </Button>
        </form> :
      null}

      {step === 'how' ?
      <div className="space-y-3">
          <HowOption
          on={how.includes('independent')}
          onClick={() => toggleHow('independent')}
          icon={<PresentationIcon className="h-5 w-5" />}
          title="Independently"
          body="Create your own classes, set fees and schedules, and collect payments. No institute needed." />
        
          <HowOption
          on={how.includes('institute')}
          onClick={() => toggleHow('institute')}
          icon={<BuildingIcon className="h-5 w-5" />}
          title="I teach at an institute"
          body="Link to your institute. After they approve, you get the classes they assign to you." />
        
          <Button
          size="lg"
          className="mt-2 w-full"
          disabled={how.length === 0}
          onClick={() => how.includes('institute') ? setStep('institute') : finish(null)}>
          
            Continue
          </Button>
        </div> :
      null}

      {step === 'institute' ?
      <InstituteLinkForm
        onRequested={(n) => {
          setRequested(n);
          setStep('pending');
        }} /> :

      null}

      {step === 'pending' && requested ?
      <div className="space-y-5">
          <div className="flex gap-3 rounded-2xl border border-state-info/25 bg-state-infoBg/60 p-5">
            <ClockIcon className="mt-0.5 h-5 w-5 shrink-0 text-state-info" aria-hidden="true" />
            <div>
              <p className="text-small font-medium text-ink">Waiting for {requested}</p>
              <p className="mt-1 text-caption text-ink-soft">
                We’ll notify you by SMS when they approve. Until then you won’t see any of
                their classes or students.
                {how.includes('independent') ? ' Your independent workspace is ready now.' : ''}
              </p>
            </div>
          </div>
          <Button size="lg" className="w-full" onClick={() => finish(requested)}>
            {how.includes('independent') ? 'Open my teaching workspace' : 'Finish'}
          </Button>
        </div> :
      null}
    </AuthLayout>);

}

function HowOption({
  on,
  onClick,
  icon,
  title,
  body






}: {on: boolean;onClick: () => void;icon: React.ReactNode;title: string;body: string;}) {
  return (
    <button
      type="button"
      role="checkbox"
      aria-checked={on}
      onClick={onClick}
      className={`flex w-full items-start gap-4 rounded-2xl border p-5 text-left transition-[border-color,background-color] duration-150 ease-out ${
      on ? 'border-forest bg-forest-50' : 'border-line bg-white hover:border-line-strong'}`
      }>
      
      <span className={`inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${on ? 'bg-forest text-white' : 'bg-forest-50 text-forest'}`}>
        {icon}
      </span>
      <span>
        <span className="block text-h3 text-ink">{title}</span>
        <span className="mt-1 block text-small text-ink-soft">{body}</span>
      </span>
    </button>);

}