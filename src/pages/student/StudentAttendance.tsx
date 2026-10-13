import React, { useState } from 'react';
import { CheckCircle2Icon, ScanLineIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { studentNav } from '../../components/app/navConfig';
import { AttendanceBadge, Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Tabs } from '../../components/ui/Tabs';
import { useAuth } from '../../contexts/AuthContext';
import { attendanceRecords } from '../../data/people';

const byClass = [
{ name: 'A/L Physics', attended: 9, total: 10 },
{ name: 'Combined Maths', attended: 8, total: 8 },
{ name: 'A/L Chemistry', attended: 5, total: 6 }];


export function StudentAttendance() {
  const { user } = useAuth();
  const [filter, setFilter] = useState('all');
  const [scanOpen, setScanOpen] = useState(false);
  const [scanned, setScanned] = useState(false);

  const attended = byClass.reduce((s, c) => s + c.attended, 0);
  const total = byClass.reduce((s, c) => s + c.total, 0);
  const overall = Math.round(attended / total * 100);

  const rows = attendanceRecords.filter((r) => {
    if (filter === 'missed') return r.status === 'absent' || r.status === 'late';
    if (filter === 'all') return true;
    return r.className === filter;
  });

  return (
    <AppShell
      role="Student"
      accountName={user?.name ?? 'Kasun Perera'}
      accountDetail={user?.detail ?? 'Grade 12 · A/L 2027'}
      initials={user?.initials ?? 'KP'}
      nav={studentNav}
      title="Attendance"
      subtitle="Every session, how it was recorded, and who recorded it"
      actions={
      <Button size="sm" onClick={() => {setScanned(false);setScanOpen(true);}}>
          <ScanLineIcon className="h-4 w-4" />
          Scan session QR
        </Button>
      }>
      
      <div className="grid gap-6 xl:grid-cols-[0.62fr_1.38fr]">
        <div className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <p className="text-caption uppercase tracking-wide text-ink-faint">Overall this term</p>
            <p className="mt-1.5 text-display text-ink">{overall}%</p>
            <p className="mt-1 text-small text-ink-soft">
              {attended} of {total} sessions across {byClass.length} classes
            </p>
          </section>
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">By class</h2>
            <ul className="mt-4 space-y-4">
              {byClass.map((c) => {
                const pct = Math.round(c.attended / c.total * 100);
                return (
                  <li key={c.name}>
                    <div className="flex items-center justify-between text-small">
                      <span className="text-ink">{c.name}</span>
                      <span className="text-ink-soft">
                        {c.attended}/{c.total} · {pct}%
                      </span>
                    </div>
                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                      <div className={`h-full rounded-full ${pct >= 90 ? 'bg-forest' : 'bg-state-warn'}`} style={{ width: `${pct}%` }} />
                    </div>
                  </li>);

              })}
            </ul>
          </section>
        </div>

        <section className="rounded-2xl border border-line bg-white shadow-card">
          <div className="px-6 pt-5">
            <Tabs
              ariaLabel="Session filters"
              active={filter}
              onChange={setFilter}
              items={[
              { id: 'all', label: 'All sessions' },
              { id: 'missed', label: 'Missed or late' },
              ...byClass.map((c) => ({ id: c.name, label: c.name }))]
              } />
            
          </div>
          <ul className="divide-y divide-line px-6">
            {rows.map((r) =>
            <li key={`${r.date}-${r.className}`} className="flex flex-wrap items-center justify-between gap-3 py-4">
                <div>
                  <p className="text-small font-medium text-ink">{r.className}</p>
                  <p className="text-caption text-ink-soft">
                    {r.date}
                    {r.time ? ` · ${r.time}` : ''} · {r.markedBy}
                    {r.note ? ` · ${r.note}` : ''}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  {r.method ? <Badge tone="neutral">{r.method}</Badge> : null}
                  <AttendanceBadge status={r.status} />
                </div>
              </li>
            )}
          </ul>
        </section>
      </div>

      <Modal open={scanOpen} onClose={() => setScanOpen(false)} title="Scan session QR">
        {scanned ?
        <div className="rounded-xl bg-state-successBg p-5">
            <div className="flex gap-3">
              <CheckCircle2Icon className="mt-0.5 h-5 w-5 text-forest" aria-hidden="true" />
              <div>
                <p className="text-small font-medium text-ink">Marked present · A/L Physics</p>
                <p className="mt-1 text-caption text-ink-soft">
                  Tuesday 6:02 PM · Nugegoda Hall B. Your guardian receives the usual SMS.
                </p>
              </div>
            </div>
          </div> :

        <div className="space-y-4">
            <div className="relative flex aspect-square items-center justify-center overflow-hidden rounded-2xl bg-ink">
              <div className="h-2/3 w-2/3 rounded-2xl border-2 border-white/70" />
              <p className="absolute bottom-4 text-caption text-white/80">Point at the QR on the hall screen</p>
            </div>
            <p className="text-caption text-ink-soft">
              The code changes every 20 seconds and only works during this session, so a
              photo of it forwarded on WhatsApp will not mark anyone present.
            </p>
            <Button className="w-full" onClick={() => setScanned(true)}>
              Prototype: simulate a scan
            </Button>
          </div>
        }
      </Modal>
    </AppShell>);

}