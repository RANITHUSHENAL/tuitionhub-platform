import React from 'react';
import { Link } from 'react-router-dom';
import { Badge, PaymentBadge } from '../ui/Badge';
import { LinkButton } from '../ui/Button';
import { SectionHeading } from './SectionHeading';

export function ParentSection() {
  return (
    <section className="border-b border-line/60">
      <div className="mx-auto max-w-[1200px] px-5 py-16 lg:px-8 lg:py-20">
        <SectionHeading
          eyebrow="For parents"
          title="Stay informed about your child's tuition"
          body="Without chasing teachers or institutes for updates. One evening view: the next class, whether they have been going, the latest mark, and what you owe — for every child."
          action={
          <LinkButton to="/auth?mode=create&role=parent" variant="secondary">
              Create a parent account
            </LinkButton>
          } />
        

        <div className="mt-10 grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
          <div className="overflow-hidden rounded-2xl border border-line bg-white shadow-card">
            <div className="flex items-center justify-between gap-4 border-b border-line px-6 py-5">
              <div>
                <p className="text-small text-ink-soft">Good evening, Mrs. Perera</p>
                <p className="mt-0.5 text-h3 text-ink">Kasun · Grade 12 · A/L 2027</p>
              </div>
              <Badge tone="neutral">Switch child</Badge>
            </div>

            <div className="grid gap-px bg-line sm:grid-cols-2">
              <Tile
                label="Next class"
                value="Physics"
                detail="Tomorrow · 6:00 PM · Nugegoda Hall B" />
              
              <Tile
                label="Attendance this term"
                value="92%"
                detail="22 of 24 sessions attended" />
              
              <Tile
                label="Latest result"
                value="78 / 100"
                detail="Physics Model Paper 3 · class average 64" />
              
              <Tile
                label="March fees"
                value="LKR 6,200"
                detail="Paid on 3 March · receipt available"
                badge={<PaymentBadge status="paid" />} />
              
            </div>

            <div className="border-t border-line px-6 py-5">
              <p className="text-caption uppercase tracking-wide text-ink-faint">
                Latest announcement
              </p>
              <p className="mt-1.5 text-small text-ink">
                “Saturday class postponed to Sunday.”
              </p>
              <p className="mt-1 text-caption text-ink-soft">
                Sarasavi Academy · sent to app and SMS · 2 hours ago
              </p>
            </div>
          </div>

          <div className="space-y-5">
            <Point
              title="Visibility, not surveillance"
              body="Parents see attendance, marks and payments for the classes they pay for. The product is built to reduce arguments about money, not to track a teenager's day." />
            
            <Point
              title="Works without a smartphone"
              body="Every attendance and payment event can go out as an SMS, so a parent who does not use apps still knows." />
            
            <Point
              title="Every rupee accounted for"
              body="Each payment shows the class it covers, the institute that received it, and a reference you can quote on the phone." />
            
            <Point
              title="One family, several children"
              body="A verified guardian can link every child — even at different institutes — and pay each invoice to the right place." />
            
            <Link
              to="/parent/fees"
              className="inline-block text-small font-medium text-forest underline underline-offset-4">
              
              See how a fee payment works (sign-in) →
            </Link>
          </div>
        </div>
      </div>
    </section>);

}

function Tile({
  label,
  value,
  detail,
  badge





}: {label: string;value: string;detail: string;badge?: React.ReactNode;}) {
  return (
    <div className="bg-white px-6 py-5">
      <div className="flex items-center justify-between gap-3">
        <p className="text-caption uppercase tracking-wide text-ink-faint">{label}</p>
        {badge}
      </div>
      <p className="mt-1.5 text-h2 text-ink">{value}</p>
      <p className="mt-1 text-small text-ink-soft">{detail}</p>
    </div>);

}

function Point({ title, body }: {title: string;body: string;}) {
  return (
    <div>
      <h3 className="text-h3 text-ink">{title}</h3>
      <p className="mt-1.5 text-small text-ink-soft">{body}</p>
    </div>);

}