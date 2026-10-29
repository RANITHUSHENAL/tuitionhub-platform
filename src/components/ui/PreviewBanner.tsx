import { InfoIcon } from 'lucide-react';

/**
 * Shown on every screen that still renders prototype sample data, so it is never mistaken for the
 * user's real records. Remove it from a screen once that screen reads from the API.
 */
export function PreviewBanner() {
  return (
    <div role="note" className="mb-6 flex gap-2.5 rounded-xl border border-state-warn/30 bg-state-warnBg px-4 py-3">
      <InfoIcon className="mt-0.5 h-4 w-4 shrink-0 text-state-warn" aria-hidden="true" />
      <p className="text-small text-ink">
        <span className="font-semibold">Preview: Sample Data – Features coming soon.</span>{' '}
        Names, classes, figures and actions on this screen are examples, not your real records.
      </p>
    </div>);

}
