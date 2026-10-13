import type { AuthUser, Identity, Role, TeachingWorkspace } from '../types';

export const sarasaviWorkspace: TeachingWorkspace = {
  id: 'ws-sarasavi',
  kind: 'institute',
  name: 'Sarasavi Academy',
  detail: 'Institute teacher · Nugegoda',
  status: 'active'
};

export const independentWorkspace: TeachingWorkspace = {
  id: 'ws-independent',
  kind: 'independent',
  name: 'Independent classes',
  detail: 'Your own classes · Maharagama',
  status: 'active'
};

const kasun: AuthUser = {
  id: 'std-01',
  role: 'student',
  name: 'Kasun Perera',
  initials: 'KP',
  detail: 'Grade 12 · A/L 2027',
  phone: '+94 77 123 4567',
  phoneVerified: true,
  isMinor: true,
  guardianLink: 'linked',
  guardianName: 'Mrs. Chamari Perera',
  enrollmentVerified: true
};

/**
 * Prototype identities. Each phone number is ONE identity; the roles it holds
 * are profiles on that identity, not separate accounts.
 */
export const demoIdentities: {identity: Identity;label: string;description: string;}[] = [
{
  label: 'Nimal Perera · 3 roles',
  description: 'Teacher at Sarasavi + independent classes, parent, and part-time degree student',
  identity: {
    id: 'id-nimal',
    name: 'Nimal Perera',
    initials: 'NP',
    phone: '+94 76 222 3344',
    profiles: [
    {
      id: 'tch-01',
      role: 'teacher',
      name: 'Nimal Perera',
      initials: 'NP',
      detail: 'Physics teacher',
      phone: '+94 76 222 3344',
      phoneVerified: true,
      workspaces: [sarasaviWorkspace, independentWorkspace]
    },
    {
      id: 'par-07',
      role: 'parent',
      name: 'Nimal Perera',
      initials: 'NP',
      detail: '1 child · Maharagama',
      phone: '+94 76 222 3344',
      phoneVerified: true,
      verification: 'verified',
      childrenLinked: 1
    },
    {
      id: 'std-31',
      role: 'student',
      name: 'Nimal Perera',
      initials: 'NP',
      detail: 'Adult learner · ICT evening class',
      phone: '+94 76 222 3344',
      phoneVerified: true,
      isMinor: false,
      guardianLink: 'not-required'
    }]

  }
},
{
  label: 'Kasun · student',
  description: 'A/L 2027, guardian linked — one role, goes straight in',
  identity: { id: 'id-kasun', name: kasun.name, initials: 'KP', phone: kasun.phone, profiles: [kasun] }
},
{
  label: 'Dinithi · student, guardian pending',
  description: 'O/L 2027 — new account, 72-hour window running',
  identity: {
    id: 'id-dinithi',
    name: 'Dinithi Pathirana',
    initials: 'DP',
    phone: '+94 71 555 0192',
    profiles: [
    {
      id: 'std-09',
      role: 'student',
      name: 'Dinithi Pathirana',
      initials: 'DP',
      detail: 'Grade 11 · O/L 2027',
      phone: '+94 71 555 0192',
      phoneVerified: true,
      isMinor: true,
      guardianLink: 'pending',
      enrollmentVerified: false
    }]

  }
},
{
  label: 'Mrs. Perera · parent',
  description: 'Verified guardian, two children linked',
  identity: {
    id: 'id-chamari',
    name: 'Mrs. Chamari Perera',
    initials: 'CP',
    phone: '+94 77 987 6543',
    profiles: [
    {
      id: 'par-01',
      role: 'parent',
      name: 'Mrs. Chamari Perera',
      initials: 'CP',
      detail: '2 children · Nugegoda',
      phone: '+94 77 987 6543',
      phoneVerified: true,
      verification: 'verified',
      childrenLinked: 2
    }]

  }
},
{
  label: 'Mr. Fernando · new parent',
  description: 'Just signed up — profile, verification and children still to do',
  identity: {
    id: 'id-fernando',
    name: 'Ruwan Fernando',
    initials: 'RF',
    phone: '+94 76 222 3141',
    profiles: [
    {
      id: 'par-02',
      role: 'parent',
      name: 'Ruwan Fernando',
      initials: 'RF',
      detail: 'Parent account',
      phone: '+94 76 222 3141',
      phoneVerified: true,
      verification: 'none',
      profileComplete: false,
      childrenLinked: 0
    }]

  }
},
{
  label: 'Ms. P. Silva · independent teacher',
  description: 'Runs her own English classes, no institute',
  identity: {
    id: 'id-silva',
    name: 'Ms. Priya Silva',
    initials: 'PS',
    phone: '+94 70 410 8800',
    profiles: [
    {
      id: 'tch-06',
      role: 'teacher',
      name: 'Ms. Priya Silva',
      initials: 'PS',
      detail: 'English teacher',
      phone: '+94 70 410 8800',
      phoneVerified: true,
      workspaces: [{ ...independentWorkspace, detail: 'Your own classes · Dehiwala' }]
    }]

  }
},
{
  label: 'Sarasavi Academy · institute owner',
  description: 'Whole-institute access, Nugegoda',
  identity: {
    id: 'id-sarasavi',
    name: 'Sarasavi Academy',
    initials: 'SA',
    phone: '+94 11 281 0000',
    profiles: [
    {
      id: 'ins-01',
      role: 'institute',
      name: 'Sarasavi Academy',
      initials: 'SA',
      detail: 'Owner · Nugegoda (main)',
      phone: '+94 11 281 0000',
      phoneVerified: true
    }]

  }
}];


export const roleHome: Record<Role, string> = {
  student: '/student',
  parent: '/parent',
  institute: '/institute',
  teacher: '/teacher'
};

export const roleLabel: Record<Role, string> = {
  student: 'Student',
  parent: 'Parent',
  institute: 'Institute',
  teacher: 'Teacher'
};

export const roleSummary: Record<Role, string> = {
  student: 'View classes, attendance, marks and Study.',
  parent: 'Manage children, fees and progress.',
  teacher: 'Manage your classes, attendance, marks and exams.',
  institute: 'Run classes, students, teachers and fees.'
};

export const actionCopy: Record<string, string> = {
  enroll: 'enrol in this class',
  save: 'save this class',
  pay: 'pay tuition fees',
  join: 'join this study activity',
  request: 'request to join this activity',
  message: 'send messages',
  create: 'create a study activity',
  connect: 'connect your child',
  dashboard: 'open your dashboard',
  teach: 'open your teaching workspace'
};