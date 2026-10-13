import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { CheckCircle2Icon, QrCodeIcon, SendIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { GuardianBanner } from '../../components/student/GuardianBanner';
import { Tabs } from '../../components/ui/Tabs';
import { Button } from '../../components/ui/Button';
import { Label, TextInput } from '../../components/ui/Field';
import { Badge } from '../../components/ui/Badge';
import { useAuth } from '../../contexts/AuthContext';

const routes = [
{ id: 'existing', label: 'Parent has TuitionHub' },
{ id: 'invite', label: 'Invite my parent' },
{ id: 'claim', label: 'Parent created my profile' }];


const inviteTimeline = ['Invitation sent by SMS', 'Parent creates account', 'Guardian verification', 'Parent approves you'];

export function ConnectGuardian() {
  const { user, update, guardianState } = useAuth();
  const navigate = useNavigate();
  const [route, setRoute] = useState('existing');
  const [code, setCode] = useState('');
  const [requested, setRequested] = useState(false);
  const [invitePhone, setInvitePhone] = useState('');
  const [inviteStage, setInviteStage] = useState(-1);
  const [claimCode, setClaimCode] = useState('');
  const [claimFound, setClaimFound] = useState(false);

  const link = (guardianName: string) => {
    update({ guardianLink: 'linked', guardianName });
  };

  const linked = guardianState === 'linked';

  return (
    <AppShell
      role="Student"
      accountName={user?.name ?? 'Student'}
      accountDetail={user?.detail ?? ''}
      initials={user?.initials ?? 'ST'}
      nav={studentNav}
      title="Connect a parent or guardian"
      subtitle="Choose whichever route fits your family. All three end with your parent approving you.">
      
      <div className="max-w-3xl space-y-6">
        {linked ?
        <div className="rounded-2xl border border-state-success/25 bg-state-successBg/60 p-6">
            <div className="flex items-start gap-3">
              <CheckCircle2Icon className="mt-0.5 h-5 w-5 text-forest" aria-hidden="true" />
              <div>
                <p className="text-small font-medium text-ink">
                  Connected to {user?.guardianName ?? 'your guardian'}
                </p>
                <p className="mt-1 text-small text-ink-soft">
                  Your account is fully active. Your guardian sees attendance, marks and
                  fees for your classes — not your messages or Study activity.
                </p>
                <Button size="sm" className="mt-4" onClick={() => navigate('/student')}>
                  Go to my home
                </Button>
              </div>
            </div>
          </div> :

        <>
            <GuardianBanner />
            <Tabs items={routes} active={route} onChange={setRoute} ariaLabel="Connection route" />

            {route === 'existing' ?
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
                {requested ?
            <div className="space-y-4">
                    <Badge tone="info">Request sent</Badge>
                    <p className="text-small text-ink-soft">
                      Mrs. C. Perera received your request in her TuitionHub app and by SMS.
                      As soon as she approves, your account is fully active.
                    </p>
                    <Button variant="secondary" size="sm" onClick={() => link('Mrs. Chamari Perera')}>
                      Prototype: simulate parent approval
                    </Button>
                  </div> :

            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                setRequested(true);
              }}>
              
                    <p className="text-small text-ink-soft">
                      Ask your parent to open <strong className="text-ink">Family → Show family code</strong>{' '}
                      in their app. The code changes every few minutes.
                    </p>
                    <div>
                      <Label htmlFor="fcode">Family code</Label>
                      <TextInput
                  id="fcode"
                  value={code}
                  onChange={(e) => setCode(e.target.value.toUpperCase())}
                  placeholder="e.g. 7KQ-42P"
                  maxLength={7}
                  className="font-mono tracking-widest" />
                
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button type="submit" disabled={code.length < 6}>
                        Send request
                      </Button>
                      <Button type="button" variant="secondary" onClick={() => setRequested(true)}>
                        <QrCodeIcon className="h-4 w-4" />
                        Scan their QR instead
                      </Button>
                    </div>
                  </form>
            }
              </section> :
          null}

            {route === 'invite' ?
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
                {inviteStage < 0 ?
            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                setInviteStage(0);
              }}>
              
                    <p className="text-small text-ink-soft">
                      We send your parent an SMS invitation. They create an account, verify
                      as your guardian, then approve you.
                    </p>
                    <div>
                      <Label htmlFor="iphone">Parent's mobile number</Label>
                      <TextInput
                  id="iphone"
                  inputMode="tel"
                  required
                  value={invitePhone}
                  onChange={(e) => setInvitePhone(e.target.value)}
                  placeholder="+94 77 987 6543" />
                
                    </div>
                    <Button type="submit">
                      <SendIcon className="h-4 w-4" />
                      Send invitation
                    </Button>
                  </form> :

            <div>
                    <ol className="space-y-3">
                      {inviteTimeline.map((t, i) =>
                <li key={t} className="flex items-center gap-3">
                          <span
                    className={`flex h-6 w-6 items-center justify-center rounded-full text-caption font-semibold ${
                    i <= inviteStage ? 'bg-forest text-white' : 'bg-surface-sunk text-ink-faint'}`
                    }>
                    
                            {i + 1}
                          </span>
                          <span className={`text-small ${i <= inviteStage ? 'text-ink' : 'text-ink-faint'}`}>{t}</span>
                        </li>
                )}
                    </ol>
                    <p className="mt-4 text-caption text-ink-soft">Invitation sent to {invitePhone}.</p>
                    <Button
                variant="secondary"
                size="sm"
                className="mt-4"
                onClick={() => {
                  if (inviteStage >= inviteTimeline.length - 1) link('Your parent');else
                  setInviteStage((s) => s + 1);
                }}>
                
                      Prototype: advance the parent's progress
                    </Button>
                  </div>
            }
              </section> :
          null}

            {route === 'claim' ?
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
                {claimFound ?
            <div className="space-y-4">
                    <p className="text-small text-ink-soft">This profile was created by Mrs. C. Perera:</p>
                    <div className="rounded-xl border border-line px-4 py-3">
                      <p className="text-small font-medium text-ink">{user?.name}</p>
                      <p className="text-caption text-ink-soft">{user?.detail} · added 2 days ago</p>
                    </div>
                    <div className="flex gap-2">
                      <Button onClick={() => link('Mrs. Chamari Perera')}>Yes, this is me</Button>
                      <Button variant="secondary" onClick={() => setClaimFound(false)}>
                        Not me
                      </Button>
                    </div>
                  </div> :

            <form
              className="space-y-4"
              onSubmit={(e) => {
                e.preventDefault();
                setClaimFound(true);
              }}>
              
                    <p className="text-small text-ink-soft">
                      If your parent already added you, they have a child connection code
                      under <strong className="text-ink">Family → your name</strong>.
                    </p>
                    <div>
                      <Label htmlFor="ccode">Child connection code</Label>
                      <TextInput
                  id="ccode"
                  value={claimCode}
                  onChange={(e) => setClaimCode(e.target.value.toUpperCase())}
                  placeholder="e.g. KP-8812"
                  className="font-mono tracking-widest" />
                
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <Button type="submit" disabled={claimCode.length < 4}>
                        Find my profile
                      </Button>
                      <Button type="button" variant="secondary" onClick={() => setClaimFound(true)}>
                        <QrCodeIcon className="h-4 w-4" />
                        Scan QR
                      </Button>
                    </div>
                  </form>
            }
              </section> :
          null}

            {guardianState === 'pending' ?
          <button
            onClick={() => update({ guardianDeadline: Date.now() - 1000 })}
            className="text-caption text-ink-faint underline underline-offset-4">
            
                Prototype: preview the state after 72 hours
              </button> :
          null}
          </>
        }
      </div>
    </AppShell>);

}