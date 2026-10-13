import React from 'react';
import { classById, classes } from '../../data/classes';
import { sponsored } from '../../data/feed';
import { ClassCard } from '../cards/ClassCard';
import { LinkButton } from '../ui/Button';

/** First content after the hero: real listings with open seats. */
export function EnrollingSection() {
  const promo = sponsored[0];
  const promoClass = classById(promo.classId);
  const enrolling = classes.filter((c) => c.status === 'active' && c.id !== promoClass?.id).slice(0, 5);

  return (
    <section className="border-b border-line/60">
      <div className="mx-auto max-w-[1200px] px-5 py-14 lg:px-8 lg:py-16">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <h2 className="text-h1 text-ink">Enrolling now</h2>
            <p className="mt-2 text-body text-ink-soft">Classes with open seats this term.</p>
          </div>
          <LinkButton to="/search" variant="secondary">
            Browse all classes
          </LinkButton>
        </div>
        <div className="mt-8 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
          {enrolling.map((c) =>
          <ClassCard key={c.id} item={c} compact />
          )}
          {promoClass ? <ClassCard item={promoClass} sponsoredBy={promo.advertiser} compact /> : null}
        </div>
      </div>
    </section>);

}