import React from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertTriangleIcon, CheckCircle2Icon, XIcon } from 'lucide-react';

export function EmptyState({
  icon,
  title,
  body,
  action





}: {icon: React.ReactNode;title: string;body: string;action?: React.ReactNode;}) {
  return (
    <div className="flex flex-col items-center rounded-2xl border border-dashed border-line-strong bg-surface-muted px-6 py-12 text-center">
      <span className="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-white text-forest">
        {icon}
      </span>
      <h3 className="text-h3 text-ink">{title}</h3>
      <p className="mt-1.5 max-w-sm text-small text-ink-soft">{body}</p>
      {action ? <div className="mt-5">{action}</div> : null}
    </div>);

}

export function ErrorState({
  title,
  body,
  action




}: {title: string;body: string;action?: React.ReactNode;}) {
  return (
    <div className="rounded-2xl border border-state-danger/25 bg-state-dangerBg/50 px-5 py-5">
      <div className="flex gap-3">
        <AlertTriangleIcon className="mt-0.5 h-5 w-5 shrink-0 text-state-danger" aria-hidden="true" />
        <div>
          <h3 className="text-h3 text-ink">{title}</h3>
          <p className="mt-1 text-small text-ink-soft">{body}</p>
          {action ? <div className="mt-4">{action}</div> : null}
        </div>
      </div>
    </div>);

}

export function SkeletonCard() {
  return (
    <div className="animate-pulse rounded-2xl border border-line bg-white p-5">
      <div className="h-3 w-24 rounded-full bg-surface-sunk" />
      <div className="mt-3 h-4 w-3/4 rounded-full bg-surface-sunk" />
      <div className="mt-2 h-4 w-1/2 rounded-full bg-surface-sunk" />
      <div className="mt-5 flex gap-2">
        <div className="h-6 w-20 rounded-full bg-surface-sunk" />
        <div className="h-6 w-16 rounded-full bg-surface-sunk" />
      </div>
    </div>);

}

export function Toast({
  open,
  message,
  detail,
  onClose





}: {open: boolean;message: string;detail?: string;onClose: () => void;}) {
  return (
    <AnimatePresence>
      {open ?
      <motion.div
        role="status"
        initial={{ opacity: 0, y: 12, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        exit={{ opacity: 0, y: 8, scale: 0.98 }}
        transition={{ duration: 0.22, ease: [0.23, 1, 0.32, 1] }}
        className="fixed bottom-5 left-1/2 z-[60] w-[min(92vw,26rem)] -translate-x-1/2 rounded-2xl border border-line bg-white px-4 py-3.5 shadow-panel">
        
          <div className="flex items-start gap-3">
            <CheckCircle2Icon className="mt-0.5 h-5 w-5 shrink-0 text-forest" aria-hidden="true" />
            <div className="min-w-0 flex-1">
              <p className="text-small font-medium text-ink">{message}</p>
              {detail ? <p className="mt-0.5 text-caption text-ink-soft">{detail}</p> : null}
            </div>
            <button
            onClick={onClose}
            aria-label="Dismiss"
            className="rounded-lg p-1 text-ink-faint transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">
            
              <XIcon className="h-4 w-4" />
            </button>
          </div>
        </motion.div> :
      null}
    </AnimatePresence>);

}

export function PrototypeNote({ children }: {children: React.ReactNode;}) {
  return (
    <p className="text-caption text-ink-faint">
      <span className="font-medium text-ink-soft">Prototype data.</span> {children}
    </p>);

}