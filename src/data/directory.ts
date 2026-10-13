export interface InstituteListing {
  id: string;
  name: string;
  city: string;
  verified: boolean;
  subjects: string[];
  classCount: number;
  mediums: string[];
  modes: string[];
  about: string;
}

export interface TeacherListing {
  id: string;
  name: string;
  subject: string;
  institute: string;
  verified: boolean;
  classCount: number;
  examTargets: string[];
  medium: string;
  about: string;
}

export const institutes: InstituteListing[] = [
{
  id: 'ins-01',
  name: 'Sarasavi Academy',
  city: 'Nugegoda',
  verified: true,
  subjects: ['Combined Maths', 'Physics', 'O/L Maths', 'Accounting'],
  classCount: 5,
  mediums: ['English', 'Sinhala'],
  modes: ['Physical', 'Hybrid'],
  about: 'A/L science and commerce institute with hybrid options for Physics.'
},
{
  id: 'ins-02',
  name: 'Lanka Science Centre',
  city: 'Colombo 05',
  verified: true,
  subjects: ['Chemistry', 'Biology'],
  classCount: 3,
  mediums: ['English'],
  modes: ['Physical'],
  about: 'Revision-focused A/L chemistry and biology batches.'
},
{
  id: 'ins-03',
  name: 'Horizon Learning Centre',
  city: 'Dehiwala · Kottawa',
  verified: true,
  subjects: ['O/L Science', 'O/L English'],
  classCount: 4,
  mediums: ['English', 'Tamil'],
  modes: ['Physical', 'Hybrid'],
  about: 'O/L classes in English and Tamil medium, with SMS updates after every session.'
},
{
  id: 'ins-04',
  name: 'CodeLab Institute',
  city: 'Online',
  verified: false,
  subjects: ['ICT'],
  classCount: 2,
  mediums: ['English'],
  modes: ['Online'],
  about: 'Island-wide online A/L ICT theory. Verification in progress.'
},
{
  id: 'ins-05',
  name: 'Vidyaloka Institute',
  city: 'Maharagama',
  verified: true,
  subjects: ['O/L Maths', 'O/L Science'],
  classCount: 3,
  mediums: ['Sinhala'],
  modes: ['Physical'],
  about: 'Paper-class specialists for the O/L cohort.'
}];


/** Teachers appear publicly only where their institute has switched it on. */
export const teachers: TeacherListing[] = [
{
  id: 'tch-01',
  name: 'Mr. N. Perera',
  subject: 'Physics',
  institute: 'Sarasavi Academy',
  verified: true,
  classCount: 1,
  examTargets: ['A/L 2027'],
  medium: 'English',
  about: 'Theory with monthly practical demonstrations.'
},
{
  id: 'tch-02',
  name: 'Mr. R. Jayasinghe',
  subject: 'Combined Mathematics',
  institute: 'Sarasavi Academy',
  verified: true,
  classCount: 1,
  examTargets: ['A/L 2027'],
  medium: 'English',
  about: 'Full theory cycle with weekly problem sets.'
},
{
  id: 'tch-03',
  name: 'Dr. A. Wickrama',
  subject: 'Chemistry',
  institute: 'Lanka Science Centre',
  verified: true,
  classCount: 1,
  examTargets: ['A/L 2027'],
  medium: 'English',
  about: 'Unit-by-unit revision with MCQ drilling.'
},
{
  id: 'tch-04',
  name: 'Mr. K. Suresh',
  subject: 'Science',
  institute: 'Horizon Learning Centre',
  verified: true,
  classCount: 1,
  examTargets: ['O/L 2028'],
  medium: 'Tamil',
  about: 'Tamil-medium O/L science from Unit 1.'
},
{
  id: 'tch-05',
  name: 'Mr. T. Bandara',
  subject: 'ICT',
  institute: 'CodeLab Institute',
  verified: true,
  classCount: 1,
  examTargets: ['A/L 2028'],
  medium: 'English',
  about: 'Online theory with recorded catch-up.'
},
{
  id: 'tch-06',
  name: 'Ms. P. Silva',
  subject: 'English',
  institute: 'Horizon Learning Centre',
  verified: true,
  classCount: 1,
  examTargets: ['O/L 2027'],
  medium: 'English',
  about: 'Paper technique plus small speaking groups.'
}];