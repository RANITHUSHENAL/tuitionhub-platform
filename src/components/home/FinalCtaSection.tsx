import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRightIcon } from 'lucide-react';
import { LinkButton } from '../ui/Button';

export function FinalCtaSection() {
  return (
    <section>
      <div className="mx-auto grid max-w-[1200px] gap-10 px-5 py-16 lg:grid-cols-[1.3fr_0.7fr] lg:items-end lg:px-8 lg:py-20">
        <div>
          <h2 className="text-h1 text-ink">
            Find your next class.
            <br />
            <span className="text-forest">Manage your tuition. Learn together.</span>
          </h2>
          <div className="mt-8 flex flex-wrap gap-3">
            <LinkButton to="/search" size="lg">
              Find a class
            </LinkButton>
            <LinkButton to="/auth?mode=create" size="lg" variant="secondary">
              Join TuitionHub
            </LinkButton>
          </div>
        </div>
        <div className="space-y-1 border-t border-line pt-6 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0">
          <p className="pb-1 text-caption text-ink-faint">For education providers</p>
          <ProviderLink href="#institutes" label="Run your institute with TuitionHub" />
          <ProviderLink href="/auth/teacher" label="Start teaching with TuitionHub" />
        </div>
      </div>
    </section>);

}

function ProviderLink({ href, label }: {href: string;label: string;}) {
  const cls =
  'group flex items-center justify-between gap-3 py-2 text-body font-medium text-ink transition-colors duration-150 ease-out hover:text-forest';
  const inner =
  <>
      {label}
      <ArrowRightIcon className="h-4 w-4 shrink-0 transition-transform duration-150 ease-out group-hover:translate-x-0.5" aria-hidden="true" />
    </>;

  return href.startsWith('#') ?
  <a href={href} className={cls}>
      {inner}
    </a> :

  <Link to={href} className={cls}>
      {inner}
    </Link>;

}