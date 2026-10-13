import React from 'react';
import { Navigate } from 'react-router-dom';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { PaymentBadge } from '../../components/ui/Badge';
import { useTeacherScope } from '../../hooks/useTeacherScope';
import { independentPayments } from '../../data/teaching';
import { lkr } from '../../utils/format';

/** Independent teachers only — institute teachers never see fees. */
export function TeacherPayments() {
  const { isIndependent } = useTeacherScope();
  if (!isIndependent) return <Navigate to="/teacher" replace />;

  const paid = independentPayments.filter((p) => p.status === 'paid').reduce((s, p) => s + p.amount, 0);
  const due = independentPayments.filter((p) => p.status !== 'paid').reduce((s, p) => s + p.amount, 0);

  return (
    <TeacherShell title="Payments" subtitle="Fees for the classes you run yourself">
      <div className="grid max-w-4xl gap-6">
        <dl className="grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2">
          <div className="bg-white px-6 py-5">
            <dt className="text-caption text-ink-faint">Collected this month</dt>
            <dd className="mt-1 text-h1 text-ink">{lkr(paid)}</dd>
            <p className="mt-1 text-caption text-ink-soft">Settled to your bank account the next working day</p>
          </div>
          <div className="bg-white px-6 py-5">
            <dt className="text-caption text-ink-faint">Still due</dt>
            <dd className="mt-1 text-h1 text-ink">{lkr(due)}</dd>
            <p className="mt-1 text-caption text-ink-soft">Guardians get a reminder 3 days before the due date</p>
          </div>
        </dl>
        <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
          <ul className="divide-y divide-line">
            {independentPayments.map((p) =>
            <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                <div>
                  <p className="text-small font-medium text-ink">{p.student}</p>
                  <p className="text-caption text-ink-soft">
                    {p.className} · {p.month}
                  </p>
                </div>
                <div className="flex items-center gap-3">
                  <span className="text-small tabular-nums text-ink">{lkr(p.amount)}</span>
                  <PaymentBadge status={p.status} />
                </div>
              </li>
            )}
          </ul>
        </section>
      </div>
    </TeacherShell>);

}