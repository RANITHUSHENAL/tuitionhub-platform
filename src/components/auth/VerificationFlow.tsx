import React, { useState } from 'react';
import {
  AlertCircleIcon,
  CameraIcon,
  CheckCircle2Icon,
  FileSearchIcon,
  HourglassIcon,
  ScanFaceIcon,
  UserCheckIcon } from
'lucide-react';
import { Button } from '../ui/Button';
import { Badge } from '../ui/Badge';
import type { VerificationState } from '../../types';

type Stage = 'intro' | 'document' | 'reading' | 'confirm' | 'liveness' | 'status';

const statusCopy: Record<Exclude<VerificationState, 'none'>, {tone: 'info' | 'success' | 'warn' | 'danger';title: string;body: string;}> = {
  'in-progress': {
    tone: 'info',
    title: 'Verification in progress',
    body: 'Automated checks have read your document. A member of the TuitionHub team confirms every guardian verification — usually within a few hours.'
  },
  verified: {
    tone: 'success',
    title: 'Verified guardian',
    body: 'You can now approve child connections, see attendance and marks, and pay fees.'
  },
  retry: {
    tone: 'danger',
    title: 'Please try again',
    body: 'We could not read the document clearly — usually glare or a cropped edge. Retake it on a flat, well-lit surface.'
  },
  review: {
    tone: 'warn',
    title: 'Needs review',
    body: 'Something needs a closer look, so a person is reviewing it. We may call you on your registered number. Nothing has been rejected.'
  }
};

