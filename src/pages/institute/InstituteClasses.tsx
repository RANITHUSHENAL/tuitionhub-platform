import React, { useState } from 'react';
import { PlusIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge, ClassStatusBadge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { Label, Select, TextInput } from '../../components/ui/Field';
import { institute, instituteClasses } from '../../data/institute';
import { lkr, percent } from '../../utils/format';

export function InstituteClasses() {
  const [open, setOpen] = useState(false);
  const [listed, setListed] = useState<Record<string, boolean>>(
    Object.fromEntries(instituteClasses.map((c) => [c.id, c.listed]))
  );

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Classes"
      subtitle="Schedules, fees, teachers and what is visible in the marketplace"
      actions={
      <Button size="sm" onClick={() => setOpen(true)}>
          <PlusIcon className="h-4 w-4" />
          New class
        </Button>
      }>
      
      <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
        <table className="w-full min-w-[46rem] text-left">
          <thead className="border-b border-line bg-surface-muted">
            <tr className="text-caption uppercase tracking-wide text-ink-faint">
              <th scope="col" className="px-6 py-3 font-medium">Class</th>
              <th scope="col" className="px-6 py-3 font-medium">Schedule</th>
              <th scope="col" className="px-6 py-3 font-medium">Enrolment</th>
              <th scope="col" className="px-6 py-3 font-medium">Monthly fee</th>
              <th scope="col" className="px-6 py-3 font-medium">Status</th>
              <th scope="col" className="px-6 py-3 font-medium">Public listing</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {instituteClasses.map((c) => {
              const fill = percent(c.enrolled, c.capacity);
              return (
                <tr key={c.id} className="text-small">
                  <td className="px-6 py-4">
                    <p className="font-medium text-ink">{c.name}</p>
                    <p className="text-caption text-ink-soft">{c.teacher}</p>
                  </td>
                  <td className="px-6 py-4 text-ink-soft">{c.schedule}</td>
                  <td className="px-6 py-4">
                    <p className="text-ink">
                      {c.enrolled} / {c.capacity}
                    </p>
                    <div className="mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-surface-sunk">
                      <div
                        className={`h-full rounded-full ${fill >= 95 ? 'bg-clay' : 'bg-forest-400'}`}
                        style={{ width: `${fill}%` }} />
                      
                    </div>
                  </td>
                  <td className="px-6 py-4 text-ink">{lkr(c.fee)}</td>
                  <td className="px-6 py-4">
                    <ClassStatusBadge status={c.status} />
                  </td>
                  <td className="px-6 py-4">
                    <button
                      role="switch"
                      aria-checked={listed[c.id]}
                      aria-label={`List ${c.name} publicly`}
                      onClick={() => setListed((l) => ({ ...l, [c.id]: !l[c.id] }))}
                      className={`h-6 w-11 rounded-full border transition-colors duration-150 ease-out ${
                      listed[c.id] ? 'border-forest bg-forest' : 'border-line bg-surface-sunk'}`
                      }>
                      
                      <span
                        className={`block h-5 w-5 rounded-full bg-white transition-transform duration-150 ease-out ${
                        listed[c.id] ? 'translate-x-5' : 'translate-x-0.5'}`
                        } />
                      
                    </button>
                  </td>
                </tr>);

            })}
          </tbody>
        </table>
      </section>

      <p className="mt-4 max-w-2xl text-caption text-ink-soft">
        Listings are attributed to {institute.name}. Teacher names appear publicly only
        when you switch them on for that class, under Settings → Public profile.
      </p>

      <Modal open={open} onClose={() => setOpen(false)} title="New class">
        <div className="space-y-4">
          <div>
            <Label htmlFor="cname">Class name</Label>
            <TextInput id="cname" placeholder="A/L Biology — Theory" />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="cteacher">Teacher</Label>
              <Select id="cteacher">
                <option>Mr. R. Jayasinghe</option>
                <option>Mr. N. Perera</option>
                <option>Mrs. S. Fernando</option>
              </Select>
            </div>
            <div>
              <Label htmlFor="cfee">Monthly fee (LKR)</Label>
              <TextInput id="cfee" type="number" defaultValue={3000} />
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <Label htmlFor="cday">Day</Label>
              <Select id="cday">
                <option>Saturday</option>
                <option>Sunday</option>
                <option>Tuesday</option>
              </Select>
            </div>
            <div>
              <Label htmlFor="ctime">Start time</Label>
              <TextInput id="ctime" type="time" defaultValue="08:00" />
            </div>
          </div>
          <div className="flex items-center justify-between rounded-xl border border-line px-4 py-3">
            <div>
              <p className="text-small font-medium text-ink">List in the marketplace</p>
              <p className="text-caption text-ink-soft">Parents can find and enrol in this class</p>
            </div>
            <Badge tone="success">On</Badge>
          </div>
          <div className="flex gap-2 pt-1">
            <Button className="flex-1" onClick={() => setOpen(false)}>
              Create class
            </Button>
            <Button variant="secondary" onClick={() => setOpen(false)}>
              Cancel
            </Button>
          </div>
        </div>
      </Modal>
    </AppShell>);

}