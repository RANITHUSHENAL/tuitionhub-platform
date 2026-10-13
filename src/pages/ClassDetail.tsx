import React, { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import {
  BookmarkIcon,
  CalendarIcon,
  CheckIcon,
  ChevronRightIcon,
  MapPinIcon,
  ShieldCheckIcon,
  UsersIcon } from
'lucide-react';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import { Badge, ClassStatusBadge, VerifiedBadge } from '../components/ui/Badge';
import { Button, LinkButton } from '../components/ui/Button';
import { Modal } from '../components/ui/DetailPanel';
import { Toast, EmptyState } from '../components/ui/States';
import { ClassCardMini } from '../components/cards/ClassCard';
import { classById, classes } from '../data/classes';
import { lkr, percent } from '../utils/format';
import { useAuth } from '../contexts/AuthContext';

export function ClassDetail() {
  const { id = '' } = useParams();
  const item = classById(id);
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const { user, requireAuth, guardianState } = useAuth();
  const [joinOpen, setJoinOpen] = useState(false);
  const [saved, setSaved] = useState(false);
  const [toast, setToast] = useState<string | null>(null);

  const here = `/class/${id}`;

  // Resume the action a guest started before signing in.
  useEffect(() => {
    const intent = params.get('intent');
    if (!intent || !user) return;
    if (intent === 'enroll') setJoinOpen(true);
    if (intent === 'save') {
      setSaved(true);
      setToast('Class saved');
    }
    params.delete('intent');
    setParams(params, { replace: true });
  }, [params, setParams, user]);

  const startEnroll = () => {
    if (!requireAuth('enroll', `${here}?intent=enroll`)) return;
    setJoinOpen(true);
  };

  const toggleSave = () => {
    if (!requireAuth('save', `${here}?intent=save`)) return;
    setSaved((s) => !s);
    setToast(saved ? 'Class removed from saved' : 'Class saved');
  };

  const restricted = guardianState === 'restricted';
  const isStudent = user?.role === 'student';

  if (!item) {
    return (
      <div className="min-h-screen bg-canvas">
        <SiteHeader />
        <main className="mx-auto max-w-[900px] px-5 py-20">
          <EmptyState
            icon={<MapPinIcon className="h-5 w-5" />}
            title="Class not found"
            body="This listing may have been removed by the institute, or the link is out of date."
            action={<LinkButton to="/search">Back to search</LinkButton>} />
          
        </main>
        <SiteFooter />
      </div>);

  }

  const related = classes.filter((c) => c.id !== item.id && c.subject !== item.subject).slice(0, 3);
  const fill = percent(item.seatsTaken, item.seatsTotal);
  const canJoin = item.status === 'active';

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />

      <main className="mx-auto max-w-[1200px] px-5 py-8 lg:px-8">
        <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-caption text-ink-soft">
          <Link to="/search" className="hover:text-ink">
            Classes
          </Link>
          <ChevronRightIcon className="h-3.5 w-3.5" aria-hidden="true" />
          <span>{item.subject}</span>
          <ChevronRightIcon className="h-3.5 w-3.5" aria-hidden="true" />
          <span className="text-ink">{item.examTarget}</span>
        </nav>

        <div className="mt-6 grid gap-8 lg:grid-cols-[1.4fr_0.6fr] lg:items-start">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone="neutral">{item.subject}</Badge>
              <Badge tone="neutral">{item.grade}</Badge>
              <Badge tone="neutral">{item.examTarget}</Badge>
              <ClassStatusBadge status={item.status} />
            </div>
            <h1 className="mt-4 text-h1 text-ink">{item.title}</h1>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-small text-ink-soft">
              <span className="text-ink">{item.institute}</span>
              {item.instituteVerified ? <VerifiedBadge /> : null}
              <span>·</span>
              <span>{item.teacher}</span>
              {item.teacherVerified ? <VerifiedBadge label="Verified teacher" /> : null}
            </div>

            <dl className="mt-6 grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-3">
              <Fact icon={<CalendarIcon className="h-4 w-4" />} label="Schedule" value={`${item.day} · ${item.startTime} – ${item.endTime}`} />
              <Fact icon={<MapPinIcon className="h-4 w-4" />} label="Location" value={`${item.city}, ${item.district} · ${item.mode}`} />
              <Fact icon={<UsersIcon className="h-4 w-4" />} label="Availability" value={`${item.seatsTaken} of ${item.seatsTotal} seats taken`} />
            </dl>

            <section className="mt-8">
              <h2 className="text-h2 text-ink">About this class</h2>
              <p className="mt-3 text-body text-ink-soft">{item.description}</p>
              <ul className="mt-5 space-y-2">
                {item.highlights.map((h) =>
                <li key={h} className="flex items-start gap-2.5 text-small text-ink-soft">
                    <CheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-forest" aria-hidden="true" />
                    {h}
                  </li>
                )}
              </ul>
            </section>

            <section className="mt-8 rounded-2xl border border-line bg-white p-5">
              <h2 className="text-h3 text-ink">What the institute shares with parents</h2>
              <ul className="mt-3 grid gap-2 text-small text-ink-soft sm:grid-cols-2">
                <li>Attendance marked per session</li>
                <li>Marks for every assessment, with class average</li>
                <li>Monthly invoice and payment receipt</li>
                <li>Announcements by app and SMS</li>
              </ul>
            </section>

            <section className="mt-8">
              <h2 className="text-h3 text-ink">Reviews</h2>
              <div className="mt-3 rounded-2xl border border-dashed border-line-strong bg-surface-muted px-5 py-6">
                <p className="text-small text-ink-soft">
                  Reviews open once a class has completed a full term on TuitionHub, and
                  can only be written by enrolled students. Nothing to show yet for this
                  class.
                </p>
              </div>
            </section>

            {related.length ?
            <section className="mt-8">
                <h2 className="text-h3 text-ink">Other classes at this time</h2>
                <div className="mt-3 space-y-2">
                  {related.map((c) =>
                <ClassCardMini key={c.id} item={c} />
                )}
                </div>
              </section> :
            null}
          </div>

          <aside className="lg:sticky lg:top-24">
            <div className="rounded-2xl border border-line bg-white p-5 shadow-card">
              <p className="text-caption uppercase tracking-wide text-ink-faint">
                Monthly fee
              </p>
              <p className="mt-1 text-h1 text-ink">{lkr(item.feeMonthly)}</p>
              <p className="mt-1 text-caption text-ink-soft">
                Paid monthly through TuitionHub · platform fee {lkr(Math.round(item.feeMonthly * 0.02))} shown
                before you confirm
              </p>

              <div className="mt-4">
                <div className="h-1.5 overflow-hidden rounded-full bg-surface-sunk">
                  <div
                    className={`h-full rounded-full ${fill >= 95 ? 'bg-clay' : 'bg-forest-400'}`}
                    style={{ width: `${fill}%` }} />
                  
                </div>
                <p className="mt-2 text-caption text-ink-soft">
                  {item.seatsTotal - item.seatsTaken} seats remaining
                </p>
              </div>

              <div className="mt-5 space-y-2">
                <Button className="w-full" disabled={!canJoin} onClick={startEnroll}>
                  {item.status === 'active' ?
                  'Join this class' :
                  item.status === 'full' ?
                  'Class full — join waitlist' :
                  'Registration closed'}
                </Button>
                <Button
                  variant="secondary"
                  className="w-full"
                  onClick={toggleSave}>
                  
                  <BookmarkIcon className="h-4 w-4" />
                  {saved ? 'Saved' : 'Save class'}
                </Button>
              </div>
              {!user ?
              <p className="mt-3 text-caption text-ink-faint">
                  Browsing as a guest. You’ll be asked to sign in when you enrol or save —
                  and brought straight back here.
                </p> :
              null}

              <div className="mt-5 flex gap-2.5 rounded-xl bg-surface-muted px-3.5 py-3">
                <ShieldCheckIcon className="mt-0.5 h-4 w-4 shrink-0 text-forest" aria-hidden="true" />
                <p className="text-caption text-ink-soft">
                  Fees paid here are recorded against this enrolment and settled to{' '}
                  {item.institute}. You can see the receipt in Fees &amp; payments.
                </p>
              </div>
            </div>
          </aside>
        </div>
      </main>

      <Modal open={joinOpen} onClose={() => setJoinOpen(false)} title="Join this class">
        <div className="space-y-4">
          <div className="rounded-xl border border-line px-4 py-3">
            <p className="text-small font-medium text-ink">{item.title}</p>
            <p className="text-caption text-ink-soft">
              {item.institute} · {item.day} {item.startTime} · {item.city}
            </p>
          </div>
          {restricted ?
          <p className="rounded-xl bg-state-warnBg px-4 py-3 text-small text-state-warn">
              Enrolling is paused until a parent or guardian connects to your account.{' '}
              <Link to="/student/connect-guardian" className="font-medium underline underline-offset-4">
                Connect now
              </Link>
            </p> :
          null}
          <ol className="space-y-3 text-small text-ink-soft">
            {isStudent ?
            <Step n={1} title="You request the seat">
                Enrolling as {user?.name}. {guardianState === 'linked' ?
              `${user?.guardianName ?? 'Your guardian'} is notified and pays the invoice.` :
              'Your guardian pays once they connect.'}
              </Step> :

            <Step n={1} title="Choose the student">
                Kasun Perera (Grade 12), Nethmi Perera (Grade 10), or add another child from Family.
              </Step>
            }
            <Step n={2} title="Institute confirms the seat">
              Sarasavi Academy accepts or declines within one working day.
            </Step>
            <Step n={3} title="First invoice is issued">
              {lkr(item.feeMonthly)} for the month, plus a {lkr(Math.round(item.feeMonthly * 0.02))} platform
              fee, payable in Fees &amp; payments.
            </Step>
          </ol>
          <div className="flex gap-2 pt-1">
            {isStudent ?
            <Button
              className="flex-1"
              disabled={restricted}
              onClick={() => {
                setJoinOpen(false);
                setToast('Enrolment request sent');
              }}>
              
                Send enrolment request
              </Button> :

            <Button
              className="flex-1"
              onClick={() => {
                if (!requireAuth('pay', `${here}?intent=enroll`)) return;
                navigate('/parent/fees');
              }}>
              
                Continue to payment
              </Button>
            }
            <Button variant="secondary" onClick={() => setJoinOpen(false)}>
              Not now
            </Button>
          </div>
        </div>
      </Modal>

      <Toast
        open={toast !== null}
        message={toast ?? ''}
        detail={
        toast === 'Enrolment request sent' ?
        `${item.institute} confirms the seat within one working day.` :
        'Saved classes appear in your account.'
        }
        onClose={() => setToast(null)} />
      

      <SiteFooter />
    </div>);

}

function Fact({
  icon,
  label,
  value




}: {icon: React.ReactNode;label: string;value: string;}) {
  return (
    <div className="bg-white px-5 py-4">
      <div className="flex items-center gap-2 text-ink-faint">
        {icon}
        <dt className="text-caption uppercase tracking-wide">{label}</dt>
      </div>
      <dd className="mt-1.5 text-small font-medium text-ink">{value}</dd>
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