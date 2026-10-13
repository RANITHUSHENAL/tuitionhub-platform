import React, { useState } from 'react';
import { CheckIcon, XIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Avatar } from '../../components/ui/Avatar';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Toast } from '../../components/ui/States';
import { institute, instituteClasses } from '../../data/institute';
import { instituteTeam, teacherRequests, type InstituteMemberRole } from '../../data/teaching';
import { initialsOf } from '../../utils/format';

const roleTone: Record<InstituteMemberRole, 'forest' | 'info' | 'neutral' | 'success'> = {
  Owner: 'forest',
  Manager: 'info',
  Staff: 'neutral',
  Teacher: 'success'
};

export function InstituteTeachers() {
  const [requests, setRequests] = useState(teacherRequests);
  const [reviewing, setReviewing] = useState<(typeof teacherRequests)[number] | null>(null);
  const [assign, setAssign] = useState<string[]>([]);
  const [toast, setToast] = useState<string | null>(null);

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Teachers & team"
      subtitle="Who works here, and what each person can see">
      
      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <section className="overflow-x-auto rounded-2xl border border-line bg-white shadow-card">
          <table className="w-full min-w-[36rem] text-left">
            <thead className="border-b border-line bg-surface-muted">
              <tr className="text-caption uppercase tracking-wide text-ink-faint">
                <th scope="col" className="px-5 py-3 font-medium">Person</th>
                <th scope="col" className="px-5 py-3 font-medium">Role</th>
                <th scope="col" className="px-5 py-3 font-medium">Can access</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line text-small">
              {instituteTeam.map((m) =>
              <tr key={m.id}>
                  <td className="px-5 py-3.5">
                    <span className="flex items-center gap-3">
                      <Avatar initials={initialsOf(m.name)} size="sm" />
                      <span>
                        <span className="block font-medium text-ink">{m.name}</span>
                        <span className="block text-caption text-ink-soft">
                          {m.subject ? `${m.subject} · ${m.classes} ${m.classes === 1 ? 'class' : 'classes'}` : '—'}
                        </span>
                      </span>
                    </span>
                  </td>
                  <td className="px-5 py-3.5">
                    <Badge tone={roleTone[m.role]}>{m.role}</Badge>
                  </td>
                  <td className="px-5 py-3.5 text-ink-soft">{m.access}</td>
                </tr>
              )}
            </tbody>
          </table>
        </section>

        <aside className="space-y-4">
          <section className="rounded-2xl border border-line bg-white p-5 shadow-card">
            <div className="flex items-center justify-between">
              <h2 className="text-h3 text-ink">Teacher requests</h2>
              {requests.length ? <Badge tone="info">{requests.length} new</Badge> : null}
            </div>
            {requests.length ?
            <ul className="mt-3 space-y-3">
                {requests.map((r) =>
              <li key={r.id} className="rounded-xl border border-line p-3.5">
                    <p className="text-small font-medium text-ink">{r.name}</p>
                    <p className="text-caption text-ink-soft">
                      {r.detail} · {r.requested}
                    </p>
                    <p className="mt-1.5 text-caption italic text-ink-soft">“{r.note}”</p>
                    <div className="mt-3 flex gap-2">
                      <Button size="sm" onClick={() => {setReviewing(r);setAssign([]);}}>
                        Review
                      </Button>
                      <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                      setRequests((q) => q.filter((x) => x.id !== r.id));
                      setToast(`${r.name}'s request declined`);
                    }}>
                    
                        Decline
                      </Button>
                    </div>
                  </li>
              )}
              </ul> :

            <p className="mt-3 text-small text-ink-soft">No pending requests.</p>
            }
          </section>
          <p className="px-1 text-caption text-ink-faint">
            Teachers ask to join from their own TuitionHub account. Nobody is linked to your
            institute until you approve, and teachers only see the classes you assign.
          </p>
        </aside>
      </div>

      <Modal open={reviewing !== null} onClose={() => setReviewing(null)} title={`Approve ${reviewing?.name ?? ''}`}>
        <div className="space-y-4">
          <p className="text-small text-ink-soft">Choose which classes they teach. They will see only these classes and their students.</p>
          <ul className="space-y-2">
            {instituteClasses.map((c) => {
              const on = assign.includes(c.id);
              return (
                <li key={c.id}>
                  <button
                    role="checkbox"
                    aria-checked={on}
                    onClick={() => setAssign((a) => on ? a.filter((x) => x !== c.id) : [...a, c.id])}
                    className={`flex w-full items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-left transition-colors duration-150 ease-out ${
                    on ? 'border-forest bg-forest-50' : 'border-line hover:border-line-strong'}`
                    }>
                    
                    <span>
                      <span className="block text-small font-medium text-ink">{c.name}</span>
                      <span className="block text-caption text-ink-soft">{c.schedule}</span>
                    </span>
                    {on ? <CheckIcon className="h-4 w-4 text-forest" /> : null}
                  </button>
                </li>);

            })}
          </ul>
          <div className="flex gap-2">
            <Button
              className="flex-1"
              onClick={() => {
                if (!reviewing) return;
                setRequests((q) => q.filter((x) => x.id !== reviewing.id));
                setToast(`${reviewing.name} approved with ${assign.length} ${assign.length === 1 ? 'class' : 'classes'}`);
                setReviewing(null);
              }}>
              
              <CheckIcon className="h-4 w-4" />
              Approve as teacher
            </Button>
            <Button variant="secondary" onClick={() => setReviewing(null)}>
              <XIcon className="h-4 w-4" />
              Cancel
            </Button>
          </div>
        </div>
      </Modal>
      <Toast open={toast !== null} message={toast ?? ''} onClose={() => setToast(null)} />
    </AppShell>);

}