export function VerificationFlow({
  initial = 'none',
  onChange,
  onDone




}: {initial?: VerificationState;onChange?: (s: VerificationState) => void;onDone?: () => void;}) {
  const [stage, setStage] = useState<Stage>(initial === 'none' ? 'intro' : 'status');
  const [status, setStatus] = useState<VerificationState>(initial === 'none' ? 'in-progress' : initial);
  const [side, setSide] = useState<'front' | 'back'>('front');

  const setState = (s: VerificationState) => {
    setStatus(s);
    onChange?.(s);
  };

  if (stage === 'intro') {
    return (
      <div className="space-y-5">
        <ol className="space-y-3">
          <Row icon={<CameraIcon className="h-4 w-4" />} title="Photograph your NIC or passport" body="Front and back. We read your name and ID number from it." />
          <Row icon={<ScanFaceIcon className="h-4 w-4" />} title="A short face check" body="Turn your head slowly so we can match you to the photo." />
          <Row icon={<UserCheckIcon className="h-4 w-4" />} title="A person confirms it" body="Software assists; a TuitionHub reviewer makes the final decision." />
        </ol>
        <p className="text-caption text-ink-faint">
          Your document is used only to verify you as a guardian. It is never shown to
          institutes, teachers or other parents.
        </p>
        <Button size="lg" className="w-full" onClick={() => setStage('document')}>
          Start verification
        </Button>
      </div>);

  }

  if (stage === 'document') {
    return (
      <div className="space-y-5">
        <div className="relative flex aspect-[1.6] items-center justify-center overflow-hidden rounded-2xl border border-line bg-ink">
          <div className="absolute inset-6 rounded-xl border-2 border-dashed border-white/60" />
          <p className="relative text-small text-white/80">
            Place the {side} of your ID inside the frame
          </p>
        </div>
        <div className="flex items-center justify-between text-caption text-ink-soft">
          <Badge tone={side === 'front' ? 'forest' : 'success'}>Front</Badge>
          <span className="h-px flex-1 bg-line" />
          <Badge tone={side === 'back' ? 'forest' : 'neutral'}>Back</Badge>
        </div>
        <Button
          size="lg"
          className="w-full"
          onClick={() => {
            if (side === 'front') setSide('back');else
            {
              setStage('reading');
              window.setTimeout(() => setStage('confirm'), 1100);
            }
          }}>
          
          <CameraIcon className="h-4 w-4" />
          Capture {side}
        </Button>
      </div>);

  }

  if (stage === 'reading') {
    return (
      <div className="flex flex-col items-center py-10 text-center">
        <span className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-forest-50 text-forest">
          <FileSearchIcon className="h-6 w-6" />
        </span>
        <p className="mt-4 text-small font-medium text-ink">Reading your document</p>
        <p className="mt-1 text-caption text-ink-soft">Checking the text is legible and the edges are visible.</p>
      </div>);

  }

  if (stage === 'confirm') {
    return (
      <div className="space-y-5">
        <p className="text-small text-ink-soft">
          We read these details from your ID. Correct anything that looks wrong — the
          reviewer sees both versions.
        </p>
        <dl className="divide-y divide-line rounded-2xl border border-line bg-white">
          <Field label="Name on ID" value="W. A. Chamari Perera" />
          <Field label="ID number" value="•••••••••2341V" />
          <Field label="Date of birth" value="14 Aug 1981" />
        </dl>
        <Button size="lg" className="w-full" onClick={() => setStage('liveness')}>
          Details are correct
        </Button>
      </div>);

  }

  if (stage === 'liveness') {
    return (
      <div className="space-y-5">
        <div className="mx-auto flex h-56 w-56 items-center justify-center rounded-full border-4 border-dashed border-forest-100 bg-surface-muted">
          <ScanFaceIcon className="h-16 w-16 text-forest-400" aria-hidden="true" />
        </div>
        <p className="text-center text-small text-ink-soft">
          Keep your face in the circle and turn your head slowly left, then right.
        </p>
        <Button
          size="lg"
          className="w-full"
          onClick={() => {
            setState('in-progress');
            setStage('status');
          }}>
          
          Submit for review
        </Button>
      </div>);

  }

  const s = status === 'none' ? statusCopy['in-progress'] : statusCopy[status];
  const toneBox = {
    info: 'border-state-info/25 bg-state-infoBg/60',
    success: 'border-state-success/25 bg-state-successBg/60',
    warn: 'border-state-warn/25 bg-state-warnBg/60',
    danger: 'border-state-danger/25 bg-state-dangerBg/60'
  }[s.tone];
  const Icon =
  status === 'verified' ? CheckCircle2Icon : status === 'retry' ? AlertCircleIcon : status === 'review' ? UserCheckIcon : HourglassIcon;

  return (
    <div className="space-y-5">
      <div className={`rounded-2xl border p-5 ${toneBox}`}>
        <div className="flex items-start gap-3">
          <Icon className="mt-0.5 h-5 w-5 shrink-0 text-ink" aria-hidden="true" />
          <div>
            <p className="text-small font-medium text-ink">{s.title}</p>
            <p className="mt-1 text-small text-ink-soft">{s.body}</p>
          </div>
        </div>
      </div>

      {status === 'retry' ?
      <Button className="w-full" onClick={() => {setSide('front');setStage('document');}}>
          Retake document photo
        </Button> :
      null}
      {onDone ?
      <Button variant={status === 'retry' ? 'secondary' : 'primary'} size="lg" className="w-full" onClick={onDone}>
          Continue
        </Button> :
      null}

      <div className="rounded-xl border border-dashed border-line-strong p-3">
        <p className="text-caption text-ink-faint">Prototype — preview each outcome:</p>
        <div className="mt-2 flex flex-wrap gap-2">
          {(['in-progress', 'verified', 'retry', 'review'] as const).map((k) =>
          <button
            key={k}
            onClick={() => setState(k)}
            aria-pressed={status === k}
            className={`rounded-full border px-2.5 py-1 text-caption font-medium transition-colors duration-150 ease-out ${
            status === k ? 'border-forest bg-forest text-white' : 'border-line text-ink-soft hover:text-ink'}`
            }>
            
              {statusCopy[k].title}
            </button>
          )}
        </div>
      </div>
    </div>);

}

function Row({ icon, title, body }: {icon: React.ReactNode;title: string;body: string;}) {
  return (
    <li className="flex gap-3 rounded-xl border border-line bg-white px-4 py-3">
      <span className="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-forest-50 text-forest">
        {icon}
      </span>
      <span>
        <span className="block text-small font-medium text-ink">{title}</span>
        <span className="block text-caption text-ink-soft">{body}</span>
      </span>
    </li>);

}

function Field({ label, value }: {label: string;value: string;}) {
  return (
    <div className="flex items-center justify-between gap-3 px-4 py-3">
      <dt className="text-caption text-ink-soft">{label}</dt>
      <dd className="text-small font-medium text-ink">{value}</dd>
    </div>);

}