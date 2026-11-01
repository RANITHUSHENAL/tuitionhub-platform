/**
 * Demo illustration only. A flat Rs 100 TuitionHub fee is added on top of the institute's class
 * fee (C3 worked example: Rs 2,500 + Rs 100 = Rs 2,600, Rs 2,500 owed to the institute). It is
 * never a percentage. F5/E4 replace every use with the snapshotted invoice and payment values
 * returned by the API.
 */
export const DEMO_PLATFORM_FEE = 100;

/** What a parent would pay for one class fee in the demo illustration. */
export function demoParentTotal(classFee: number) {
  return classFee + DEMO_PLATFORM_FEE;
}

/** Daily sample totals: one flat demo fee per payment; the class fees are owed to the institute. */
export function demoDailyTotals(payments: number, owedToInstitute: number) {
  const platformFee = payments * DEMO_PLATFORM_FEE;
  return { platformFee, paidByParents: owedToInstitute + platformFee, owedToInstitute };
}
