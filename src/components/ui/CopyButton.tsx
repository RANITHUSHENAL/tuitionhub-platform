import { useEffect, useState } from 'react';
import { CheckIcon, CopyIcon } from 'lucide-react';
import { Button } from './Button';

/**
 * Copies a value to the clipboard. Only the clipboard receives it: nothing is logged or stored.
 * If the browser refuses (no clipboard permission), the user is asked to copy it by hand.
 */
export function CopyButton({ value, label = 'Copy' }: {value: string;label?: string;}) {
  const [state, setState] = useState<'idle' | 'copied' | 'failed'>('idle');

  useEffect(() => {
    if (state === 'idle') return;
    const id = window.setTimeout(() => setState('idle'), 2500);
    return () => window.clearTimeout(id);
  }, [state]);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(value);
      setState('copied');
    } catch {
      setState('failed');
    }
  };

  return (
    <span className="inline-flex flex-col items-start gap-1">
      <Button type="button" variant="secondary" size="sm" onClick={() => void copy()}>
        {state === 'copied' ? <CheckIcon className="h-4 w-4" /> : <CopyIcon className="h-4 w-4" />}
        {state === 'copied' ? 'Copied' : label}
      </Button>
      <span aria-live="polite" className="text-caption text-ink-faint">
        {state === 'failed' ? 'Couldn’t copy automatically — select it and copy by hand.' : ''}
      </span>
    </span>);

}
