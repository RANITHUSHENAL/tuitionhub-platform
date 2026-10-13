/** Teacher-scoped data. A teacher only ever sees classes assigned to them. */

export interface TeacherClass {
  id: string;
  workspaceId: string;
  name: string;
  subject: string;
  schedule: string;
  place: string;
  students: number;
  attendanceRate: number;
  nextSession: string;
}

export interface TeacherSession {
  id: string;
  classId: string;
  name: string;
  time: string;
  place: string;
  state: 'upcoming' | 'next' | 'done';
  present?: number;
  total: number;
}

export const teacherClasses: TeacherClass[] = [
{ id: 'tcl-1', workspaceId: 'ws-sarasavi', name: 'A/L Physics — Theory & Revision', subject: 'Physics', schedule: 'Tue & Sat · 6:00 PM', place: 'Nugegoda · Hall B', students: 32, attendanceRate: 91, nextSession: 'Today · 6:00 PM' },
{ id: 'tcl-2', workspaceId: 'ws-sarasavi', name: 'A/L Physics — 2028 Theory', subject: 'Physics', schedule: 'Sun · 9:00 AM', place: 'Nugegoda · Hall A', students: 28, attendanceRate: 88, nextSession: 'Sunday · 9:00 AM' },
{ id: 'tcl-3', workspaceId: 'ws-independent', name: 'O/L Science — Small Group', subject: 'Science', schedule: 'Wed · 4:00 PM', place: 'Maharagama · home class', students: 12, attendanceRate: 95, nextSession: 'Today · 3:30 PM' },
{ id: 'tcl-4', workspaceId: 'ws-independent', name: 'A/L Physics — Online Revision', subject: 'Physics', schedule: 'Fri · 8:00 PM', place: 'Online', students: 40, attendanceRate: 84, nextSession: 'Friday · 8:00 PM' }];


/** Students in one of the teacher's classes (prototype sample of the class) */
export const classStudents = [
{ id: 's-1', name: 'Kasun Perera', grade: 'Grade 12', attendance: 92, lastMark: 78 },
{ id: 's-2', name: 'Ayesha Rajapaksa', grade: 'Grade 12', attendance: 98, lastMark: 84 },
{ id: 's-3', name: 'Thilina Samarasinghe', grade: 'Grade 12', attendance: 74, lastMark: 55 },
{ id: 's-4', name: 'Hasini Weerasinghe', grade: 'Grade 12', attendance: 88, lastMark: 69 },
{ id: 's-5', name: 'Ruwan Fernando', grade: 'Grade 12', attendance: 61, lastMark: 42 },
{ id: 's-6', name: 'Sachini Jayawardena', grade: 'Grade 12', attendance: 95, lastMark: 81 },
{ id: 's-7', name: 'Pasan Wickramasinghe', grade: 'Grade 12', attendance: 90, lastMark: 73 },
{ id: 's-8', name: 'Nethmi Gunasekara', grade: 'Grade 12', attendance: 86, lastMark: 66 },
{ id: 's-9', name: 'Chamod Dissanayake', grade: 'Grade 12', attendance: 93, lastMark: 77 },
{ id: 's-10', name: 'Imasha Herath', grade: 'Grade 12', attendance: 97, lastMark: 88 }];


export const teacherSessions: TeacherSession[] = [
{ id: 'ts-1', classId: 'tcl-3', name: 'O/L Science — Small Group', time: '3:30 PM', place: 'Maharagama', state: 'done', present: 11, total: 12 },
{ id: 'ts-2', classId: 'tcl-1', name: 'A/L Physics — Theory & Revision', time: '6:00 PM', place: 'Nugegoda · Hall B', state: 'next', total: 32 }];


export const teacherActivity = [
{ id: 'ta-1', text: '18 new submissions — Physics Waves Unit Test', time: '12 min ago', to: '/teacher/exams' },
{ id: 'ta-2', text: 'Attendance closed — O/L Science, 11 of 12 present', time: '2 h ago', to: '/teacher/attendance' },
{ id: 'ta-3', text: 'Your announcement “Bring the 2025 paper” reached 32 students and their guardians', time: 'Yesterday', to: '/teacher/announcements' }];


export const teacherAnnouncements = [
{ id: 'tan-1', classId: 'tcl-1', title: 'Bring the 2025 paper on Saturday', body: 'We will go through Part B together. Printed copies at the front desk.', sent: 'Yesterday · 7:10 PM', reach: 32 },
{ id: 'tan-2', classId: 'tcl-4', title: 'Friday link updated', body: 'The Zoom link has changed. It is on the class page from 7:30 PM.', sent: 'Mon · 9:02 PM', reach: 40 }];


/** Pending teacher → institute link requests, seen by the institute */
export const teacherRequests = [
{ id: 'rq-1', name: 'Ms. Dilani Weerasinghe', subject: 'Chemistry', detail: 'A/L Chemistry · 6 years', note: 'I cover Saturday batches at Maharagama.', requested: '2 hours ago' },
{ id: 'rq-2', name: 'Mr. Ashan Kumara', subject: 'ICT', detail: 'A/L ICT · 3 years', note: 'Referred by Mr. Jayasinghe.', requested: 'Yesterday' }];


export type InstituteMemberRole = 'Owner' | 'Manager' | 'Staff' | 'Teacher';

export const instituteTeam: {id: string;name: string;role: InstituteMemberRole;subject?: string;classes: number;access: string;}[] = [
{ id: 'm-1', name: 'Mrs. K. Rajapaksha', role: 'Owner', classes: 0, access: 'Whole institute, including settlements' },
{ id: 'm-2', name: 'Mr. S. Dias', role: 'Manager', classes: 0, access: 'Classes, students, fees — no settlement changes' },
{ id: 'm-3', name: 'Ms. H. Nanayakkara', role: 'Staff', classes: 0, access: 'Front desk: enrolments, manual attendance' },
{ id: 'm-4', name: 'Mr. N. Perera', role: 'Teacher', subject: 'Physics', classes: 2, access: 'Assigned classes only' },
{ id: 'm-5', name: 'Mr. R. Jayasinghe', role: 'Teacher', subject: 'Combined Maths', classes: 1, access: 'Assigned classes only' },
{ id: 'm-6', name: 'Mrs. S. Fernando', role: 'Teacher', subject: 'O/L Maths', classes: 1, access: 'Assigned classes only' },
{ id: 'm-7', name: 'Ms. P. Silva', role: 'Teacher', subject: 'English', classes: 1, access: 'Assigned classes only' }];


export const independentPayments = [
{ id: 'ip-1', student: 'Sanduni R.', className: 'O/L Science — Small Group', month: 'April', amount: 2500, status: 'paid' as const },
{ id: 'ip-2', student: 'Tharindu M.', className: 'O/L Science — Small Group', month: 'April', amount: 2500, status: 'pending' as const },
{ id: 'ip-3', student: 'Ishara P.', className: 'A/L Physics — Online Revision', month: 'April', amount: 2000, status: 'paid' as const },
{ id: 'ip-4', student: 'Ravindu S.', className: 'A/L Physics — Online Revision', month: 'April', amount: 2000, status: 'failed' as const }];