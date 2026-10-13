import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon, CheckIcon, MinusIcon, PlusIcon } from 'lucide-react';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import { Badge } from '../components/ui/Badge';
import { Tabs } from '../components/ui/Tabs';
import { LinkButton } from '../components/ui/Button';
import {
  architecture,
  heroCritique,
  journeys,
  naming,
  phases,
  safety,
  sitemap } from
'../data/blueprint';

const sections = [
{ id: 'product', label: 'Product' },
{ id: 'architecture', label: 'Architecture' },
{ id: 'sitemap', label: 'Sitemap' },
{ id: 'journeys', label: 'Journeys' },
{ id: 'critique', label: 'Hero critique' },
{ id: 'safety', label: 'Safety' },
{ id: 'naming', label: 'Naming' },
{ id: 'phases', label: 'Phases' }];


export function Blueprint() {
  const [tab, setTab] = useState('product');

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main className="mx-auto max-w-[1100px] px-5 py-10 lg:px-8">
        <header className="max-w-3xl">
          <p className="text-caption font-medium uppercase tracking-wide text-clay">
            Product blueprint
          </p>
          <h1 className="mt-2 text-h1 text-ink">
            One platform: discover a class, run the institute, see the money, and find
            people to learn with.
          </h1>
          <p className="mt-3 text-body text-ink-soft">
            TuitionHub is the platform Sri Lankan tuition institutes use to run their
            classes, and the place parents pay fees with full visibility on attendance
            and progress. Study is a student-owned layer on top of the same identity —
            not a second product bolted on.
          </p>
        </header>

        <div className="mt-8">
          <Tabs items={sections} active={tab} onChange={setTab} ariaLabel="Blueprint sections" />
        </div>

        <div className="mt-8">
          {tab === 'product' ? <ProductSection /> : null}
          {tab === 'architecture' ? <ArchitectureSection /> : null}
          {tab === 'sitemap' ? <SitemapSection /> : null}
          {tab === 'journeys' ? <JourneysSection /> : null}
          {tab === 'critique' ? <CritiqueSection /> : null}
          {tab === 'safety' ? <SafetySection /> : null}
          {tab === 'naming' ? <NamingSection /> : null}
          {tab === 'phases' ? <PhasesSection /> : null}
        </div>
      </main>
      <SiteFooter />
    </div>);

}

function Card({ title, children }: {title: string;children: React.ReactNode;}) {
  return (
    <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
      <h2 className="text-h3 text-ink">{title}</h2>
      <div className="mt-3 space-y-2 text-small text-ink-soft">{children}</div>
    </section>);

}

function ProductSection() {
  return (
    <div className="grid gap-5 md:grid-cols-2">
      <Card title="What TuitionHub is">
        <p>
          Four layers sharing one database and one brand: a discovery marketplace, class
          management for institutes and teachers, a payment hub, and a parent oversight
          view. The parent view is not a revenue line — it is the reason parents agree
          to pay through the platform, which is what makes the payment layer work.
        </p>
      </Card>
      <Card title="Who it serves, and how hard each one is">
        <p>
          <strong className="text-ink">Institute owner</strong> — hardest. Fears loss of
          cash-flow control, teacher poaching and disruption. Sold on admin time saved,
          not on payments.
        </p>
        <p>
          <strong className="text-ink">Parent</strong> — the buyer once payments are on.
          Sold on visibility and accountability.
        </p>
        <p>
          <strong className="text-ink">Student</strong> — the daily user. Needs the most
          polished surface, and is the only stakeholder Study is designed for.
        </p>
        <p>
          <strong className="text-ink">Teacher</strong> — end user, not buyer. Needs
          three actions in one screen: attendance, marks, announcement.
        </p>
      </Card>
      <Card title="What Study is, and is not">
        <p>
          It is a way to answer “what can I study or learn with other people right
          now?”. Activities are objects with a subject, an exam year, a time and a
          capacity. Circles are the persistent version of the same thing.
        </p>
        <p>
          It is not a feed, not a follower graph, not proximity-to-people. There is no
          scrolling surface where the question is “what are people posting today”.
        </p>
      </Card>
      <Card title="Design principles applied here">
        <p>Clarity over decoration; the payment surface is deliberately plain.</p>
        <p>
          One record, many views: attendance, marks and payments all hang off the same
          enrolment, so a parent sees the same truth the institute does.
        </p>
        <p>
          Honest placeholders: no invented traction, no certifications, no security
          badges the product has not earned.
        </p>
      </Card>
    </div>);

}

