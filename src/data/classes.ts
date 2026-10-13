import type { TuitionClass } from '../types';

export const subjects = [
'Combined Mathematics',
'Physics',
'Chemistry',
'Biology',
'ICT',
'Accounting',
'Economics',
'English',
'Mathematics',
'Science'];


export const grades = ['Grade 9', 'Grade 10', 'Grade 11', 'Grade 12', 'Grade 13'];
export const examTargets = ['O/L 2027', 'O/L 2028', 'A/L 2027', 'A/L 2028'];
export const districts = ['Colombo', 'Gampaha', 'Kalutara', 'Kandy', 'Galle'];
export const cities = [
'Nugegoda',
'Maharagama',
'Pannipitiya',
'Colombo 05',
'Kottawa',
'Dehiwala',
'Online'];

export const mediums = ['English', 'Sinhala', 'Tamil'];
export const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export const classes: TuitionClass[] = [
{
  id: 'cls-01',
  title: 'A/L Combined Mathematics — Theory',
  subject: 'Combined Mathematics',
  grade: 'Grade 12',
  examTarget: 'A/L 2027',
  institute: 'Sarasavi Academy',
  instituteVerified: true,
  teacher: 'Mr. R. Jayasinghe',
  teacherVerified: true,
  medium: 'English',
  day: 'Saturday',
  startTime: '8:00 AM',
  endTime: '11:00 AM',
  city: 'Nugegoda',
  district: 'Colombo',
  mode: 'Physical',
  feeMonthly: 3000,
  seatsTaken: 84,
  seatsTotal: 120,
  status: 'active',
  summary: 'Full theory cycle with weekly structured problem sets.',
  description:
  'A complete A/L 2027 Combined Mathematics theory cycle covering Pure and Applied. Each session ends with a structured problem set, and model paper marking begins in the second term. Notes are distributed through the class page before every session.',
  highlights: [
  'Weekly problem sets marked and returned',
  'Model paper cycle from Term 2',
  'Printed notes included in the monthly fee'],

  mapX: 38,
  mapY: 44
},
{
  id: 'cls-02',
  title: 'A/L Physics — Theory & Revision',
  subject: 'Physics',
  grade: 'Grade 12',
  examTarget: 'A/L 2027',
  institute: 'Sarasavi Academy',
  instituteVerified: true,
  teacher: 'Mr. N. Perera',
  teacherVerified: true,
  medium: 'English',
  day: 'Tuesday',
  startTime: '6:00 PM',
  endTime: '8:00 PM',
  city: 'Nugegoda',
  district: 'Colombo',
  mode: 'Hybrid',
  feeMonthly: 3200,
  seatsTaken: 96,
  seatsTotal: 100,
  status: 'active',
  summary: 'Attend in the hall or join the same session online.',
  description:
  'Theory delivered in the Nugegoda hall and streamed live for enrolled students who cannot travel. Recordings stay available for seven days. Practical demonstrations are scheduled monthly on Sundays.',
  highlights: [
  'Live stream link posted 30 minutes before class',
  'Recording available for 7 days',
  'Monthly practical demonstration session'],

  mapX: 41,
  mapY: 40
},
{
  id: 'cls-03',
  title: 'O/L Mathematics — Paper Class',
  subject: 'Mathematics',
  grade: 'Grade 11',
  examTarget: 'O/L 2027',
  institute: 'Vidyaloka Institute',
  instituteVerified: true,
  teacher: 'Mrs. S. Fernando',
  teacherVerified: false,
  medium: 'Sinhala',
  day: 'Sunday',
  startTime: '9:00 AM',
  endTime: '11:30 AM',
  city: 'Maharagama',
  district: 'Colombo',
  mode: 'Physical',
  feeMonthly: 2200,
  seatsTaken: 60,
  seatsTotal: 60,
  status: 'full',
  summary: 'Past paper drilling with weekly timed practice.',
  description:
  'Strictly a paper class — theory is assumed. Every session is a timed paper followed by full discussion. Marks are entered the same evening and visible to parents.',
  highlights: [
  'Timed paper every session',
  'Same-day mark entry',
  'Term ranking within the class'],

  mapX: 30,
  mapY: 62
},
{
  id: 'cls-04',
  title: 'A/L Chemistry — Revision Batch',
  subject: 'Chemistry',
  grade: 'Grade 13',
  examTarget: 'A/L 2027',
  institute: 'Lanka Science Centre',
  instituteVerified: true,
  teacher: 'Dr. A. Wickrama',
  teacherVerified: true,
  medium: 'English',
  day: 'Friday',
  startTime: '5:30 PM',
  endTime: '8:00 PM',
  city: 'Colombo 05',
  district: 'Colombo',
  mode: 'Physical',
  feeMonthly: 4000,
  seatsTaken: 45,
  seatsTotal: 80,
  status: 'active',
  summary: 'Unit-by-unit revision with MCQ drilling.',
  description:
  'Revision batch for the 2027 sitting. Each unit closes with a 40-question MCQ drill and a structured essay. Suited to students who have already finished theory.',
  highlights: [
  'MCQ drill after every unit',
  'Structured essay marking',
  'Doubt-clearing session on the last Sunday of each month'],

  mapX: 52,
  mapY: 30
},
{
  id: 'cls-05',
  title: 'A/L ICT — Online Theory',
  subject: 'ICT',
  grade: 'Grade 12',
  examTarget: 'A/L 2028',
  institute: 'CodeLab Institute',
  instituteVerified: false,
  teacher: 'Mr. T. Bandara',
  teacherVerified: true,
  medium: 'English',
  day: 'Wednesday',
  startTime: '7:00 PM',
  endTime: '9:00 PM',
  city: 'Online',
  district: 'Colombo',
  mode: 'Online',
  feeMonthly: 2500,
  seatsTaken: 130,
  seatsTotal: 200,
  status: 'active',
  summary: 'Island-wide online theory with recorded catch-up.',
  description:
  'Fully online theory class delivered over Zoom. Session links appear on the student dashboard one hour before class. Assignments are submitted through the class page.',
  highlights: [
  'Zoom link on the dashboard, no WhatsApp hunting',
  'Assignment submission in the class page',
  'Catch-up recordings for 14 days'],

  mapX: 66,
  mapY: 55
},
{
  id: 'cls-06',
  title: 'O/L Science — Theory (Tamil Medium)',
  subject: 'Science',
  grade: 'Grade 10',
  examTarget: 'O/L 2028',
  institute: 'Horizon Learning Centre',
  instituteVerified: true,
  teacher: 'Mr. K. Suresh',
  teacherVerified: true,
  medium: 'Tamil',
  day: 'Saturday',
  startTime: '2:00 PM',
  endTime: '4:30 PM',
  city: 'Dehiwala',
  district: 'Colombo',
  mode: 'Physical',
  feeMonthly: 2000,
  seatsTaken: 28,
  seatsTotal: 50,
  status: 'active',
  summary: 'Tamil medium theory from the start of the syllabus.',
  description:
  'Full O/L Science syllabus in Tamil medium, starting from Unit 1. Weekly short tests keep pace with school terms, and parents receive an SMS after every session.',
  highlights: [
  'Weekly short test',
  'SMS to parents after each session',
  'Term progress report shared in-app'],

  mapX: 46,
  mapY: 70
},
{
  id: 'cls-07',
  title: 'A/L Accounting — Theory',
  subject: 'Accounting',
  grade: 'Grade 12',
  examTarget: 'A/L 2027',
  institute: 'Commerce Hub Pannipitiya',
  instituteVerified: false,
  teacher: 'Mr. D. Gunaratne',
  teacherVerified: false,
  medium: 'Sinhala',
  day: 'Thursday',
  startTime: '4:00 PM',
  endTime: '6:30 PM',
  city: 'Pannipitiya',
  district: 'Colombo',
  mode: 'Physical',
  feeMonthly: 2800,
  seatsTaken: 40,
  seatsTotal: 70,
  status: 'closed',
  summary: 'Registration reopens for the next term.',
  description:
  'Theory class for the 2027 commerce stream. Registration for the current term has closed; the institute reopens enrolment at the start of the next term.',
  highlights: [
  'Registration reopens next term',
  'Waitlist kept in order of request',
  'Term fee payable monthly'],

  mapX: 26,
  mapY: 74
},
{
  id: 'cls-08',
  title: 'O/L English — Spoken & Paper',
  subject: 'English',
  grade: 'Grade 11',
  examTarget: 'O/L 2027',
  institute: 'Horizon Learning Centre',
  instituteVerified: true,
  teacher: 'Ms. P. Silva',
  teacherVerified: true,
  medium: 'English',
  day: 'Monday',
  startTime: '4:30 PM',
  endTime: '6:00 PM',
  city: 'Kottawa',
  district: 'Colombo',
  mode: 'Hybrid',
  feeMonthly: 1800,
  seatsTaken: 22,
  seatsTotal: 40,
  status: 'active',
  summary: 'Paper technique plus structured speaking practice.',
  description:
  'Half the session is paper technique, half is structured speaking practice in small groups. Suitable for students targeting an A in the O/L paper.',
  highlights: [
  'Small speaking groups of 6',
  'Essay marked every fortnight',
  'Online option for the paper half'],

  mapX: 22,
  mapY: 56
}];


export const classById = (id: string) => classes.find((c) => c.id === id);