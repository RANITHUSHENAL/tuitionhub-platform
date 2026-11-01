import React from 'react';
import { DownloadIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { instituteNav } from '../../components/app/navConfig';
import { Badge, PaymentBadge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { dailyPayments, institute, instituteStats, roster } from '../../data/institute';
import { DEMO_PLATFORM_FEE, demoDailyTotals } from '../../utils/demoFees';
import { lkr } from '../../utils/format';

export function InstituteFees() {
  const unpaid = roster.filter((r) => r.feeStatus !== 'paid');

  return (
    <AppShell
      role="Institute"
      accountName={institute.name}
      accountDetail={institute.branch}
      initials="SA"
      nav={instituteNav}
      title="Fees & payments"
      subtitle="What parents paid and what is still outstanding"
      actions={
      <Button variant="secondary" size="sm">
          <DownloadIcon className="h-4 w-4" />
          Reconciliation (Excel)
        </Button>
      }>
      
      <div className="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
        <div className="space-y-6">
          <section className="grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-2">
            <Metric label="Collected this month" value={lkr(instituteStats.collectedThisMonth)} detail="1,246 payments" />
            <Metric label="Outstanding" value={lkr(instituteStats.outstanding)} detail="38 students" />
          </section>

          {/* C3: amounts owed to the institute are shown without any payout or settlement date. */}
          <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <div className="flex items-center justify-between gap-3 border-b border-line px-6 py-4">
              <h2 className="text-h3 text-ink">Payments by day</h2>
              <Badge tone="neutral">Sample data</Badge>
            </div>
            <table className="w-full min-w-[40rem] text-left">
              <thead className="border-b border-line bg-surface-muted">
                <tr className="text-caption uppercase tracking-wide text-ink-faint">
                  <th scope="col" className="px-6 py-3 font-medium">Date</th>
                  <th scope="col" className="px-6 py-3 font-medium">Payments</th>
                  <th scope="col" className="px-6 py-3 font-medium">Paid by parents</th>
                  <th scope="col" className="px-6 py-3 font-medium">TuitionHub fee</th>
                  <th scope="col" className="px-6 py-3 font-medium">Owed to institute</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line text-small">
                {dailyPayments.map((d) => {
                  const totals = demoDailyTotals(d.payments, d.owedToInstitute);
                  return (
                    <tr key={d.id}>
                      <td className="px-6 py-4 text-ink">{d.date}</td>
                      <td className="px-6 py-4 text-ink-soft">{d.payments}</td>
                      <td className="px-6 py-4 text-ink-soft">{lkr(totals.paidByParents)}</td>
                      <td className="px-6 py-4 text-ink-soft">{lkr(totals.platformFee)}</td>
                      <td className="px-6 py-4 font-medium text-ink">{lkr(totals.owedToInstitute)}</td>
                    </tr>);

                })}
              </tbody>
            </table>
            <p className="border-t border-line px-6 py-3 text-caption text-ink-faint">
              Demo illustration: a flat {lkr(DEMO_PLATFORM_FEE)} TuitionHub fee per payment, on top
              of the class fee. Amounts owed are not payouts.
            </p>
          </section>

          <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <h2 className="border-b border-line px-6 py-4 text-h3 text-ink">
              Needs follow-up
            </h2>
            <ul className="divide-y divide-line">
              {unpaid.map((r) =>
              <li key={r.id} className="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                  <div>
                    <p className="text-small font-medium text-ink">{r.name}</p>
                    <p className="text-caption text-ink-soft">
                      {r.studentId} · guardian {r.guardian}
                    </p>
                  </div>
                  <div className="flex items-center gap-3">
                    <PaymentBadge status={r.feeStatus} />
                    <Button variant="secondary" size="sm">
                      Send reminder
                    </Button>
                  </div>
                </li>
              )}
            </ul>
          </section>
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">How the money moves</h2>
            <ol className="mt-4 space-y-4 text-small text-ink-soft">
              <Step n={1} title="Parent pays">
                The class fee plus the TuitionHub fee, shown separately before paying.
              </Step>
              <Step n={2} title="Recorded against the enrolment">
                The payment is tied to one student, one class, one month.
              </Step>
              <Step n={3} title="Owed to your institute">
                The class fee is recorded as owed to your institute. The TuitionHub fee
                is kept separate.
              </Step>
              <Step n={4} title="Reconciled">
                Daily report of in, out, pending and refunded — exportable for your
                accountant.
              </Step>
            </ol>
          </section>
        </aside>
      </div>
    </AppShell>);

}

function Metric({ label, value, detail }: {label: string;value: string;detail: string;}) {
  return (
    <div className="bg-white px-5 py-5">
      <p className="text-caption uppercase tracking-wide text-ink-faint">{label}</p>
      <p className="mt-1.5 text-h2 text-ink">{value}</p>
      <p className="mt-1 text-caption text-ink-soft">{detail}</p>
    </div>);

}

function Step({ n, title, children }: {n: number;title: string;children: React.ReactNode;}) {
  return (
    <li className="flex gap-3">
      <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-forest-50 text-caption font-semibold text-forest">
        {n}
      </span>
      <span>
        <span className="block text-small font-medium text-ink">{title}</span>
        {children}
      </span>
    </li>);

}