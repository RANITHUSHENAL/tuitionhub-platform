import React from 'react';
import { CheckCircle2Icon } from 'lucide-react';
import type {
  ActivityStatus,
  AttendanceStatus,
  ClassStatus,
  PaymentStatus } from
'../../types';

type Tone = 'neutral' | 'success' | 'warn' | 'danger' | 'info' | 'forest';

const tones: Record<Tone, string> = {
  neutral: 'bg-surface-sunk text-ink-soft border-line',
  success: 'bg-state-successBg text-state-success border-state-success/20',
  warn: 'bg-state-warnBg text-state-warn border-state-warn/20',
  danger: 'bg-state-dangerBg text-state-danger border-state-danger/20',
  info: 'bg-state-infoBg text-state-info border-state-info/20',
  forest: 'bg-forest text-white border-forest'
};

export function Badge({
  tone = 'neutral',
  children,
  className = ''




}: {tone?: Tone;children: React.ReactNode;className?: string;}) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-caption font-medium ${tones[tone]} ${className}`}>
      
      {children}
    </span>);

}

const paymentMap: Record<PaymentStatus, {tone: Tone;label: string;}> = {
  pending: { tone: 'warn', label: 'Payment due' },
  processing: { tone: 'info', label: 'Processing' },
  paid: { tone: 'success', label: 'Paid' },
  failed: { tone: 'danger', label: 'Failed' },
  refunded: { tone: 'neutral', label: 'Refunded' }
};

export function PaymentBadge({ status }: {status: PaymentStatus;}) {
  const s = paymentMap[status];
  return <Badge tone={s.tone}>{s.label}</Badge>;
}

const attendanceMap: Record<AttendanceStatus, {tone: Tone;label: string;}> = {
  present: { tone: 'success', label: 'Present' },
  absent: { tone: 'danger', label: 'Absent' },
  late: { tone: 'warn', label: 'Late' },
  excused: { tone: 'info', label: 'Excused' }
};

export function AttendanceBadge({ status }: {status: AttendanceStatus;}) {
  const s = attendanceMap[status];
  return <Badge tone={s.tone}>{s.label}</Badge>;
}

const classMap: Record<ClassStatus, {tone: Tone;label: string;}> = {
  active: { tone: 'success', label: 'Enrolling' },
  full: { tone: 'warn', label: 'Class full' },
  closed: { tone: 'neutral', label: 'Registration closed' },
  cancelled: { tone: 'danger', label: 'Cancelled' }
};

export function ClassStatusBadge({ status }: {status: ClassStatus;}) {
  const s = classMap[status];
  return <Badge tone={s.tone}>{s.label}</Badge>;
}

const activityMap: Record<ActivityStatus, {tone: Tone;label: string;}> = {
  open: { tone: 'success', label: 'Open' },
  full: { tone: 'warn', label: 'Full' },
  'request-pending': { tone: 'info', label: 'Request pending' },
  joined: { tone: 'forest', label: 'Joined' },
  cancelled: { tone: 'danger', label: 'Cancelled' },
  completed: { tone: 'neutral', label: 'Completed' }
};

export function ActivityStatusBadge({ status }: {status: ActivityStatus;}) {
  const s = activityMap[status];
  return <Badge tone={s.tone}>{s.label}</Badge>;
}

export function VerifiedBadge({ label = 'Verified institute' }: {label?: string;}) {
  return (
    <span className="inline-flex items-center gap-1.5 text-caption font-medium text-forest-600">
      <CheckCircle2Icon className="h-3.5 w-3.5" aria-hidden="true" />
      {label}
    </span>);

}