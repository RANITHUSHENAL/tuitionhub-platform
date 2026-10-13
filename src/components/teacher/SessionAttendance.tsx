import React, { useEffect, useState } from 'react';
import { CheckIcon, ClockIcon, ListChecksIcon, MinusIcon, QrCodeIcon, XIcon } from 'lucide-react';
import { Avatar } from '../ui/Avatar';
import { AttendanceBadge } from '../ui/Badge';
import { Button } from '../ui/Button';
import { SegmentedControl } from '../ui/Tabs';
import { QrCode } from '../ui/QrCode';
import { classStudents } from '../../data/teaching';
import type { AttendanceStatus } from '../../types';
import { initialsOf } from '../../utils/format';

type Stage = 'ready' | 'open' | 'closed';

const options: {value: AttendanceStatus;label: string;icon: React.ReactNode;}[] = [
{ value: 'present', label: 'Present', icon: <CheckIcon className="h-3.5 w-3.5" /> },
{ value: 'late', label: 'Late', icon: <ClockIcon className="h-3.5 w-3.5" /> },
{ value: 'absent', label: 'Absent', icon: <XIcon className="h-3.5 w-3.5" /> },
{ value: 'excused', label: 'Excused', icon: <MinusIcon className="h-3.5 w-3.5" /> }];


/**
 * Teacher's attendance for ONE session: start → dynamic QR (rotates every
 * 20s, bound to this session) → students appear live → fix anyone manually →
 * close. Unmarked students become Absent on close.
 */
