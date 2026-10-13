import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BanknoteIcon,
  CheckCircle2Icon,
  GraduationCapIcon,
  MapPinIcon,
  SearchIcon } from
'lucide-react';
import { Button } from '../ui/Button';
import { Badge } from '../ui/Badge';

const providerLink =
'inline-flex h-10 items-center justify-center rounded-xl border border-line bg-white px-4 text-small font-medium text-ink transition-[background-color,border-color] duration-150 ease-out hover:border-line-strong hover:bg-surface-muted';

const quickFilters = ['A/L 2027', 'O/L 2027', 'Combined Maths', 'Online classes'];

export function Hero() {
  const navigate = useNavigate();
  const [subject, setSubject] = useState('');
  const [place, setPlace] = useState('');

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (subject) params.set('q', subject);
    if (place) params.set('where', place);
    navigate(`/search?${params.toString()}`);
  };

  return (
    <section className="border-b border-line/60">
      <div className="mx-auto grid max-w-[1200px] items-start gap-12 px-5 pb-14 pt-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-16 lg:px-8 lg:pb-20 lg:pt-16">
        <div>
          <span className="inline-flex items-center gap-2 rounded-full bg-forest-100 px-3 py-1.5 text-caption font-medium text-forest">
            <span className="h-1.5 w-1.5 rounded-full bg-clay" aria-hidden="true" />
            Early access · Nugegoda – Maharagama corridor
          </span>

          <h1 className="mt-5 text-display text-ink">
            Know your child went.
            <br />
            Know what they scored.
            <br />
            <span className="text-forest">Know where every payment goes.</span>
          </h1>

          <p className="mt-5 max-w-xl text-body text-ink-soft">
            Find the right tuition class, then follow attendance, marks and fees in one
            place — the same record for parents, students, teachers and institutes.
          </p>

          <form
            onSubmit={submit}
            className="mt-8 rounded-2xl border border-line bg-white p-2 shadow-lift sm:flex sm:items-center sm:gap-1"
            role="search"
            aria-label="Find a tuition class">
            
            <div className="flex flex-1 items-center gap-2.5 px-3 py-2.5">
              <SearchIcon className="h-5 w-5 shrink-0 text-ink-faint" aria-hidden="true" />
              <input
                value={subject}
                onChange={(e) => setSubject(e.target.value)}
                placeholder="What do you want to learn?"
                aria-label="Subject, grade or exam target"
                className="w-full bg-transparent text-body text-ink placeholder:text-ink-faint focus:outline-none" />
              
            </div>
            <div className="h-px bg-line sm:h-9 sm:w-px" aria-hidden="true" />
            <div className="flex flex-1 items-center gap-2.5 px-3 py-2.5 sm:max-w-[16rem]">
              <MapPinIcon className="h-5 w-5 shrink-0 text-ink-faint" aria-hidden="true" />
              <input
                value={place}
                onChange={(e) => setPlace(e.target.value)}
                placeholder="Nugegoda / Online"
                aria-label="City or online"
                className="w-full bg-transparent text-body text-ink placeholder:text-ink-faint focus:outline-none" />
              
            </div>
            <Button type="submit" variant="accent" size="lg" className="mt-2 w-full sm:mt-0 sm:w-auto">
              Find a class
            </Button>
          </form>

          <div className="mt-4 flex flex-wrap gap-2">
            {quickFilters.map((f) =>
            <button
              key={f}
              onClick={() => navigate(`/search?q=${encodeURIComponent(f)}`)}
              className="rounded-full border border-line bg-white px-3 py-1.5 text-caption font-medium text-ink-soft transition-colors duration-150 ease-out hover:border-forest hover:text-forest">
              
                {f}
              </button>
            )}
          </div>

          <div className="mt-8 flex flex-wrap items-center gap-x-4 gap-y-3 border-t border-line pt-6">
            <span className="text-small text-ink-soft">For education providers</span>
            <div className="flex flex-wrap gap-2">
              <a href="#institutes" className={providerLink}>
                I run an institute
              </a>
              <a href="#teachers" className={providerLink}>
                I’m a teacher
              </a>
            </div>
          </div>
        </div>

        <ProofPanel />
      </div>
    </section>);

}

function ProofPanel() {
  return (
    <div className="relative">
      <div className="rounded-3xl border border-line bg-white p-5 shadow-lift">
        <div className="flex items-center justify-between gap-3 border-b border-line pb-4">
          <div>
            <p className="text-caption uppercase tracking-wide text-ink-faint">Parent view</p>
            <p className="mt-0.5 text-h3 text-ink">Kasun · A/L 2027</p>
          </div>
          <Badge tone="success">3 classes</Badge>
        </div>

        <ul className="divide-y divide-line">
          <ProofRow
            icon={<CheckCircle2Icon className="h-4.5 w-4.5" />}
            label="Attendance"
            headline="Present · Physics, 18 March 6:04 PM"
            detail="Scanned in · marked by Mr. N. Perera · SMS sent to you" />
          
          <ProofRow
            icon={<GraduationCapIcon className="h-4.5 w-4.5" />}
            label="Latest result"
            headline="Physics Model Paper 3 — 78 / 100"
            detail="Class average 64 · published by the teacher 15 March" />
          
          <ProofRow
            icon={<BanknoteIcon className="h-4.5 w-4.5" />}
            label="March fees"
            headline="LKR 6,200 paid to Sarasavi Academy"
            detail="Settled to the institute on 4 March · receipt TH-3F91" />
          
        </ul>

        <div className="mt-4 rounded-xl bg-surface-muted px-4 py-3">
          <p className="text-caption text-ink-soft">
            Everything shown on this site uses prototype data.
          </p>
        </div>
      </div>
    </div>);

}

function ProofRow({
  icon,
  label,
  headline,
  detail





}: {icon: React.ReactNode;label: string;headline: string;detail: string;}) {
  return (
    <li className="flex gap-3 py-4">
      <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-forest-50 text-forest">
        {icon}
      </span>
      <div className="min-w-0">
        <p className="text-caption uppercase tracking-wide text-ink-faint">{label}</p>
        <p className="mt-0.5 text-small font-medium text-ink">{headline}</p>
        <p className="mt-0.5 text-caption text-ink-soft">{detail}</p>
      </div>
    </li>);

}