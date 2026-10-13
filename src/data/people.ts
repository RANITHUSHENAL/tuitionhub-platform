import type {
  Announcement,
  AttendanceRecord,
  Invoice,
  MarkRecord } from
'../types';

export const parentProfile = {
  name: 'Mrs. Chamari Perera',
  children: [
  {
    id: 'std-01',
    name: 'Kasun Perera',
    grade: 'Grade 12',
    examTarget: 'A/L 2027',
    school: 'Royal College',
    attendanceRate: 92,
    classesEnrolled: 3
  },
  {
    id: 'std-02',
    name: 'Nethmi Perera',
    grade: 'Grade 10',
    examTarget: 'O/L 2028',
    school: 'Visakha Vidyalaya',
    attendanceRate: 97,
    classesEnrolled: 2
  }]

};

export const studentProfile = {
  id: 'std-01',
  name: 'Kasun Perera',
  grade: 'Grade 12',
  examTarget: 'A/L 2027',
  initials: 'KP',
  subjects: ['Combined Mathematics', 'Physics', 'Chemistry']
};

export const upcomingSessions = [
{
  id: 'ses-01',
  className: 'A/L Physics — Theory & Revision',
  institute: 'Sarasavi Academy',
  when: 'Tomorrow · 6:00 PM',
  where: 'Nugegoda Hall B',
  mode: 'Hybrid' as const,
  link: 'Online link opens 30 min before'
},
{
  id: 'ses-02',
  className: 'A/L Combined Mathematics — Theory',
  institute: 'Sarasavi Academy',
  when: 'Saturday · 8:00 AM',
  where: 'Nugegoda Main Hall',
  mode: 'Physical' as const,
  link: ''
},
{
  id: 'ses-03',
  className: 'A/L Chemistry — Revision Batch',
  institute: 'Lanka Science Centre',
  when: 'Friday · 5:30 PM',
  where: 'Colombo 05',
  mode: 'Physical' as const,
  link: ''
}];


export const attendanceRecords: AttendanceRecord[] = [
{ date: '18 Mar', className: 'A/L Physics', status: 'present', markedBy: 'Mr. N. Perera', method: 'QR scan', time: '6:02 PM' },
{ date: '16 Mar', className: 'Combined Maths', status: 'present', markedBy: 'Mr. R. Jayasinghe', method: 'QR scan', time: '7:54 AM' },
{ date: '15 Mar', className: 'A/L Chemistry', status: 'late', markedBy: 'Dr. A. Wickrama', method: 'QR scan', time: '6:05 PM', note: 'Arrived 6:05 PM' },
{ date: '11 Mar', className: 'A/L Physics', status: 'absent', markedBy: 'Mr. N. Perera', method: 'Manual' },
{ date: '09 Mar', className: 'Combined Maths', status: 'present', markedBy: 'Mr. R. Jayasinghe', method: 'Manual', time: '8:00 AM' },
{ date: '08 Mar', className: 'A/L Chemistry', status: 'excused', markedBy: 'Institute office', method: 'Manual', note: 'Medical note submitted' },
{ date: '04 Mar', className: 'A/L Physics', status: 'present', markedBy: 'Mr. N. Perera', method: 'Online join', time: '6:01 PM' },
{ date: '02 Mar', className: 'Combined Maths', status: 'present', markedBy: 'Mr. R. Jayasinghe', method: 'QR scan', time: '7:58 AM' }];


export const invoices: Invoice[] = [
{
  id: 'inv-2026-04',
  month: 'April 2026',
  className: 'A/L Physics — Theory & Revision',
  institute: 'Sarasavi Academy',
  amount: 3200,
  platformFee: 64,
  status: 'pending'
},
{
  id: 'inv-2026-04b',
  month: 'April 2026',
  className: 'A/L Chemistry — Revision Batch',
  institute: 'Lanka Science Centre',
  amount: 4000,
  platformFee: 80,
  status: 'processing'
},
{
  id: 'inv-2026-03',
  month: 'March 2026',
  className: 'A/L Combined Mathematics — Theory',
  institute: 'Sarasavi Academy',
  amount: 3000,
  platformFee: 60,
  status: 'paid',
  paidOn: '03 Mar 2026',
  method: 'Card ending 4417',
  reference: 'TH-3F91-2026'
},
{
  id: 'inv-2026-03b',
  month: 'March 2026',
  className: 'A/L Physics — Theory & Revision',
  institute: 'Sarasavi Academy',
  amount: 3200,
  platformFee: 64,
  status: 'paid',
  paidOn: '03 Mar 2026',
  method: 'Card ending 4417',
  reference: 'TH-3F92-2026'
},
{
  id: 'inv-2026-02',
  month: 'February 2026',
  className: 'A/L Chemistry — Revision Batch',
  institute: 'Lanka Science Centre',
  amount: 4000,
  platformFee: 80,
  status: 'failed'
},
{
  id: 'inv-2026-01',
  month: 'January 2026',
  className: 'A/L Chemistry — Revision Batch',
  institute: 'Lanka Science Centre',
  amount: 4000,
  platformFee: 80,
  status: 'refunded',
  paidOn: '04 Jan 2026',
  method: 'Card ending 4417',
  reference: 'TH-2K10-2026'
}];


export const marks: MarkRecord[] = [
{ id: 'mk-1', assessment: 'Physics Model Paper 3', subject: 'Physics', score: 78, outOf: 100, classAverage: 64, date: '15 Mar 2026' },
{ id: 'mk-2', assessment: 'Combined Maths — Term Test', subject: 'Combined Mathematics', score: 71, outOf: 100, classAverage: 58, date: '08 Mar 2026' },
{ id: 'mk-3', assessment: 'Chemistry Unit 4 MCQ', subject: 'Chemistry', score: 32, outOf: 40, classAverage: 27, date: '01 Mar 2026' },
{ id: 'mk-4', assessment: 'Physics Model Paper 2', subject: 'Physics', score: 66, outOf: 100, classAverage: 61, date: '12 Feb 2026' }];


export const announcements: Announcement[] = [
{
  id: 'ann-1',
  title: 'Saturday class postponed to Sunday',
  body: 'Combined Mathematics theory moves to Sunday 8:00 AM this week only. The hall is unchanged.',
  from: 'Sarasavi Academy',
  time: '2 hours ago',
  channel: 'App + SMS'
},
{
  id: 'ann-2',
  title: 'Model paper 4 marks released',
  body: 'Physics model paper 4 marks are now visible under Marks. Paper collection from Tuesday.',
  from: 'Mr. N. Perera',
  time: 'Yesterday',
  channel: 'App'
},
{
  id: 'ann-3',
  title: 'April fee invoices issued',
  body: 'April invoices are now available. Fees are unchanged from March.',
  from: 'Sarasavi Academy',
  time: '2 days ago',
  channel: 'App + SMS'
}];