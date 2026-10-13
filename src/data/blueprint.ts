export const architecture = [
{
  pillar: 'Discover',
  subtitle: 'Public, unauthenticated',
  items: ['Class search & filters', 'Class profile', 'Institute profile', 'Teacher (optional, institute-controlled)']
},
{
  pillar: 'Manage',
  subtitle: 'Institute, teacher',
  items: ['Classes & schedules', 'Enrolments & rosters', 'Attendance', 'Marks & exams', 'Announcements & SMS', 'Fees, invoices, settlements']
},
{
  pillar: 'Connect',
  subtitle: 'Student-owned',
  items: ['Study activities', 'Study Map', 'Study Circles', 'Activity & circle chat']
}];


export const sitemap = [
{
  group: 'Public',
  routes: [
  { path: '/', label: 'Home' },
  { path: '/search', label: 'Class search (list + map)' },
  { path: '/class/:id', label: 'Class detail' },
  { path: '/blueprint', label: 'Product blueprint' },
  { path: '/design-system', label: 'Design system' }]

},
{
  group: 'Parent',
  routes: [
  { path: '/parent', label: 'Overview' },
  { path: '/parent/attendance', label: 'Attendance' },
  { path: '/parent/fees', label: 'Fees & payments' },
  { path: '/parent/marks', label: 'Marks' },
  { path: '/parent/announcements', label: 'Announcements' }]

},
{
  group: 'Student',
  routes: [
  { path: '/student', label: 'Home' },
  { path: '/student/schedule', label: 'Schedule' },
  { path: '/student/marks', label: 'Marks & attendance' },
  { path: '/student/profile', label: 'Profile & privacy' },
  { path: '/messages', label: 'Messages' }]

},
{
  group: 'Study',
  routes: [
  { path: '/study', label: 'Discovery — map / list' },
  { path: '/study/activity/:id', label: 'Activity detail' },
  { path: '/study/create', label: 'Create activity' },
  { path: '/study/circles/:id', label: 'Study Circle' }]

},
{
  group: 'Institute (whole-institute scope)',
  routes: [
  { path: '/institute', label: 'Dashboard' },
  { path: '/institute/classes', label: 'Classes' },
  { path: '/institute/students', label: 'Students' },
  { path: '/institute/teachers', label: 'Teachers & requests' },
  { path: '/institute/fees', label: 'Fees & payments' },
  { path: '/institute/attendance', label: 'More → Attendance overview' },
  { path: '/institute/exams', label: 'More → Exams & academic' },
  { path: '/institute/announcements', label: 'More → Announcements' }]

},
{
  group: 'Teacher (my classes only)',
  routes: [
  { path: '/teacher', label: 'Home' },
  { path: '/teacher/classes', label: 'My classes → class: Students · Attendance · Marks · Exams' },
  { path: '/teacher/attendance', label: 'Attendance — start, QR, close' },
  { path: '/teacher/marks', label: 'Marks' },
  { path: '/teacher/exams', label: 'My exams → create' },
  { path: '/teacher/announcements', label: 'Class announcements' },
  { path: '/teacher/payments', label: 'Payments (independent only)' },
  { path: '/teacher/workspaces', label: 'Workspaces & join an institute' }]

}];


export const journeys = [
{
  id: 'A',
  name: 'Parent finds and pays for a class',
  steps: ['Home', 'Search', 'Class detail', 'Join this class', 'Fees & payments', 'Paid + receipt'],
  link: '/search'
},
{
  id: 'B',
  name: 'Parent checks the week',
  steps: ['Parent overview', 'Attendance', 'Marks', 'Announcements'],
  link: '/parent'
},
{
  id: 'C',
  name: 'Student joins a study session',
  steps: ['Student home', 'Study map', 'Tap marker', 'Panel / bottom sheet', 'Join', 'Group chat'],
  link: '/study'
},
{
  id: 'D',
  name: 'Student creates an activity',
  steps: ['Study', 'Create activity', 'Details', 'Visibility', 'Publish'],
  link: '/study/create'
},
{
  id: 'E',
  name: 'Institute runs the day',
  steps: ['Institute overview', 'Classes', 'Students', 'Attendance', 'Settlements'],
  link: '/institute'
},
{
  id: 'F',
  name: 'Study Circle keeps a cohort going',
  steps: ['Study', 'Circle', 'Discussions', 'Activities', 'Create session', 'Chat'],
  link: '/study/circles/cir-01'
}];


