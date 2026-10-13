import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { PlusIcon, QrCodeIcon, ShieldCheckIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Avatar } from '../../components/ui/Avatar';
import { Button, LinkButton } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { EmptyState, Toast } from '../../components/ui/States';
import { ParentSetupCard } from '../../components/parent/ParentSetupCard';
import { connectChildPath } from '../../utils/parentSetup';
import { TrustBadges } from '../../components/ui/TrustBadges';
import { QrCode } from '../../components/ui/QrCode';
import { useAuth } from '../../contexts/AuthContext';
import { grades } from '../../data/classes';
import { initialsOf } from '../../utils/format';

interface Child {
  id: string;
  name: string;
  grade: string;
  status: 'linked' | 'awaiting-claim';
  code?: string;
  institutes: string[];
}

const initialChildren: Child[] = [
{ id: 'c1', name: 'Kasun Perera', grade: 'Grade 12 · A/L 2027', status: 'linked', institutes: ['Sarasavi Academy', 'Lanka Science Centre'] },
{ id: 'c2', name: 'Nethmi Perera', grade: 'Grade 10 · O/L 2028', status: 'linked', institutes: ['Horizon Learning Centre'] }];


function makeCode() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  const pick = () => chars[Math.floor(Math.random() * chars.length)];
  return `${pick()}${pick()}${pick()}-${pick()}${pick()}${pick()}`;
}

