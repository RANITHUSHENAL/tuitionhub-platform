import type { ActivityType, StudyActivity, StudyCircle } from '../types';

export const activityTypes: ActivityType[] = [
'Study Together',
'Peer Teaching',
'Past Paper Discussion',
'Practice Session',
'Discussion',
'Project / Knowledge Sharing',
'Online Study Session'];


export const activities: StudyActivity[] = [
{
  id: 'act-01',
  title: 'A/L Physics — Waves Study Session',
  type: 'Study Together',
  subject: 'Physics',
  examTarget: 'A/L 2027',
  dayLabel: 'Today',
  timeLabel: '6:00 PM – 8:00 PM',
  durationLabel: '2 hours',
  areaLabel: 'Near Nugegoda',
  venueType: 'Library',
  going: 4,
  capacity: 6,
  visibility: 'public',
  status: 'open',
  host: { name: 'Nimal', detail: 'A/L 2027 · Physics', verified: true },
  description:
  'Going through waves theory and solving past-paper questions together. Bring your own notes — we will work through stationary waves first, then the 2025 structured questions.',
  participants: [
  { name: 'Nimal', initials: 'NP', detail: 'Host · A/L 2027' },
  { name: 'Ayesha', initials: 'AR', detail: 'A/L 2027 · Physics' },
  { name: 'Thilina', initials: 'TS', detail: 'A/L 2027 · Maths' },
  { name: 'Hasini', initials: 'HW', detail: 'A/L 2027 · Physics' }],

  chatPreview: [
  { author: 'Nimal', initials: 'NP', text: "I'm bringing the 2025 paper.", time: '4:12 PM' },
  { author: 'Ayesha', initials: 'AR', text: 'Should we start with stationary waves?', time: '4:20 PM' },
  { author: 'Thilina', initials: 'TS', text: 'Works for me. I have the tuition notes for that unit.', time: '4:26 PM' }],

  mapX: 36,
  mapY: 46
},
{
  id: 'act-02',
  title: '2025 A/L Chemistry Paper Discussion',
  type: 'Past Paper Discussion',
  subject: 'Chemistry',
  examTarget: 'A/L 2027',
  dayLabel: 'Today',
  timeLabel: '8:30 PM',
  durationLabel: '1 hour 30 min',
  areaLabel: 'Online',
  venueType: 'Online',
  going: 12,
  capacity: 20,
  visibility: 'request',
  status: 'request-pending',
  host: { name: 'Sanduni', detail: 'A/L 2027 · Chemistry', verified: true },
  description:
  'Walking through the full 2025 Chemistry paper, MCQ section first. Request to join and I will approve before 7 PM so the room stays focused.',
  participants: [
  { name: 'Sanduni', initials: 'SK', detail: 'Host · A/L 2027' },
  { name: 'Ravindu', initials: 'RD', detail: 'A/L 2027 · Bio' },
  { name: 'Ishara', initials: 'IJ', detail: 'A/L 2027 · Chemistry' },
  { name: 'Malith', initials: 'MB', detail: 'A/L 2027 · Chemistry' }],

  chatPreview: [
  { author: 'Sanduni', initials: 'SK', text: 'Link goes out once everyone is approved.', time: '3:02 PM' },
  { author: 'Ishara', initials: 'IJ', text: 'Can we cover organic first?', time: '3:40 PM' }],

  mapX: 70,
  mapY: 30
},
{
  id: 'act-03',
  title: 'Sunday Library Study',
  type: 'Study Together',
  subject: 'Mixed subjects',
  examTarget: 'O/L 2027',
  dayLabel: 'Sunday',
  timeLabel: '2:00 PM – 5:00 PM',
  durationLabel: '3 hours',
  areaLabel: 'Around Colombo 07',
  venueType: 'Library',
  going: 3,
  capacity: 8,
  visibility: 'public',
  status: 'open',
  host: { name: 'Dinithi', detail: 'O/L 2027 · Maths', verified: false },
  description:
  'Quiet co-study at the public library. No teaching — everyone works on their own subject, we take one 15-minute break together.',
  participants: [
  { name: 'Dinithi', initials: 'DP', detail: 'Host · O/L 2027' },
  { name: 'Kavith', initials: 'KA', detail: 'O/L 2027 · Science' },
  { name: 'Nethmi', initials: 'NS', detail: 'O/L 2027 · Maths' }],

  chatPreview: [
  { author: 'Dinithi', initials: 'DP', text: 'Meeting at the reading room entrance.', time: 'Fri 6:10 PM' }],

  mapX: 55,
  mapY: 22
},
{
  id: 'act-04',
  title: 'I can explain Electric Fields',
  type: 'Peer Teaching',
  subject: 'Physics',
  examTarget: 'A/L 2027',
  dayLabel: 'Tomorrow',
  timeLabel: '7:00 PM',
  durationLabel: '1 hour',
  areaLabel: 'Online',
  venueType: 'Online',
  going: 9,
  capacity: 15,
  visibility: 'public',
  status: 'open',
  host: { name: 'Thilina', detail: 'A/L 2027 · Physics', verified: true },
  description:
  'I finished this unit last month and it clicked for me. I will walk through field lines, potential and the standard structured question, then take questions.',
  participants: [
  { name: 'Thilina', initials: 'TS', detail: 'Host · A/L 2027' },
  { name: 'Hasini', initials: 'HW', detail: 'A/L 2027 · Physics' },
  { name: 'Ruwan', initials: 'RF', detail: 'A/L 2027 · Maths' },
  { name: 'Ayesha', initials: 'AR', detail: 'A/L 2027 · Physics' }],

  chatPreview: [
  { author: 'Thilina', initials: 'TS', text: 'Slides are short, mostly worked problems.', time: '11:05 AM' }],

  mapX: 64,
  mapY: 64
},
{
  id: 'act-05',
  title: "Let's learn Python together",
  type: 'Project / Knowledge Sharing',
  subject: 'ICT',
  examTarget: 'A/L 2028',
  dayLabel: 'Saturday',
  timeLabel: '10:00 AM',
  durationLabel: '2 hours',
  areaLabel: 'Near Maharagama',
  venueType: 'Café',
  going: 6,
  capacity: 6,
  visibility: 'request',
  status: 'full',
  host: { name: 'Ravindu', detail: 'A/L 2028 · ICT', verified: true },
  description:
  'Working through basic Python together for the ICT project component. Laptops required. This session is full — join the circle to get the next one.',
  participants: [
  { name: 'Ravindu', initials: 'RD', detail: 'Host · A/L 2028' },
  { name: 'Malith', initials: 'MB', detail: 'A/L 2028 · ICT' },
  { name: 'Nethmi', initials: 'NS', detail: 'A/L 2028 · ICT' },
  { name: 'Kavith', initials: 'KA', detail: 'A/L 2028 · ICT' }],

  chatPreview: [
  { author: 'Ravindu', initials: 'RD', text: 'Install Python before Saturday please.', time: 'Wed 8:30 PM' }],

  mapX: 28,
  mapY: 66
},
{
  id: 'act-06',
  title: 'Combined Maths — Integration Practice',
  type: 'Practice Session',
  subject: 'Combined Mathematics',
  examTarget: 'A/L 2027',
  dayLabel: 'Thursday',
  timeLabel: '5:00 PM',
  durationLabel: '2 hours',
  areaLabel: 'Near Pannipitiya',
  venueType: 'Institute',
  going: 5,
  capacity: 10,
  visibility: 'public',
  status: 'joined',
  host: { name: 'Ayesha', detail: 'A/L 2027 · Maths', verified: true },
  description:
  'Timed integration practice — 20 questions, 90 minutes, then we mark each other and discuss the ones most people missed.',
  participants: [
  { name: 'Ayesha', initials: 'AR', detail: 'Host · A/L 2027' },
  { name: 'Kasun', initials: 'KP', detail: 'A/L 2027 · Maths' },
  { name: 'Nimal', initials: 'NP', detail: 'A/L 2027 · Physics' },
  { name: 'Ruwan', initials: 'RF', detail: 'A/L 2027 · Maths' }],

  chatPreview: [
  { author: 'Ayesha', initials: 'AR', text: 'Bring a printed answer sheet if you can.', time: 'Tue 9:15 PM' },
  { author: 'Kasun', initials: 'KP', text: 'Will do. Starting sharp at 5?', time: 'Tue 9:22 PM', self: true }],

  mapX: 24,
  mapY: 78
}];