function ArchitectureSection() {
  return (
    <div className="space-y-5">
      <div className="rounded-2xl border border-line bg-white p-6 text-center shadow-card">
        <p className="text-h3 text-ink">TuitionHub</p>
        <p className="mt-1 text-caption text-ink-soft">One account, one identity, three pillars</p>
      </div>
      <div className="grid gap-5 md:grid-cols-3">
        {architecture.map((a) =>
        <section key={a.pillar} className="rounded-2xl border border-line bg-white p-6 shadow-card">
            <h2 className="text-h3 text-ink">{a.pillar}</h2>
            <p className="text-caption uppercase tracking-wide text-ink-faint">{a.subtitle}</p>
            <ul className="mt-4 space-y-2">
              {a.items.map((i) =>
            <li key={i} className="flex items-start gap-2 text-small text-ink-soft">
                  <CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-forest" aria-hidden="true" />
                  {i}
                </li>
            )}
            </ul>
          </section>
        )}
      </div>
      <Card title="Where Study can separate later">
        <p>
          Study reads the same account, enrolment and verification records as the core
          product, but writes only to its own tables (activities, participants, circles,
          messages). That boundary is what allows “TuitionHub → Study” to become
          “StudyCircle by TuitionHub” as a separate app on a shared login, without a
          migration.
        </p>
      </Card>
    </div>);

}

function SitemapSection() {
  return (
    <div className="grid gap-5 md:grid-cols-2">
      {sitemap.map((group) =>
      <section key={group.group} className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <h2 className="text-h3 text-ink">{group.group}</h2>
          <ul className="mt-3 divide-y divide-line">
            {group.routes.map((r) =>
          <li key={r.path} className="flex items-center justify-between gap-3 py-2.5">
                <span className="text-small text-ink">{r.label}</span>
                <code className="text-caption text-ink-faint">{r.path}</code>
              </li>
          )}
          </ul>
        </section>
      )}
    </div>);

}

function JourneysSection() {
  return (
    <div className="space-y-4">
      {journeys.map((j) =>
      <section key={j.id} className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h2 className="text-h3 text-ink">
              <span className="mr-2 text-ink-faint">Flow {j.id}</span>
              {j.name}
            </h2>
            <LinkButton to={j.link} variant="secondary" size="sm">
              Walk this flow
              <ArrowRightIcon className="h-4 w-4" />
            </LinkButton>
          </div>
          <ol className="mt-4 flex flex-wrap items-center gap-2">
            {j.steps.map((s, i) =>
          <li key={s} className="flex items-center gap-2">
                <span className="rounded-xl border border-line bg-surface-muted px-3 py-1.5 text-caption text-ink">
                  {s}
                </span>
                {i < j.steps.length - 1 ?
            <span className="text-caption text-ink-faint" aria-hidden="true">
                    →
                  </span> :
            null}
              </li>
          )}
          </ol>
        </section>
      )}
    </div>);

}

