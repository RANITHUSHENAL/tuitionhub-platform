export type ClassMode = 'Physical' | 'Online' | 'Hybrid';
export type ClassStatus = 'active' | 'full' | 'closed' | 'cancelled';
export type Medium = 'English' | 'Sinhala' | 'Tamil';

export interface TuitionClass {
  id: string;
  title: string;
  subject: string;
  grade: string;
  examTarget: string;
  institute: string;
  instituteVerified: boolean;
  teacher: string;
  teacherVerified: boolean;
  medium: Medium;
  day: string;
  startTime: string;
  endTime: string;
  city: string;
  district: string;
  mode: ClassMode;
  feeMonthly: number;
  seatsTaken: number;
  seatsTotal: number;
  status: ClassStatus;
  summary: string;
  description: string;
  highlights: string[];
  /** approximate marker position on the schematic discovery map, 0-100 */
  mapX: number;
  mapY: number;
}

export type ActivityType =
'Study Together' |
'Peer Teaching' |
'Past Paper Discussion' |
'Practice Session' |
'Discussion' |
'Project / Knowledge Sharing' |
'Online Study Session';

export type ActivityVisibility = 'public' | 'request' | 'private';
export type ActivityStatus =
'open' |
'full' |
'request-pending' |
'joined' |
'cancelled' |
'completed';

export interface Participant {
  name: string;
  initials: string;
  detail: string;
}

export interface ChatMessage {
  author: string;
  initials: string;
  text: string;
  time: string;
  self?: boolean;
}

export interface StudyActivity {
  id: string;
  title: string;
  type: ActivityType;
  subject: string;
  examTarget: string;
  dayLabel: string;
  timeLabel: string;
  durationLabel: string;
  areaLabel: string;
  venueType: 'Online' | 'Library' | 'Café' | 'Institute' | 'Other';
  going: number;
  capacity: number;
  visibility: ActivityVisibility;
  status: ActivityStatus;
  host: {name: string;detail: string;verified: boolean;};
  description: string;
  participants: Participant[];
  chatPreview: ChatMessage[];
  mapX: number;
  mapY: number;
}

export interface StudyCircle {
  id: string;
  name: string;
  members: number;
  subject: string;
  examTarget: string;
  area: string;
  description: string;
  activitiesThisWeek: number;
  verifiedHost: string;
}

export type PaymentStatus =
'pending' |
'processing' |
'paid' |
'failed' |
'refunded';
export type AttendanceStatus = 'present' | 'absent' | 'late' | 'excused';

export interface Invoice {
  id: string;
  month: string;
  className: string;
  institute: string;
  amount: number;
  platformFee: number;
  status: PaymentStatus;
  paidOn?: string;
  method?: string;
  reference?: string;
}

export interface AttendanceRecord {
  date: string;
  className: string;
  status: AttendanceStatus;
  markedBy: string;
  method?: 'QR scan' | 'Manual' | 'Online join';
  time?: string;
  note?: string;
}

export type Role = 'student' | 'parent' | 'institute' | 'teacher';
export type GuardianLink = 'not-required' | 'pending' | 'linked';
export type VerificationState = 'none' | 'in-progress' | 'verified' | 'retry' | 'review';

/** Where a teacher works. One person may have several. */
export interface TeachingWorkspace {
  id: string;
  kind: 'independent' | 'institute';
  name: string;
  detail: string;
  /** institute link must be approved by the institute */
  status: 'active' | 'pending';
}

/** One phone number = one identity, which may hold several role profiles. */
export interface Identity {
  id: string;
  name: string;
  initials: string;
  phone: string;
  profiles: AuthUser[];
}

export interface AuthUser {
  id: string;
  role: Role;
  /** teacher only */
  workspaces?: TeachingWorkspace[];
  activeWorkspaceId?: string;
  name: string;
  initials: string;
  detail: string;
  phone: string;
  phoneVerified: boolean;
  isMinor?: boolean;
  guardianLink?: GuardianLink;
  /** epoch ms — end of the 72-hour guardian connection window */
  guardianDeadline?: number;
  guardianName?: string;
  verification?: VerificationState;
  childrenLinked?: number;
  /** parent only — details added after signup, before guardian verification */
  profileComplete?: boolean;
  relationship?: string;
  area?: string;
  email?: string;
  enrollmentVerified?: boolean;
}

export type QuestionType =
'single' |
'multiple' |
'truefalse' |
'short' |
'numerical' |
'essay' |
'image';

export interface ExamQuestion {
  id: string;
  type: QuestionType;
  prompt: string;
  options?: string[];
  correct?: number[];
  answerText?: string;
  unit?: string;
  marks: number;
}

export type ExamStatus = 'draft' | 'upcoming' | 'open' | 'submitted' | 'graded';

export interface Exam {
  id: string;
  title: string;
  className: string;
  subject: string;
  institute: string;
  durationMins: number;
  totalMarks: number;
  status: ExamStatus;
  windowLabel: string;
  questions: ExamQuestion[];
  score?: number;
  submissions?: number;
}

export interface MarkRecord {
  id: string;
  assessment: string;
  subject: string;
  score: number;
  outOf: number;
  classAverage: number;
  date: string;
}

export interface Announcement {
  id: string;
  title: string;
  body: string;
  from: string;
  time: string;
  channel: 'App' | 'SMS' | 'App + SMS';
}