export const activityById = (id: string) => activities.find((a) => a.id === id);

export const circles: StudyCircle[] = [
{
  id: 'cir-01',
  name: 'A/L Physics 2027 — Colombo',
  members: 38,
  subject: 'Physics',
  examTarget: 'A/L 2027',
  area: 'Colombo District',
  description:
  'A standing group for the 2027 Physics cohort in and around Colombo. Weekly sessions, shared unit notes, and a running doubts thread.',
  activitiesThisWeek: 3,
  verifiedHost: 'Nimal'
},
{
  id: 'cir-02',
  name: 'O/L Maths 2027 — Maharagama',
  members: 21,
  subject: 'Mathematics',
  examTarget: 'O/L 2027',
  area: 'Maharagama',
  description:
  'Paper-focused circle. One timed paper every weekend, discussed together on Sunday evening.',
  activitiesThisWeek: 2,
  verifiedHost: 'Dinithi'
},
{
  id: 'cir-03',
  name: 'ICT Project Builders',
  members: 14,
  subject: 'ICT',
  examTarget: 'A/L 2028',
  area: 'Online',
  description:
  'Students building their A/L ICT project components together. Code reviews, not lectures.',
  activitiesThisWeek: 1,
  verifiedHost: 'Ravindu'
}];


export const circleById = (id: string) => circles.find((c) => c.id === id);

export const conversations = [
{
  id: 'con-01',
  name: 'A/L Physics — Waves Study Session',
  kind: 'Activity group' as const,
  last: 'Ayesha: Should we start with stationary waves?',
  time: '4:20 PM',
  unread: 2
},
{
  id: 'con-02',
  name: 'A/L Physics 2027 — Colombo',
  kind: 'Study Circle' as const,
  last: 'Nimal: Unit 6 notes are in Resources now.',
  time: '2:05 PM',
  unread: 0
},
{
  id: 'con-03',
  name: 'Ayesha Rajapaksa',
  kind: 'Direct' as const,
  last: 'Thanks for the integration sheet!',
  time: 'Yesterday',
  unread: 0
},
{
  id: 'con-04',
  name: 'TuitionHub Support',
  kind: 'Support' as const,
  last: 'Your March payment receipt is available.',
  time: 'Mon',
  unread: 0
}];