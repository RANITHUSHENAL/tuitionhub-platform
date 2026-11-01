import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { Button } from './Button';
import type { PageMeta } from '../../lib/api';

/** Previous / next for the API's paginated lists (contract §1.3 `meta`). Hidden for a single page. */
export function Pagination({ meta, onPage, label }: {meta: PageMeta;onPage: (page: number) => void;label: string;}) {
  if (meta.last_page <= 1) return null;

  return (
    <nav aria-label={`${label} pages`} className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-3">
      <p className="text-caption text-ink-soft">
        Page {meta.current_page} of {meta.last_page} · {meta.total} total
      </p>
      <div className="flex gap-2">
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page <= 1}
          onClick={() => onPage(meta.current_page - 1)}>

          <ChevronLeftIcon className="h-4 w-4" />
          Previous
        </Button>
        <Button
          variant="secondary"
          size="sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}>

          Next
          <ChevronRightIcon className="h-4 w-4" />
        </Button>
      </div>
    </nav>);

}