export function SessionAttendance({
  sessionName,
  sessionMeta,
  initialStage = 'ready',
  onClosed





}: {sessionName: string;sessionMeta: string;initialStage?: Stage;onClosed?: (present: number, total: number) => void;}) {
  const [stage, setStage] = useState<Stage>(initialStage);
  const [mode, setMode] = useState<'qr' | 'manual'>('qr');
  const [marks, setMarks] = useState<Record<string, AttendanceStatus>>({});
  const [tick, setTick] = useState(0);
  const [secs, setSecs] = useState(20);

  useEffect(() => {
    if (stage !== 'open' || mode !== 'qr') return;
    const id = window.setInterval(() => {
      // Prototype: a student scans in every couple of seconds.
      setMarks((m) => {
        const next = classStudents.find((s) => !m[s.id]);
        return next && Math.random() > 0.35 ? { ...m, [next.id]: 'present' } : m;
      });
      setSecs((s) => {
        if (s <= 1) {
          setTick((t) => t + 1);
          return 20;
        }
        return s - 1;
      });
    }, 1000);
    return () => window.clearInterval(id);
  }, [stage, mode]);

  const total = classStudents.length;
  const present = Object.values(marks).filter((v) => v === 'present' || v === 'late').length;
  const counted = Object.keys(marks).length;

  const close = () => {
    const final = { ...marks };
    classStudents.forEach((s) => {
      if (!final[s.id]) final[s.id] = 'absent';
    });
    setMarks(final);
    setStage('closed');
    onClosed?.(Object.values(final).filter((v) => v === 'present' || v === 'late').length, total);
  };

  return (
    <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
      <header className="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
        <div className="min-w-0">
          <h2 className="truncate text-h3 text-ink">{sessionName}</h2>
          <p className="text-caption text-ink-soft">{sessionMeta}</p>
        </div>
        {stage === 'open' ?
        <div className="flex flex-wrap items-center gap-2">
            <SegmentedControl
            ariaLabel="Attendance method"
            value={mode}
            onChange={setMode}
            options={[
            { value: 'qr', label: 'QR', icon: <QrCodeIcon className="h-4 w-4" /> },
            { value: 'manual', label: 'Manual', icon: <ListChecksIcon className="h-4 w-4" /> }]
            } />
          
            <Button size="sm" onClick={close}>
              Close attendance
            </Button>
          </div> :
        null}
      </header>

      {stage === 'ready' ?
      <div className="flex flex-col items-start gap-4 px-5 py-8 sm:flex-row sm:items-center sm:justify-between">
          <p className="max-w-md text-small text-ink-soft">
            Start when students arrive. A QR appears for the hall screen; it only works for
            this session and changes every 20 seconds.
          </p>
          <Button onClick={() => setStage('open')}>
            <QrCodeIcon className="h-4 w-4" />
            Start attendance
          </Button>
        </div> :
      null}

      {stage === 'open' && mode === 'qr' ?
      <div className="grid gap-6 p-5 md:grid-cols-[auto_1fr]">
          <div className="flex flex-col items-center">
            <div className="rounded-2xl border border-line p-3">
              <QrCode seed={`${sessionName}-${tick}`} size={196} label="Session attendance QR code" />
            </div>
            <div className="mt-3 w-full">
              <div className="h-1 overflow-hidden rounded-full bg-surface-sunk">
                <div
                className="h-full rounded-full bg-forest transition-[width] duration-1000 ease-linear"
                style={{ width: `${secs / 20 * 100}%` }} />
              
              </div>
              <p className="mt-1.5 text-center text-caption text-ink-soft">New code in {secs}s</p>
            </div>
          </div>
          <div>
            <p className="text-display leading-none text-ink">
              {present}
              <span className="text-h2 text-ink-faint"> / {total}</span>
            </p>
            <p className="mt-1 text-small text-ink-soft">scanned in so far</p>
            <ul className="mt-5 max-h-60 space-y-2 overflow-y-auto th-scroll">
              {classStudents.
            filter((s) => marks[s.id]).
            map((s) =>
            <li key={s.id} className="flex items-center justify-between gap-3">
                    <span className="flex items-center gap-2 text-small text-ink">
                      <Avatar initials={initialsOf(s.name)} size="xs" />
                      {s.name}
                    </span>
                    <AttendanceBadge status={marks[s.id]} />
                  </li>
            )}
            </ul>
            <p className="mt-4 text-caption text-ink-faint">
              Someone without a phone? Switch to Manual — you can mix both in one session.
            </p>
          </div>
        </div> :
      null}

      {stage === 'open' && mode === 'manual' ?
      <div>
          <div className="flex items-center justify-between border-b border-line px-5 py-2.5">
            <p className="text-caption text-ink-soft">
              {counted} of {total} marked
            </p>
            <Button
            variant="ghost"
            size="sm"
            onClick={() =>
            setMarks((m) =>
            Object.fromEntries(classStudents.map((s) => [s.id, m[s.id] ?? 'present' as AttendanceStatus]))
            )
            }>
            
              Mark the rest present
            </Button>
          </div>
          <ul className="divide-y divide-line">
            {classStudents.map((s) =>
          <li key={s.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                <span className="flex items-center gap-3 text-small font-medium text-ink">
                  <Avatar initials={initialsOf(s.name)} size="sm" />
                  {s.name}
                </span>
                <div className="flex gap-1">
                  {options.map((o) => {
                const active = marks[s.id] === o.value;
                return (
                  <button
                    key={o.value}
                    onClick={() => setMarks((m) => ({ ...m, [s.id]: o.value }))}
                    aria-pressed={active}
                    aria-label={`${o.label}: ${s.name}`}
                    className={`inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-caption font-medium transition-colors duration-150 ease-out ${
                    active ? 'border-forest bg-forest text-white' : 'border-line text-ink-soft hover:border-line-strong hover:text-ink'}`
                    }>
                    
                        {o.icon}
                        <span className="hidden sm:inline">{o.label}</span>
                      </button>);

              })}
                </div>
              </li>
          )}
          </ul>
        </div> :
      null}

      {stage === 'closed' ?
      <div className="p-5">
          <div className="flex flex-wrap items-center gap-4 rounded-xl bg-state-successBg px-4 py-3">
            <CheckIcon className="h-5 w-5 text-state-success" aria-hidden="true" />
            <p className="flex-1 text-small text-ink">
              Attendance closed · <strong>{present} of {total}</strong> present. Guardians of absent
              students get an SMS.
            </p>
            <Button variant="secondary" size="sm" onClick={() => setStage('open')}>
              Reopen to correct
            </Button>
          </div>
          <ul className="mt-4 grid gap-2 sm:grid-cols-2">
            {classStudents.map((s) =>
          <li key={s.id} className="flex items-center justify-between gap-3 rounded-xl border border-line px-3 py-2">
                <span className="truncate text-small text-ink">{s.name}</span>
                <AttendanceBadge status={marks[s.id] ?? 'absent'} />
              </li>
          )}
          </ul>
        </div> :
      null}
    </section>);

}