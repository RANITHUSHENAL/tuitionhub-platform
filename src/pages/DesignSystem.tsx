import React, { useState } from 'react';
import { BookOpenIcon, SearchIcon } from 'lucide-react';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import {
  ActivityStatusBadge,
  AttendanceBadge,
  Badge,
  ClassStatusBadge,
  PaymentBadge,
  VerifiedBadge } from
'../components/ui/Badge';
import { Button } from '../components/ui/Button';
import { ChoiceGroup, Label, Select, TextArea, TextInput } from '../components/ui/Field';
import { SegmentedControl, Tabs } from '../components/ui/Tabs';
import { Avatar, AvatarStack } from '../components/ui/Avatar';
import { EmptyState, ErrorState, SkeletonCard, Toast } from '../components/ui/States';
import { Modal } from '../components/ui/DetailPanel';

const colors = [
{ name: 'canvas', hex: '#E7EFE7', use: 'Page background' },
{ name: 'surface', hex: '#FFFFFF', use: 'Cards, panels' },
{ name: 'surface-muted', hex: '#F4F7F3', use: 'Inset areas' },
{ name: 'forest', hex: '#13402C', use: 'Primary action, Study surface' },
{ name: 'forest-100', hex: '#DCE9E1', use: 'Quiet emphasis, success fill' },
{ name: 'clay', hex: '#B04A2F', use: 'Accent, urgency, search CTA' },
{ name: 'ink', hex: '#1B211D', use: 'Primary text' },
{ name: 'ink-soft', hex: '#55605A', use: 'Secondary text' },
{ name: 'line', hex: '#D8E2D7', use: 'Borders, dividers' }];


const type = [
{ token: 'Display', className: 'text-display', sample: 'Know your child went.' },
{ token: 'H1', className: 'text-h1', sample: 'Find the right class' },
{ token: 'H2', className: 'text-h2', sample: 'Fees & payments' },
{ token: 'H3', className: 'text-h3', sample: 'A/L Physics — Waves' },
{ token: 'Body', className: 'text-body', sample: 'Attendance, marks and payments hang off one enrolment.' },
{ token: 'Small', className: 'text-small', sample: 'Marked by Mr. N. Perera · SMS sent' },
{ token: 'Caption', className: 'text-caption', sample: 'Prototype data · figures are illustrative' }];


