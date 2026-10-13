import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CheckIcon, ClockIcon } from 'lucide-react';
import { AuthLayout, useAuthParams } from '../../components/auth/AuthLayout';
import { PhoneOtp } from '../../components/auth/PhoneOtp';
import { Button } from '../../components/ui/Button';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { useAuth, GUARDIAN_WINDOW_MS } from '../../contexts/AuthContext';
import { examTargets, grades, subjects } from '../../data/classes';
import { initialsOf } from '../../utils/format';
import type { AuthUser } from '../../types';

type Step = 'phone' | 'details' | 'result';

function ageFrom(dob: string) {
  if (!dob) return 0;
  const d = new Date(dob);
  const diff = Date.now() - d.getTime();
  return Math.floor(diff / (365.25 * 24 * 3600 * 1000));
}

export function StudentSignup() {
  const { next } = useAuthParams();
  const navigate = useNavigate();
  const { signIn } = useAuth();
  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('');
  const [name, setName] = useState('');
  const [dob, setDob] = useState('');
  const [grade, setGrade] = useState('Grade 12');
  const [exam, setExam] = useState('A/L 2027');
  const [picked, setPicked] = useState<string[]>(['Physics']);
  const [minor, setMinor] = useState(true);

  const toggle = (s: string) =>
  setPicked((p) => p.includes(s) ? p.filter((x) => x !== s) : [...p, s]);

  const create = (e: React.FormEvent) => {
    e.preventDefault();
    const isMinor = ageFrom(dob) < 18;
    setMinor(isMinor);
    const user: AuthUser = {
      id: `std-${Date.now()}`,
      role: 'student',
      name,
      initials: initialsOf(name) || 'ST',
      detail: `${grade} · ${exam}`,
      phone,
      phoneVerified: true,
      isMinor,
      guardianLink: isMinor ? 'pending' : 'not-required',
      guardianDeadline: isMinor ? Date.now() + GUARDIAN_WINDOW_MS : undefined,
      enrollmentVerified: false
    };
    signIn(user);
    setStep('result');
  };

  const stepIndex = step === 'phone' ? 1 : step === 'details' ? 2 : 3;

  return (
    <AuthLayout
      title={
      step === 'phone' ?
      'Create a student account' :
      step === 'details' ?
      'About you' :
      minor ?
      'Your account is ready' :
      'You’re all set'
      }
      subtitle={
      step === 'phone' ?
      'You can do this on your own — no parent needs to be with you right now.' :
      step === 'details' ?
      'This shapes your recommendations and who can see you in Study.' :
      undefined
      }
      step={{ current: stepIndex, total: 3, label: `Step ${stepIndex} of 3` }}>
      
      {step === 'phone' ?
      <PhoneOtp
        onVerified={(p) => {
          setPhone(p);
          setStep('details');
        }} /> :

      null}

      {step === 'details' ?
      <form onSubmit={create} className="space-y-4">
          <div>
            <Label htmlFor="sname">Full name</Label>
            <TextInput id="sname" required value={name} onChange={(e) => setName(e.target.value)} placeholder="Dinithi Pathirana" />
          </div>
          <div>
            <Label htmlFor="sdob" hint="Used only to check guardian needs">
              Date of birth
            </Label>
            <TextInput id="sdob" type="date" required value={dob} onChange={(e) => setDob(e.target.value)} />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="sgrade">Grade</Label>
              <Select id="sgrade" value={grade} onChange={(e) => setGrade(e.target.value)}>
                {grades.map((g) =>
              <option key={g}>{g}</option>
              )}
              </Select>
            </div>
            <div>
              <Label htmlFor="sexam">Exam target</Label>
              <Select id="sexam" value={exam} onChange={(e) => setExam(e.target.value)}>
                {examTargets.map((g) =>
              <option key={g}>{g}</option>
              )}
              </Select>
            </div>
          </div>
          <fieldset>
            <legend className="mb-2 text-small font-medium text-ink">Subjects</legend>
            <div className="flex flex-wrap gap-2">
              {subjects.map((s) => {
              const on = picked.includes(s);
              return (
                <button
                  key={s}
                  type="button"
                  aria-pressed={on}
                  onClick={() => toggle(s)}
                  className={`inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-caption font-medium transition-colors duration-150 ease-out ${
                  on ? 'border-forest bg-forest-50 text-forest' : 'border-line bg-white text-ink-soft hover:border-line-strong'}`
                  }>
                  
                    {on ? <CheckIcon className="h-3 w-3" /> : null}
                    {s}
                  </button>);

            })}
            </div>
          </fieldset>
          <Button type="submit" size="lg" className="w-full" disabled={!picked.length}>
            Create account
          </Button>
        </form> :
      null}

      {step === 'result' ?
      minor ?
      <div className="space-y-5">
            <div className="rounded-2xl border border-state-warn/25 bg-state-warnBg/60 p-5">
              <div className="flex items-start gap-3">
                <ClockIcon className="mt-0.5 h-5 w-5 shrink-0 text-state-warn" aria-hidden="true" />
                <div>
                  <p className="text-small font-medium text-ink">Pending guardian connection · 72 hours</p>
                  <p className="mt-1 text-small text-ink-soft">
                    Connect a parent or guardian to keep your account active and unlock
                    full TuitionHub features. You can browse, see your schedule and join
                    public study sessions in the meantime.
                  </p>
                </div>
              </div>
            </div>
            <Button size="lg" className="w-full" onClick={() => navigate('/student/connect-guardian', { replace: true })}>
              Connect a parent now
            </Button>
            <Button
          variant="secondary"
          size="lg"
          className="w-full"
          onClick={() => navigate(next ?? '/student', { replace: true })}>
          
              {next ? 'Later — continue where I was' : 'Later — go to my home'}
            </Button>
          </div> :

      <Button size="lg" className="w-full" onClick={() => navigate(next ?? '/student', { replace: true })}>
            {next ? 'Continue where I was' : 'Go to my home'}
          </Button> :

      null}
    </AuthLayout>);

}