export const heroCritique = {
  strong: [
  'The three-clause promise is the sharpest thing in the whole product. It is concrete, it is a parent sentence, and no competitor is saying it.',
  'Parent-first positioning is correct: the parent is the buyer once payments are on, and the student is the daily user.',
  'A search bar in the hero is right — discovery is the only thing an unknown visitor can act on immediately.',
  'The restrained green / terracotta / warm-neutral palette reads as education, not fintech. Keep it.'],

  change: [
  '“Trusted by 340 institutes” and “4.9 from 2,100+ parents” are fabricated traction. For an unlaunched product this is the fastest way to lose an institute owner who asks which 340. Replaced with an honest early-access statement about the beachhead corridor.',
  'The language switcher showed “EN EN EN”. It now shows සිංහල | தமிழ் | EN, and the type stack carries Sinhala and Tamil faces so labels do not break.',
  'The headline was set at the same weight across four lines, so nothing won. The payment clause now carries the colour, because payment visibility is the differentiator.',
  'The stock isometric illustration said nothing. It is replaced with the actual artefact the promise refers to: an attendance mark, a mark with the class average, and a settled payment. Show the product, not a drawing of a classroom.',
  'CTA hierarchy was flat — “Find a class” appeared twice (nav and hero) while “Browse classes near you” and “I run an institute” competed as equals. Now one primary (Find a class), one secondary (I run an institute), and quick filter chips instead of a third button.'],

  missing: [
  'Nothing told an institute owner what they get. The hero now leads to a dedicated institute section framed as “run your institute better”, not “list with us”.',
  'No entry point for students. Study is introduced far enough down the page that it never competes with the parent promise, and is a first-class item in the nav.',
  'No indication of stage. An early-access line sets expectations honestly and makes the pilot pitch credible.'],

  answers: [
  {
    q: 'Is the headline too long?',
    a: 'Three clauses is the maximum, and it works because the structure repeats. Keep all three on desktop; on small screens the third clause carries the emphasis and the supporting line shortens.'
  },
  {
    q: 'Should search be more prominent?',
    a: 'Yes. It is now the largest interactive element on the page, with quick filter chips underneath so a visitor who does not know what to type still gets somewhere.'
  },
  {
    q: 'Is parent-first correct?',
    a: 'Yes — but the page must serve three audiences in order: parent (hero + parent section), institute (dedicated section, reassurance-led), student (Study section). Order communicates priority without excluding anyone.'
  },
  {
    q: 'How should Study appear without confusing the core purpose?',
    a: 'As a distinct surface with its own colour treatment, placed after the core product story, and always described in study language — never as a social feed.'
  }]

};

export const naming = [
{ name: 'StudyCircle', verdict: 'Strongest', note: 'Describes the persistent object the product is actually about. Works as a sub-brand and eventually as a standalone app. Slight risk: generic enough that it is hard to own in search.' },
{ name: 'StudyLoop', verdict: 'Strong', note: 'Short, memorable, implies recurrence. Reads modern without being childish. Pairs well with “by TuitionHub”.' },
{ name: 'Panthi', verdict: 'Worth testing', note: 'Local word for a class or study group — instantly meaningful in Sinhala. Collides with an existing directory site, so check before committing.' },
{ name: 'StudyCrew / StudyHive / StudySpace', verdict: 'Weaker', note: 'Crew and Hive lean social-club; Space is overused and vague. None say learning specifically.' },
{ name: 'Focus', verdict: 'Avoid', note: 'Unsearchable, already a productivity category, and says nothing about doing it with other people.' },
{ name: 'StudyMeet', verdict: 'Avoid', note: '“Meet” is the word to stay away from — it invites the meet-people reading this product must not have.' }];


export const safety = [
{ title: 'Approximate areas only', body: 'Public discovery shows “Near Nugegoda”, never a live position or an address. The exact meeting point is released after the host accepts a participant.' },
{ title: 'Host controls', body: 'Participant limit, public / request-to-join / invite-only visibility, remove a participant, close joining, cancel the session.' },
{ title: 'Report and block', body: 'On every activity, profile and message. Reports carry the activity context so moderation does not start from zero.' },
{ title: 'Verified signal', body: 'Accounts attached to an enrolment at a verified institute carry a verified marker. It is a provenance signal, not an identity check, and is labelled as such.' },
{ title: 'Scoped profiles', body: 'First name, grade, subjects. No school, no phone number, no location history. Students control discoverability and direct messages.' },
{ title: 'No dating mechanics', body: 'No swiping, no matching, no proximity to people — only to activities. Every surface is organised by subject and exam year.' }];


export const phases = [
{
  phase: 'Phase 0',
  title: 'Validation',
  body: 'Landing page, institute conversations, parent waitlist, payment-gateway path. The prototype exists to make those conversations concrete.',
  surfaces: ['Home', 'Class detail (mock listings)']
},
{
  phase: 'Phase 1',
  title: 'One institute, end to end',
  body: 'Institute admin, teacher entry, student profile, parent view, single-account payments, SMS.',
  surfaces: ['Institute dashboard', 'Teacher today', 'Parent overview / attendance / fees / marks', 'Student home']
},
{
  phase: 'Phase 2',
  title: 'Marketplace and mobile',
  body: 'Public search, mobile apps, multi-institute routing, online links, cancellations, first study activities and messaging foundations.',
  surfaces: ['Search', 'Study map & activities', 'Messages']
},
{
  phase: 'Phase 3',
  title: 'Depth',
  body: 'MCQ exams, materials, white-label institute mode, Study Circles at scale, and the option to split Study into its own app on shared accounts.',
  surfaces: ['Study Circles', 'Exams', 'Resources']
}];