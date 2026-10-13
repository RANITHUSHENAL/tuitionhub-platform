import React, { useEffect } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import { XIcon } from 'lucide-react';

/**
 * One component, two presentations: a bottom sheet on small screens and a
 * docked side panel on desktop. The desktop version is not a lifted sheet —
 * it sits in the layout beside the map, which is where the eye already is.
 */
export function DetailPanel({
  open,
  onClose,
  title,
  children





}: {open: boolean;onClose: () => void;title: string;children: React.ReactNode;}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  return (
    <AnimatePresence>
      {open ?
      <>
          <motion.div
          key="scrim"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.2, ease: [0.23, 1, 0.32, 1] }}
          onClick={onClose}
          className="fixed inset-0 z-40 bg-ink/25 lg:hidden"
          aria-hidden="true" />
        
          <motion.div
          key="panel"
          role="dialog"
          aria-modal="true"
          aria-label={title}
          initial={{ y: '100%' }}
          animate={{ y: 0 }}
          exit={{ y: '100%' }}
          transition={{ duration: 0.28, ease: [0.23, 1, 0.32, 1] }}
          className="fixed inset-x-0 bottom-0 z-50 max-h-[86vh] overflow-y-auto th-scroll rounded-t-3xl border-t border-line bg-white shadow-panel lg:hidden">
          
            <div className="sticky top-0 flex items-center justify-between gap-4 border-b border-line bg-white/95 px-5 pb-3 pt-3 backdrop-blur">
              <span
              className="absolute left-1/2 top-1.5 h-1 w-10 -translate-x-1/2 rounded-full bg-line-strong"
              aria-hidden="true" />
            
              <h2 className="mt-2 text-h3 text-ink">{title}</h2>
              <button
              onClick={onClose}
              aria-label="Close"
              className="mt-2 rounded-lg p-1.5 text-ink-soft transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">
              
                <XIcon className="h-5 w-5" />
              </button>
            </div>
            <div className="px-5 pb-8 pt-4">{children}</div>
          </motion.div>
        </> :
      null}
    </AnimatePresence>);

}

export function DockedPanel({
  title,
  onClose,
  children




}: {title: string;onClose: () => void;children: React.ReactNode;}) {
  return (
    <motion.aside
      initial={{ opacity: 0, x: 16 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.22, ease: [0.23, 1, 0.32, 1] }}
      aria-label={title}
      className="hidden h-full flex-col overflow-hidden rounded-2xl border border-line bg-white shadow-card lg:flex">
      
      <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
        <h2 className="text-h3 text-ink">{title}</h2>
        <button
          onClick={onClose}
          aria-label="Close details"
          className="rounded-lg p-1.5 text-ink-soft transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">
          
          <XIcon className="h-5 w-5" />
        </button>
      </div>
      <div className="flex-1 overflow-y-auto th-scroll px-5 py-4">{children}</div>
    </motion.aside>);

}

export function Modal({
  open,
  onClose,
  title,
  children





}: {open: boolean;onClose: () => void;title: string;children: React.ReactNode;}) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  return (
    <AnimatePresence>
      {open ?
      <div className="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6">
          <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.18, ease: [0.23, 1, 0.32, 1] }}
          className="absolute inset-0 bg-ink/30"
          onClick={onClose}
          aria-hidden="true" />
        
          <motion.div
          role="dialog"
          aria-modal="true"
          aria-label={title}
          initial={{ opacity: 0, scale: 0.97, y: 12 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.98, y: 8 }}
          transition={{ duration: 0.24, ease: [0.23, 1, 0.32, 1] }}
          className="relative z-10 w-full max-w-lg overflow-hidden rounded-t-3xl border border-line bg-white shadow-panel sm:rounded-2xl">
          
            <div className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
              <h2 className="text-h3 text-ink">{title}</h2>
              <button
              onClick={onClose}
              aria-label="Close"
              className="rounded-lg p-1.5 text-ink-soft transition-colors duration-150 ease-out hover:bg-surface-muted hover:text-ink">
              
                <XIcon className="h-5 w-5" />
              </button>
            </div>
            <div className="max-h-[70vh] overflow-y-auto th-scroll px-5 py-5">{children}</div>
          </motion.div>
        </div> :
      null}
    </AnimatePresence>);

}