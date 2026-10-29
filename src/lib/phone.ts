/**
 * Sri Lankan mobile numbers. The API accepts only E.164 mobiles: +947XXXXXXXX (contract §1.5).
 */

/** Accepts `77 123 4567`, `0771234567` or `+94 77 123 4567`; returns `+94771234567`, or null. */
export function toSriLankanMobile(input: string): string | null {
  let digits = input.replace(/\D/g, '');

  if (digits.length === 11 && digits.startsWith('94')) digits = digits.slice(2);
  if (digits.length === 10 && digits.startsWith('0')) digits = digits.slice(1);

  return /^7\d{8}$/.test(digits) ? `+94${digits}` : null;
}

/** `+94771234567` → `+94 77 123 4567`. Anything else is returned unchanged. */
export function formatSriLankanMobile(e164: string): string {
  const match = /^\+94(\d{2})(\d{3})(\d{4})$/.exec(e164);

  return match ? `+94 ${match[1]} ${match[2]} ${match[3]}` : e164;
}
