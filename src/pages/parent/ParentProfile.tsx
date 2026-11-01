import React, { useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { ParentStages } from '../../components/parent/ParentStages';
import { Button } from '../../components/ui/Button';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { Toast } from '../../components/ui/States';
import { useAuth } from '../../contexts/AuthContext';
import { cities } from '../../data/classes';
import { initialsOf } from '../../utils/format';
import { CHILD_LINK_NOTE, parentSetupSteps } from '../../utils/parentSetup';

/** Stage 2 — parent details. Separate from signup; no ID documents are ever collected (C6). */
export function ParentProfile() {
  const { user, update } = useAuth();
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const next = params.get('next');
  const [name, setName] = useState(user?.name ?? '');
  const [relationship, setRelationship] = useState(user?.relationship ?? 'Mother');
  const [area, setArea] = useState(user?.area ?? 'Nugegoda');
  const [email, setEmail] = useState(user?.email ?? '');
  const [language, setLanguage] = useState('English');
  const [saved, setSaved] = useState(false);

  const steps = parentSetupSteps(user);

  const save = (e: React.FormEvent) => {
    e.preventDefault();
    update({
      name: name.trim(),
      initials: initialsOf(name) || user?.initials,
      relationship,
      area,
      email: email.trim() || undefined,
      profileComplete: true,
      detail: `${relationship} · ${area}`
    });
    if (next) navigate(next);else
    setSaved(true);
  };

  return (
    <AppShell
      role="Parent"
      accountName={user?.name ?? 'Parent'}
      accountDetail={user?.detail ?? 'Parent account'}
      initials={user?.initials ?? 'PA'}
      nav={parentNav}
      title="Parent profile"
      subtitle="Basic details about you.">
      
      <div className="max-w-4xl space-y-6">
        <ParentStages steps={steps} current="profile" />

        <div className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
          <form onSubmit={save} className="space-y-4 rounded-2xl border border-line bg-white p-6 shadow-card">
            <div>
              <Label htmlFor="pp-name">Full name</Label>
              <TextInput id="pp-name" required value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <div>
                <Label htmlFor="pp-rel">Relationship to your children</Label>
                <Select id="pp-rel" value={relationship} onChange={(e) => setRelationship(e.target.value)}>
                  <option>Mother</option>
                  <option>Father</option>
                  <option>Legal guardian</option>
                  <option>Other family guardian</option>
                </Select>
              </div>
              <div>
                <Label htmlFor="pp-area">Area</Label>
                <Select id="pp-area" value={area} onChange={(e) => setArea(e.target.value)}>
                  {cities.filter((c) => c !== 'Online').map((c) =>
                  <option key={c}>{c}</option>
                  )}
                </Select>
              </div>
              <div>
                <Label htmlFor="pp-email" hint="Optional">
                  Email for receipts
                </Label>
                <TextInput id="pp-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
              </div>
              <div>
                <Label htmlFor="pp-lang">Updates language</Label>
                <Select id="pp-lang" value={language} onChange={(e) => setLanguage(e.target.value)}>
                  <option>English</option>
                  <option>සිංහල</option>
                  <option>தமிழ்</option>
                </Select>
              </div>
            </div>
            <div className="rounded-xl bg-surface-muted px-4 py-3 text-caption text-ink-soft">
              Mobile <span className="font-medium text-ink">{user?.phone}</span> · used to sign in
            </div>
            <Button type="submit" className="w-full sm:w-auto">
              {next ? 'Save and continue' : 'Save profile'}
            </Button>
          </form>

          <aside className="space-y-4">
            <section className="rounded-2xl border border-line bg-white p-5">
              <h2 className="text-h3 text-ink">Linking your child</h2>
              <p className="mt-2 text-small text-ink-soft">{CHILD_LINK_NOTE}</p>
            </section>
            <p className="px-1 text-caption text-ink-faint">
              TuitionHub never asks for ID documents. Institutes see your name and relationship.
            </p>
          </aside>
        </div>
      </div>
      <Toast open={saved} message="Profile saved" onClose={() => setSaved(false)} />
    </AppShell>);

}