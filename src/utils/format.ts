export function lkr(amount: number): string {
  return `LKR ${amount.toLocaleString('en-LK')}`;
}

export function lkrShort(amount: number): string {
  if (amount >= 100000) return `LKR ${(amount / 100000).toFixed(2)} lakh`;
  return lkr(amount);
}

export function initialsOf(name: string): string {
  return name.
  split(' ').
  filter(Boolean).
  slice(0, 2).
  map((n) => n[0]?.toUpperCase() ?? '').
  join('');
}

export function percent(value: number, total: number): number {
  if (!total) return 0;
  return Math.round(value / total * 100);
}