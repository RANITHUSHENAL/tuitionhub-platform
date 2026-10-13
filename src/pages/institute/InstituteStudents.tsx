import React, { useState } from 'react';
import { DownloadIcon, SearchIcon, UploadIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge, PaymentBadge } from '../../components/ui/Badge';
import { Avatar } from '../../components/ui/Avatar';
import { Button } from '../../components/ui/Button';
import { EmptyState } from '../../components/ui/States';
import { institute, roster } from '../../data/institute';
import { initialsOf } from '../../utils/format';

export function InstituteStudents() {
  const [query, setQuery] = useState('');
  const rows = roster.filter((r) =>
  `${r.name} ${r.studentId} ${r.guardian}`.toLowerCase().includes(query.toLowerCase())
  );

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Students"
      subtitle="A/L Combined Mathematics — Theory · 84 enrolled"
      actions={
      <div className="flex gap-2">
          <Button variant="secondary" size="sm">
            <UploadIcon className="h-4 w-4" />
            Bulk upload
          </Button>
          <Button variant="secondary" size="sm">
            <DownloadIcon className="h-4 w-4" />
            Export
          </Button>
        </div>
      }>
      
      <div className="mb-4 flex max-w-sm items-center gap-2.5 rounded-xl border border-line bg-white px-3.5 py-2.5">
        <SearchIcon className="h-4.5 w-4.5 shrink-0 text-ink-faint" aria-hidden="true" />
        <input
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Search name, student ID or guardian"
          aria-label="Search students"
          className="w-full bg-transparent text-small text-ink placeholder:text-ink-faint focus:outline-none" />
        
      </div>

      {rows.length === 0 ?
      <EmptyState
        icon={<SearchIcon className="h-5 w-5" />}
        title="No students match"
        body="Check the spelling, or search by student ID instead."
        action={<Button variant="secondary" onClick={() => setQuery('')}>Clear search</Button>} /> :


      <section className="overflow-x-auto rounded-2xl border border-line bg-white shadow-card">
          <table className="w-full min-w-[46rem] text-left">
            <thead className="border-b border-line bg-surface-muted">
              <tr className="text-caption uppercase tracking-wide text-ink-faint">
                <th scope="col" className="px-6 py-3 font-medium">Student</th>
                <th scope="col" className="px-6 py-3 font-medium">Student ID</th>
                <th scope="col" className="px-6 py-3 font-medium">Guardian</th>
                <th scope="col" className="px-6 py-3 font-medium">Attendance</th>
                <th scope="col" className="px-6 py-3 font-medium">This month's fee</th>
                <th scope="col" className="px-6 py-3 font-medium">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {rows.map((r) =>
            <tr key={r.id} className="text-small">
                  <td className="px-6 py-4">
                    <div className="flex items-center gap-3">
                      <Avatar initials={initialsOf(r.name)} size="sm" />
                      <div>
                        <p className="font-medium text-ink">{r.name}</p>
                        <p className="text-caption text-ink-soft">{r.grade}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-6 py-4 text-ink-soft">{r.studentId}</td>
                  <td className="px-6 py-4 text-ink-soft">{r.guardian}</td>
                  <td className="px-6 py-4">
                    <Badge tone={r.attendance >= 85 ? 'success' : r.attendance >= 70 ? 'warn' : 'danger'}>
                      {r.attendance}%
                    </Badge>
                  </td>
                  <td className="px-6 py-4">
                    <PaymentBadge status={r.feeStatus} />
                  </td>
                  <td className="px-6 py-4">
                    <Button variant="ghost" size="sm">
                      View
                    </Button>
                  </td>
                </tr>
            )}
            </tbody>
          </table>
        </section>
      }

      <p className="mt-4 text-caption text-ink-soft">
        Students with attendance below 70% and an unpaid fee are the ones worth a call —
        both columns are visible here so the office does not need two systems.
      </p>
    </AppShell>);

}