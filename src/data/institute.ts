export const institute = {
  name: 'Sarasavi Academy',
  branch: 'Nugegoda (main)',
  verified: true,
  plan: 'Growth'
};

export const instituteStats = {
  totalStudents: 1284,
  classesToday: 7,
  attendanceToday: 86,
  attendanceMarked: 5,
  collectedThisMonth: 3186000,
  outstanding: 412000,
  settledYesterday: 268400,
  nextSettlement: 'Tomorrow, 9:00 AM'
};

export const todaysClasses = [
{
  id: 'tc-1',
  name: 'A/L Physics — Theory & Revision',
  teacher: 'Mr. N. Perera',
  time: '6:00 PM',
  hall: 'Hall B',
  enrolled: 96,
  attendance: 'not-marked' as const,
  present: 0
},
{
  id: 'tc-2',
  name: 'O/L Mathematics — Paper Class',
  teacher: 'Mrs. S. Fernando',
  time: '4:00 PM',
  hall: 'Hall A',
  enrolled: 60,
  attendance: 'marked' as const,
  present: 54
},
{
  id: 'tc-3',
  name: 'A/L Combined Mathematics — Theory',
  teacher: 'Mr. R. Jayasinghe',
  time: '2:00 PM',
  hall: 'Main Hall',
  enrolled: 84,
  attendance: 'marked' as const,
  present: 79
},
{
  id: 'tc-4',
  name: 'O/L English — Spoken & Paper',
  teacher: 'Ms. P. Silva',
  time: '4:30 PM',
  hall: 'Room 3',
  enrolled: 22,
  attendance: 'partial' as const,
  present: 14
}];


export const instituteClasses = [
{ id: 'ic-1', name: 'A/L Combined Mathematics — Theory', teacher: 'Mr. R. Jayasinghe', schedule: 'Sat · 8:00 AM', enrolled: 84, capacity: 120, fee: 3000, listed: true, status: 'active' as const },
{ id: 'ic-2', name: 'A/L Physics — Theory & Revision', teacher: 'Mr. N. Perera', schedule: 'Tue · 6:00 PM', enrolled: 96, capacity: 100, fee: 3200, listed: true, status: 'active' as const },
{ id: 'ic-3', name: 'O/L Mathematics — Paper Class', teacher: 'Mrs. S. Fernando', schedule: 'Sun · 9:00 AM', enrolled: 60, capacity: 60, fee: 2200, listed: true, status: 'full' as const },
{ id: 'ic-4', name: 'A/L Accounting — Theory', teacher: 'Mr. D. Gunaratne', schedule: 'Thu · 4:00 PM', enrolled: 40, capacity: 70, fee: 2800, listed: false, status: 'closed' as const },
{ id: 'ic-5', name: 'O/L English — Spoken & Paper', teacher: 'Ms. P. Silva', schedule: 'Mon · 4:30 PM', enrolled: 22, capacity: 40, fee: 1800, listed: true, status: 'active' as const }];


export const roster = [
{ id: 'r-1', name: 'Kasun Perera', studentId: 'SA-1042', grade: 'Grade 12', guardian: 'Mrs. C. Perera', attendance: 92, feeStatus: 'paid' as const },
{ id: 'r-2', name: 'Ayesha Rajapaksa', studentId: 'SA-1067', grade: 'Grade 12', guardian: 'Mr. S. Rajapaksa', attendance: 98, feeStatus: 'paid' as const },
{ id: 'r-3', name: 'Thilina Samarasinghe', studentId: 'SA-1078', grade: 'Grade 12', guardian: 'Mrs. D. Samarasinghe', attendance: 74, feeStatus: 'pending' as const },
{ id: 'r-4', name: 'Hasini Weerasinghe', studentId: 'SA-1091', grade: 'Grade 12', guardian: 'Mr. K. Weerasinghe', attendance: 88, feeStatus: 'processing' as const },
{ id: 'r-5', name: 'Ruwan Fernando', studentId: 'SA-1103', grade: 'Grade 12', guardian: 'Mrs. N. Fernando', attendance: 61, feeStatus: 'failed' as const },
{ id: 'r-6', name: 'Dinithi Pathirana', studentId: 'SA-1119', grade: 'Grade 11', guardian: 'Mr. L. Pathirana', attendance: 95, feeStatus: 'paid' as const }];


export const teacherActivity = [
{ id: 'ta-1', teacher: 'Mr. R. Jayasinghe', action: 'Marked attendance', target: 'Combined Maths · 79 of 84', time: '12 min ago' },
{ id: 'ta-2', teacher: 'Mrs. S. Fernando', action: 'Entered marks', target: 'O/L Maths Paper 6', time: '1 hour ago' },
{ id: 'ta-3', teacher: 'Ms. P. Silva', action: 'Published announcement', target: 'Speaking groups reshuffled', time: '3 hours ago' },
{ id: 'ta-4', teacher: 'Mr. N. Perera', action: 'Added online link', target: 'A/L Physics · tonight', time: '4 hours ago' }];


export const settlements = [
{ id: 'st-1', date: '19 Mar 2026', collected: 268400, fee: 5368, net: 263032, status: 'paid' as const },
{ id: 'st-2', date: '18 Mar 2026', collected: 192000, fee: 3840, net: 188160, status: 'paid' as const },
{ id: 'st-3', date: '17 Mar 2026', collected: 341600, fee: 6832, net: 334768, status: 'paid' as const },
{ id: 'st-4', date: '20 Mar 2026', collected: 154200, fee: 3084, net: 151116, status: 'processing' as const }];