export function ParentFamily() {
  const { user, update } = useAuth();
  const isNew = !!user && (user.childrenLinked ?? 0) === 0;
  const [children, setChildren] = useState<Child[]>(isNew ? [] : initialChildren);
  const [addOpen, setAddOpen] = useState(false);
  const [codeOpen, setCodeOpen] = useState(false);
  const [familyCode, setFamilyCode] = useState(makeCode);
  const [newName, setNewName] = useState('');
  const [newGrade, setNewGrade] = useState('Grade 9');
  const [request, setRequest] = useState(!isNew);
  const [toast, setToast] = useState<string | null>(null);

  useEffect(() => {
    if (!codeOpen) return;
    const id = window.setInterval(() => setFamilyCode(makeCode()), 120_000);
    return () => window.clearInterval(id);
  }, [codeOpen]);

  const verified = (user?.verification ?? 'verified') === 'verified';
  const profileDone = !!user?.profileComplete || verified;

  useEffect(() => {
    if (user && (user.childrenLinked ?? 0) !== children.length) update({ childrenLinked: children.length });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [children.length]);

  return (
    <AppShell
      role="Parent"
      accountName={user?.name ?? 'Mrs. Chamari Perera'}
      accountDetail={user?.detail ?? '2 children · Nugegoda'}
      initials={user?.initials ?? 'CP'}
      nav={parentNav}
      title="Family"
      subtitle="Every child you are guardian for, and how they are connected"
      actions={
      verified ?
      <div className="flex gap-2">
            <Button variant="secondary" size="sm" onClick={() => setCodeOpen(true)}>
              <QrCodeIcon className="h-4 w-4" />
              Show family code
            </Button>
            <Button size="sm" onClick={() => setAddOpen(true)}>
              <PlusIcon className="h-4 w-4" />
              Add child
            </Button>
          </div> :

      <LinkButton to={connectChildPath(user)} size="sm">
            Connect a child
          </LinkButton>

      }>
      
      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="space-y-6">
          {!verified ?
          <>
              <section className="flex flex-wrap items-start gap-4 rounded-2xl border border-line bg-white p-5 shadow-card">
                <span className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-forest-50 text-forest">
                  <ShieldCheckIcon className="h-5 w-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                  <p className="text-h3 text-ink">Connecting a child needs a verified guardian</p>
                  <p className="mt-1 text-small text-ink-soft">
                    {user?.verification === 'in-progress' || user?.verification === 'review' ?
                  'Your verification is with a reviewer. You can connect children as soon as it is approved.' :
                  profileDone ?
                  'One more step: a quick ID check, confirmed by a person. Then you can add your children.' :
                  'Two short steps first: your parent profile, then a quick ID check. Then you can add your children.'}
                  </p>
                </div>
                {user?.verification !== 'in-progress' && user?.verification !== 'review' ?
              <LinkButton to={connectChildPath(user)} size="sm">
                    {profileDone ? 'Verify as guardian' : 'Complete parent profile'}
                  </LinkButton> :
              null}
              </section>
              <ParentSetupCard />
            </> :
          children.length === 0 && !request ?
          <EmptyState
            icon={<PlusIcon className="h-5 w-5" />}
            title="Connect your first child"
            body="Add them yourself and give them a code to claim, or show your family code so they can send you a request."
            action={
            <div className="flex flex-wrap justify-center gap-2">
                  <Button onClick={() => setAddOpen(true)}>Add child</Button>
                  <Button variant="secondary" onClick={() => setCodeOpen(true)}>
                    Show family code
                  </Button>
                </div>
            } /> :

          null}
          {request ?
          <section className="flex flex-wrap items-center gap-4 rounded-2xl border border-state-info/25 bg-state-infoBg/60 p-5">
              <Avatar initials="DP" />
              <div className="min-w-0 flex-1">
                <p className="text-small font-medium text-ink">Dinithi Pathirana wants to connect</p>
                <p className="text-caption text-ink-soft">Grade 11 · O/L 2027 · used your family code 4 minutes ago</p>
              </div>
              <div className="flex gap-2">
                <Button
                size="sm"
                disabled={!verified}
                onClick={() => {
                  setRequest(false);
                  setChildren((c) => [...c, { id: 'c3', name: 'Dinithi Pathirana', grade: 'Grade 11 · O/L 2027', status: 'linked', institutes: [] }]);
                  setToast('Dinithi is now linked to your family');
                }}>
                
                  Approve
                </Button>
                <Button variant="secondary" size="sm" onClick={() => setRequest(false)}>
                  Decline
                </Button>
              </div>
            </section> :
          null}

          <ul className="grid gap-4 md:grid-cols-2">
            {children.map((c) =>
            <li key={c.id} className="flex flex-col rounded-2xl border border-line bg-white p-5 shadow-card">
                <div className="flex items-center gap-3">
                  <Avatar initials={initialsOf(c.name)} />
                  <div className="min-w-0">
                    <p className="truncate text-h3 text-ink">{c.name}</p>
                    <p className="truncate text-caption text-ink-soft">{c.grade}</p>
                  </div>
                </div>
                <div className="mt-4">
                  {c.status === 'linked' ?
                <TrustBadges
                  flags={[
                  { label: 'Child linked', done: true },
                  { label: 'Enrollment verified', done: c.institutes.length > 0 }]
                  } /> :


                <div className="rounded-xl border border-dashed border-line-strong px-3.5 py-3">
                      <p className="text-caption text-ink-soft">Waiting for {c.name.split(' ')[0]} to claim this profile</p>
                      <p className="mt-1 font-mono text-h3 tracking-widest text-ink">{c.code}</p>
                    </div>
                }
                </div>
                <p className="mt-3 text-caption text-ink-faint">
                  {c.institutes.length ? c.institutes.join(' · ') : 'Not enrolled at any institute yet'}
                </p>
                <div className="mt-auto flex gap-2 pt-4">
                  <LinkButton to="/parent" size="sm" variant="secondary" className="flex-1">
                    Overview
                  </LinkButton>
                  <LinkButton to="/parent/fees" size="sm" variant="secondary" className="flex-1">
                    Fees
                  </LinkButton>
                </div>
              </li>
            )}
          </ul>
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Your trust level</h2>
            <div className="mt-3">
              <TrustBadges
                flags={[
                { label: 'Phone verified', done: true },
                { label: 'Parent profile', done: profileDone },
                { label: 'Guardian verified', done: verified },
                { label: 'Child linked', done: children.some((c) => c.status === 'linked') }]
                } />
              
            </div>
            <Link
              to="/parent/verification"
              className="mt-4 inline-flex items-center gap-1.5 text-small font-medium text-forest">
              
              <ShieldCheckIcon className="h-4 w-4" />
              Guardian verification
            </Link>
          </section>
          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <h2 className="text-h3 text-ink">Three ways children connect</h2>
            <ul className="mt-3 space-y-2 text-small text-ink-soft">
              <li><strong className="text-ink">Already has an account</strong> — they enter your family code or scan it; you approve.</li>
              <li><strong className="text-ink">Invited you</strong> — you signed up from their SMS invitation and approve here.</li>
              <li><strong className="text-ink">You added them first</strong> — they claim the profile with its connection code.</li>
            </ul>
          </section>
        </aside>
      </div>

      <Modal open={codeOpen} onClose={() => setCodeOpen(false)} title="Family code">
        <div className="flex flex-col items-center text-center">
          <QrCode seed={familyCode} size={200} label={`Family code ${familyCode}`} />
          <p className="mt-4 font-mono text-h2 tracking-widest text-ink">{familyCode}</p>
          <p className="mt-2 max-w-xs text-caption text-ink-soft">
            Your child enters this code or scans the QR. It changes every 2 minutes and you
            still approve every request.
          </p>
        </div>
      </Modal>

      <Modal open={addOpen} onClose={() => setAddOpen(false)} title="Add a child">
        <form
          className="space-y-4"
          onSubmit={(e) => {
            e.preventDefault();
            const code = `${initialsOf(newName)}-${Math.floor(1000 + Math.random() * 9000)}`;
            setChildren((c) => [...c, { id: `c${Date.now()}`, name: newName, grade: newGrade, status: 'awaiting-claim', code, institutes: [] }]);
            setAddOpen(false);
            setNewName('');
            setToast(`Profile created. Give ${newName.split(' ')[0]} the code ${code} to claim it.`);
          }}>
          
          <div>
            <Label htmlFor="cname">Child's name</Label>
            <TextInput id="cname" required value={newName} onChange={(e) => setNewName(e.target.value)} />
          </div>
          <div>
            <Label htmlFor="cgrade">Grade</Label>
            <Select id="cgrade" value={newGrade} onChange={(e) => setNewGrade(e.target.value)}>
              {grades.map((g) =>
              <option key={g}>{g}</option>
              )}
            </Select>
          </div>
          <p className="text-caption text-ink-faint">
            We create a profile and a one-time connection code. Your child claims it from
            their own phone.
          </p>
          <Button type="submit" className="w-full" disabled={!verified}>
            {verified ? 'Create profile' : 'Complete guardian verification first'}
          </Button>
        </form>
      </Modal>

      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </AppShell>);

}