function CritiqueSection() {
  return (
    <div className="space-y-5">
      <div className="grid gap-5 md:grid-cols-3">
        <CritiqueList title="What is already strong" tone="success" items={heroCritique.strong} />
        <CritiqueList title="What had to change" tone="danger" items={heroCritique.change} />
        <CritiqueList title="What was missing" tone="warn" items={heroCritique.missing} />
      </div>
      <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
        <h2 className="text-h3 text-ink">Direct answers</h2>
        <dl className="mt-4 divide-y divide-line">
          {heroCritique.answers.map((a) =>
          <div key={a.q} className="py-4">
              <dt className="text-small font-medium text-ink">{a.q}</dt>
              <dd className="mt-1 text-small text-ink-soft">{a.a}</dd>
            </div>
          )}
        </dl>
      </section>
      <div className="rounded-2xl border border-line bg-surface-muted p-6">
        <p className="text-small text-ink-soft">
          The refined headline in use: <strong className="text-ink">Know your child went. Know what they scored. Know where every payment goes.</strong>{' '}
          Supporting line: find the right tuition classes, keep track of attendance and
          marks, and pay fees in one place.
        </p>
        <Link to="/" className="mt-3 inline-block text-small font-medium text-forest underline underline-offset-4">
          See the rebuilt hero →
        </Link>
      </div>
    </div>);

}

function CritiqueList({
  title,
  items,
  tone




}: {title: string;items: string[];tone: 'success' | 'danger' | 'warn';}) {
  const icon =
  tone === 'success' ?
  <CheckIcon className="h-4 w-4 text-forest" /> :
  tone === 'danger' ?
  <MinusIcon className="h-4 w-4 text-state-danger" /> :

  <PlusIcon className="h-4 w-4 text-state-warn" />;

  return (
    <section className="rounded-2xl border border-line bg-white p-6 shadow-card">
      <h2 className="text-h3 text-ink">{title}</h2>
      <ul className="mt-3 space-y-3">
        {items.map((i) =>
        <li key={i} className="flex items-start gap-2 text-small text-ink-soft">
            <span className="mt-0.5 shrink-0" aria-hidden="true">
              {icon}
            </span>
            {i}
          </li>
        )}
      </ul>
    </section>);

}

function SafetySection() {
  return (
    <div id="safety" className="grid gap-5 md:grid-cols-2">
      {safety.map((s) =>
      <Card key={s.title} title={s.title}>
          <p>{s.body}</p>
        </Card>
      )}
    </div>);

}

function NamingSection() {
  return (
    <div className="space-y-4">
      <p className="max-w-3xl text-small text-ink-soft">
        The main brand stays TuitionHub. The social-learning layer needs a name that
        says learning, survives being said out loud in Sinhala and Tamil, and never
        reads as a meet-people app.
      </p>
      <ul className="space-y-3">
        {naming.map((n) =>
        <li
          key={n.name}
          className="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-line bg-white px-5 py-4 shadow-card">
          
            <div className="min-w-0 flex-1">
              <p className="text-h3 text-ink">{n.name}</p>
              <p className="mt-1 text-small text-ink-soft">{n.note}</p>
            </div>
            <Badge
            tone={
            n.verdict === 'Strongest' || n.verdict === 'Strong' ?
            'success' :
            n.verdict === 'Avoid' ?
            'danger' :
            'warn'
            }>
            
              {n.verdict}
            </Badge>
          </li>
        )}
      </ul>
      <div className="rounded-2xl border border-line bg-surface-muted p-6">
        <p className="text-small text-ink-soft">
          Recommendation: keep <strong className="text-ink">Study</strong> as the
          in-product label while it lives inside TuitionHub, and hold{' '}
          <strong className="text-ink">StudyCircle by TuitionHub</strong> for the day it
          becomes a separate app. Naming it early forces two brands before either has
          users.
        </p>
      </div>
    </div>);

}

function PhasesSection() {
  return (
    <ol className="space-y-4">
      {phases.map((p) =>
      <li key={p.phase} className="rounded-2xl border border-line bg-white p-6 shadow-card">
          <div className="flex flex-wrap items-center gap-3">
            <Badge tone="forest">{p.phase}</Badge>
            <h2 className="text-h3 text-ink">{p.title}</h2>
          </div>
          <p className="mt-2 text-small text-ink-soft">{p.body}</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {p.surfaces.map((s) =>
          <Badge key={s}>{s}</Badge>
          )}
          </div>
        </li>
      )}
    </ol>);

}