export function DesignSystem() {
  const [tab, setTab] = useState('one');
  const [seg, setSeg] = useState<'map' | 'list'>('map');
  const [choice, setChoice] = useState<'public' | 'request'>('public');
  const [modal, setModal] = useState(false);
  const [toast, setToast] = useState(false);

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main className="mx-auto max-w-[1100px] space-y-10 px-5 py-10 lg:px-8">
        <header className="max-w-3xl">
          <p className="text-caption font-medium uppercase tracking-wide text-clay">
            Design system
          </p>
          <h1 className="mt-2 text-h1 text-ink">Calm, modern, trustworthy, human</h1>
          <p className="mt-3 text-body text-ink-soft">
            A refinement of the existing TuitionHub direction — pale green canvas, deep
            forest green, warm terracotta accent, charcoal text — tightened into tokens,
            a type scale that carries Sinhala and Tamil, and components with every state
            designed.
          </p>
        </header>

        <Section title="Colour">
          <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
            {colors.map((c) =>
            <div key={c.name} className="rounded-xl border border-line bg-white p-3">
                <div
                className="h-16 w-full rounded-lg border border-line"
                style={{ background: c.hex }} />
              
                <p className="mt-2.5 text-small font-medium text-ink">{c.name}</p>
                <p className="text-caption text-ink-faint">{c.hex}</p>
                <p className="mt-1 text-caption text-ink-soft">{c.use}</p>
              </div>
            )}
          </div>
        </Section>

        <Section title="Typography">
          <div className="space-y-5">
            {type.map((t) =>
            <div key={t.token} className="border-b border-line pb-4 last:border-0">
                <p className="text-caption uppercase tracking-wide text-ink-faint">
                  {t.token}
                </p>
                <p className={`mt-1 text-ink ${t.className}`}>{t.sample}</p>
              </div>
            )}
            <div className="rounded-xl bg-surface-muted p-4">
              <p className="text-small text-ink">
                සිංහල · தமிழ் · English — Inter with Noto Sans Sinhala and Noto Sans
                Tamil. Buttons and nav items are sized for the longest of the three
                labels, not the English one.
              </p>
            </div>
          </div>
        </Section>

        <Section title="Buttons">
          <div className="flex flex-wrap items-center gap-3">
            <Button>Primary</Button>
            <Button variant="accent">Accent</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="danger">Danger</Button>
            <Button disabled>Disabled</Button>
            <Button size="sm">Small</Button>
            <Button size="lg">Large</Button>
          </div>
        </Section>

        <Section title="Status states">
          <div className="grid gap-6 md:grid-cols-2">
            <StateRow title="Payment">
              <PaymentBadge status="pending" />
              <PaymentBadge status="processing" />
              <PaymentBadge status="paid" />
              <PaymentBadge status="failed" />
              <PaymentBadge status="refunded" />
            </StateRow>
            <StateRow title="Attendance">
              <AttendanceBadge status="present" />
              <AttendanceBadge status="absent" />
              <AttendanceBadge status="late" />
              <AttendanceBadge status="excused" />
            </StateRow>
            <StateRow title="Activity">
              <ActivityStatusBadge status="open" />
              <ActivityStatusBadge status="full" />
              <ActivityStatusBadge status="request-pending" />
              <ActivityStatusBadge status="joined" />
              <ActivityStatusBadge status="cancelled" />
              <ActivityStatusBadge status="completed" />
            </StateRow>
            <StateRow title="Class & trust">
              <ClassStatusBadge status="active" />
              <ClassStatusBadge status="full" />
              <ClassStatusBadge status="closed" />
              <ClassStatusBadge status="cancelled" />
              <VerifiedBadge />
            </StateRow>
          </div>
        </Section>

        <Section title="Inputs">
          <div className="grid gap-5 md:grid-cols-2">
            <div>
              <Label htmlFor="ds-text" hint="Optional">
                Text input
              </Label>
              <TextInput id="ds-text" placeholder="A/L Physics — Waves session" />
            </div>
            <div>
              <Label htmlFor="ds-select">Select</Label>
              <Select id="ds-select">
                <option>Study Together</option>
                <option>Peer Teaching</option>
              </Select>
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="ds-area">Text area</Label>
              <TextArea id="ds-area" rows={3} placeholder="What you will cover" />
            </div>
            <div className="md:col-span-2">
              <Label>Choice group</Label>
              <ChoiceGroup
                name="Visibility"
                value={choice}
                onChange={setChoice}
                options={[
                { value: 'public', label: 'Public', description: 'Anyone can join' },
                { value: 'request', label: 'Request to join', description: 'Host approves' }]
                } />
              
            </div>
          </div>
        </Section>

        <Section title="Navigation & people">
          <div className="space-y-5">
            <Tabs
              items={[
              { id: 'one', label: 'Overview' },
              { id: 'two', label: 'Discussions', count: 12 },
              { id: 'three', label: 'Activities', count: 3 }]
              }
              active={tab}
              onChange={setTab}
              ariaLabel="Example tabs" />
            
            <SegmentedControl
              ariaLabel="Example view"
              value={seg}
              onChange={setSeg}
              options={[
              { value: 'map', label: 'Map' },
              { value: 'list', label: 'List' }]
              } />
            
            <div className="flex items-center gap-4">
              <Avatar initials="KP" />
              <Avatar initials="AR" size="sm" />
              <AvatarStack
                people={[
                { initials: 'NP', name: 'Nimal' },
                { initials: 'AR', name: 'Ayesha' },
                { initials: 'TS', name: 'Thilina' },
                { initials: 'HW', name: 'Hasini' }]
                }
                total={6} />
              
              <Badge tone="neutral">Badge</Badge>
            </div>
          </div>
        </Section>

        <Section title="Feedback states">
          <div className="grid gap-5 md:grid-cols-2">
            <SkeletonCard />
            <ErrorState
              title="Payment failed"
              body="The card was declined and no money left the account."
              action={<Button size="sm">Retry payment</Button>} />
            
            <div className="md:col-span-2">
              <EmptyState
                icon={<SearchIcon className="h-5 w-5" />}
                title="No classes match these filters"
                body="Try widening the fee range or including online classes."
                action={<Button variant="secondary">Clear filters</Button>} />
              
            </div>
            <div className="flex flex-wrap gap-3 md:col-span-2">
              <Button variant="secondary" onClick={() => setModal(true)}>
                Open modal
              </Button>
              <Button variant="secondary" onClick={() => setToast(true)}>
                Show toast
              </Button>
            </div>
          </div>
        </Section>

        <Section title="Motion">
          <ul className="space-y-2 text-small text-ink-soft">
            <li>Press feedback 150ms · panels and sheets 220–280ms · nothing over 300ms.</li>
            <li>Easing cubic-bezier(0.23, 1, 0.32, 1) for entrances and exits; named properties only, never transition-all.</li>
            <li>Sheets slide from the edge they belong to; modals enter at scale 0.97, never from zero.</li>
            <li>prefers-reduced-motion disables all of it.</li>
          </ul>
        </Section>

        <Section title="Interaction patterns">
          <div className="grid gap-4 md:grid-cols-3">
            <Pattern title="Bottom sheet ↔ side panel">
              The same activity content renders as a bottom sheet on mobile and a docked
              panel beside the map on desktop — not a floating sheet dragged onto a
              large screen.
            </Pattern>
            <Pattern title="Status before action">
              Every row shows state (paid, marked, full) before it shows a button, so
              scanning does not require clicking.
            </Pattern>
            <Pattern title="Honest placeholders">
              Where the real product has nothing yet — reviews, verification — the
              surface says so instead of inventing a number.
            </Pattern>
          </div>
        </Section>
      </main>

      <Modal open={modal} onClose={() => setModal(false)} title="Example modal">
        <p className="text-small text-ink-soft">
          Used for short, decisive flows: paying an invoice, creating a class, entering
          marks. Anything longer becomes a page.
        </p>
        <Button className="mt-4 w-full" onClick={() => setModal(false)}>
          Close
        </Button>
      </Modal>

      <Toast
        open={toast}
        message="Attendance submitted"
        detail="Parents of absent students receive an SMS."
        onClose={() => setToast(false)} />
      

      <SiteFooter />
    </div>);

}

function Section({ title, children }: {title: string;children: React.ReactNode;}) {
  return (
    <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
      <div className="flex items-center gap-2">
        <BookOpenIcon className="h-4 w-4 text-forest" aria-hidden="true" />
        <h2 className="text-h2 text-ink">{title}</h2>
      </div>
      <div className="mt-5">{children}</div>
    </section>);

}

function StateRow({ title, children }: {title: string;children: React.ReactNode;}) {
  return (
    <div>
      <p className="text-caption uppercase tracking-wide text-ink-faint">{title}</p>
      <div className="mt-2 flex flex-wrap gap-2">{children}</div>
    </div>);

}

function Pattern({ title, children }: {title: string;children: React.ReactNode;}) {
  return (
    <div className="rounded-xl border border-line bg-surface-muted p-4">
      <h3 className="text-h3 text-ink">{title}</h3>
      <p className="mt-1.5 text-small text-ink-soft">{children}</p>
    </div>);

}