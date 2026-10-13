import React, { useState } from 'react';
import { AlertTriangleIcon, DownloadIcon, ShieldCheckIcon } from 'lucide-react';
import { AppShell } from '../../components/app/AppShell';
import { parentNav } from '../../components/app/navConfig';
import { Badge, PaymentBadge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Modal } from '../../components/ui/DetailPanel';
import { ErrorState, Toast } from '../../components/ui/States';
import { ChoiceGroup } from '../../components/ui/Field';
import { invoices, parentProfile } from '../../data/people';
import type { Invoice } from '../../types';
import { lkr } from '../../utils/format';

type PayStep = 'review' | 'processing' | 'done';

export function ParentFees() {
  const [active, setActive] = useState<Invoice | null>(null);
  const [step, setStep] = useState<PayStep>('review');
  const [method, setMethod] = useState<'card' | 'bank'>('card');
  const [toast, setToast] = useState(false);
  const [paid, setPaid] = useState<string[]>([]);

  const outstanding = invoices.
  filter((i) => (i.status === 'pending' || i.status === 'failed') && !paid.includes(i.id)).
  reduce((sum, i) => sum + i.amount + i.platformFee, 0);

  const open = (inv: Invoice) => {
    setActive(inv);
    setStep('review');
  };

  const pay = () => {
    setStep('processing');
    window.setTimeout(() => setStep('done'), 1200);
  };

  const finish = () => {
    if (active) setPaid((p) => [...p, active.id]);
    setActive(null);
    setToast(true);
  };

  return (
    <AppShell
      role="Parent"
      accountName={parentProfile.name}
      accountDetail="2 children · Nugegoda"
      initials="CP"
      nav={parentNav}
      title="Fees & payments"
      subtitle="Every invoice shows the class it covers and the institute it is paid to."
      actions={
      <Button variant="secondary" size="sm">
          <DownloadIcon className="h-4 w-4" />
          Export history
        </Button>
      }>
      
      <div className="grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
        <div className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <p className="text-caption uppercase tracking-wide text-ink-faint">
                  Outstanding this month
                </p>
                <p className="mt-1 text-display text-ink">{lkr(outstanding)}</p>
                <p className="mt-1 text-small text-ink-soft">
                  Across {invoices.filter((i) => i.status === 'pending' || i.status === 'failed').length} invoices ·
                  April 2026
                </p>
              </div>
              <div className="flex gap-2.5 rounded-xl bg-surface-muted px-4 py-3">
                <ShieldCheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-forest" aria-hidden="true" />
                <p className="max-w-xs text-caption text-ink-soft">
                  A 2% platform fee is shown separately before you confirm — the
                  institute receives the full class fee.
                </p>
              </div>
            </div>
          </section>

          {invoices.some((i) => i.status === 'failed') ?
          <ErrorState
            title="A February payment failed"
            body="The card was declined by the bank and no money left your account. Retry with another method, or pay at the institute and ask them to mark it received."
            action={
            <Button
              size="sm"
              onClick={() => open(invoices.find((i) => i.status === 'failed') as Invoice)}>
              
                  Retry payment
                </Button>
            } /> :

          null}

          <section className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <h2 className="border-b border-line px-6 py-4 text-h3 text-ink">
              Invoices
            </h2>
            <ul className="divide-y divide-line">
              {invoices.map((inv) => {
                const isPaid = paid.includes(inv.id);
                return (
                  <li key={inv.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                    <div className="min-w-0">
                      <p className="text-small font-medium text-ink">{inv.className}</p>
                      <p className="text-caption text-ink-soft">
                        {inv.month} · {inv.institute}
                        {inv.reference ? ` · ref ${inv.reference}` : ''}
                      </p>
                      {inv.paidOn ?
                      <p className="text-caption text-ink-faint">
                          Paid {inv.paidOn} · {inv.method}
                        </p> :
                      null}
                    </div>
                    <div className="flex items-center gap-4">
                      <div className="text-right">
                        <p className="text-small font-medium text-ink">{lkr(inv.amount)}</p>
                        <p className="text-caption text-ink-faint">
                          + {lkr(inv.platformFee)} platform fee
                        </p>
                      </div>
                      <PaymentBadge status={isPaid ? 'paid' : inv.status} />
                      {(inv.status === 'pending' || inv.status === 'failed') && !isPaid ?
                      <Button size="sm" onClick={() => open(inv)}>
                          Pay
                        </Button> :

                      <Button variant="ghost" size="sm">
                          Receipt
                        </Button>
                      }
                    </div>
                  </li>);

              })}
            </ul>
          </section>
        </div>

        <aside className="space-y-6">
          <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">Payment states you may see</h2>
            <ul className="mt-4 space-y-3 text-small text-ink-soft">
              <li className="flex items-start gap-3">
                <PaymentBadge status="pending" />
                <span>Issued by the institute, not yet paid.</span>
              </li>
              <li className="flex items-start gap-3">
                <PaymentBadge status="processing" />
                <span>Sent to the bank. Confirmation can take a few minutes.</span>
              </li>
              <li className="flex items-start gap-3">
                <PaymentBadge status="paid" />
                <span>Confirmed and recorded against the class.</span>
              </li>
              <li className="flex items-start gap-3">
                <PaymentBadge status="failed" />
                <span>Declined. No money has left your account.</span>
              </li>
              <li className="flex items-start gap-3">
                <PaymentBadge status="refunded" />
                <span>Returned to your original payment method.</span>
              </li>
            </ul>
          </section>

          <section className="rounded-2xl border border-line bg-surface-muted p-6">
            <div className="flex gap-2.5">
              <AlertTriangleIcon className="mt-0.5 h-4 w-4 shrink-0 text-state-warn" aria-hidden="true" />
              <p className="text-caption text-ink-soft">
                Paying for two children at two institutes? Each invoice is paid
                separately so the money always reaches the right institute.
              </p>
            </div>
          </section>
        </aside>
      </div>

      <Modal
        open={active !== null}
        onClose={() => setActive(null)}
        title={step === 'done' ? 'Payment confirmed' : 'Pay tuition fee'}>
        
        {active ?
        <div className="space-y-5">
            <div className="rounded-xl border border-line px-4 py-3">
              <p className="text-small font-medium text-ink">{active.className}</p>
              <p className="text-caption text-ink-soft">
                {active.month} · {active.institute} · for Kasun Perera
              </p>
            </div>

            {step === 'review' ?
          <>
                <dl className="space-y-2 text-small">
                  <Row label="Class fee" value={lkr(active.amount)} />
                  <Row label="Platform fee (2%)" value={lkr(active.platformFee)} />
                  <Row
                label="Total"
                value={lkr(active.amount + active.platformFee)}
                strong />
              
                </dl>
                <div>
                  <p className="mb-2 text-small font-medium text-ink">Payment method</p>
                  <ChoiceGroup
                name="Payment method"
                value={method}
                onChange={setMethod}
                options={[
                { value: 'card', label: 'Card ending 4417', description: 'Saved card' },
                { value: 'bank', label: 'Online banking', description: 'Redirects to your bank' }]
                } />
              
                </div>
                <Button className="w-full" onClick={pay}>
                  Pay {lkr(active.amount + active.platformFee)}
                </Button>
                <p className="text-caption text-ink-faint">
                  You will receive a receipt in the app and by SMS. Prototype only — no
                  real payment is taken.
                </p>
              </> :
          null}

            {step === 'processing' ?
          <div className="flex flex-col items-center py-6 text-center">
                <span className="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-forest" />
                <p className="mt-4 text-small font-medium text-ink">
                  Confirming with the bank
                </p>
                <p className="mt-1 text-caption text-ink-soft">
                  Do not close this window. We confirm against the gateway, not the
                  redirect.
                </p>
              </div> :
          null}

            {step === 'done' ?
          <>
                <div className="rounded-xl bg-state-successBg px-4 py-4">
                  <p className="text-small font-medium text-state-success">
                    {lkr(active.amount + active.platformFee)} paid to {active.institute}
                  </p>
                  <p className="mt-1 text-caption text-ink-soft">
                    Reference TH-5A18-2026 · settles to the institute on the next
                    working day.
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button className="flex-1" onClick={finish}>
                    Done
                  </Button>
                  <Button variant="secondary" onClick={finish}>
                    View receipt
                  </Button>
                </div>
              </> :
          null}
          </div> :
        null}
      </Modal>

      <Toast
        open={toast}
        message="Payment recorded"
        detail="A receipt has been sent to your phone and saved in payment history."
        onClose={() => setToast(false)} />
      
    </AppShell>);

}

function Row({ label, value, strong }: {label: string;value: string;strong?: boolean;}) {
  return (
    <div className="flex items-center justify-between">
      <dt className={strong ? 'font-medium text-ink' : 'text-ink-soft'}>{label}</dt>
      <dd className={strong ? 'font-semibold text-ink' : 'text-ink'}>{value}</dd>
    </div>);

}