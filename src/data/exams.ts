import type { Exam, QuestionType } from '../types';

export const questionTypeLabels: Record<QuestionType, {label: string;hint: string;}> = {
  single: { label: 'Single-choice MCQ', hint: 'One correct option' },
  multiple: { label: 'Multiple answers', hint: 'Select all that apply' },
  truefalse: { label: 'True / False', hint: 'Two options' },
  short: { label: 'Short answer', hint: 'A word or phrase' },
  numerical: { label: 'Numerical', hint: 'A number, with unit and tolerance' },
  essay: { label: 'Essay', hint: 'Marked by the teacher' },
  image: { label: 'Image-based', hint: 'Question built around a diagram' }
};

export const exams: Exam[] = [
{
  id: 'exm-01',
  title: 'Physics — Waves Unit Test',
  className: 'A/L Physics — Theory & Revision',
  subject: 'Physics',
  institute: 'Sarasavi Academy',
  durationMins: 30,
  totalMarks: 20,
  status: 'open',
  windowLabel: 'Open now · closes today 11:59 PM',
  submissions: 58,
  questions: [
  {
    id: 'q1',
    type: 'single',
    prompt: 'In a stationary wave, the distance between two adjacent nodes is:',
    options: ['λ / 4', 'λ / 2', 'λ', '2λ'],
    correct: [1],
    marks: 2
  },
  {
    id: 'q2',
    type: 'multiple',
    prompt: 'Which of the following are properties of all electromagnetic waves in a vacuum?',
    options: [
    'They travel at the same speed',
    'They are transverse',
    'They need a medium',
    'They can be polarised'],

    correct: [0, 1, 3],
    marks: 3
  },
  {
    id: 'q3',
    type: 'truefalse',
    prompt: 'Sound waves can be polarised.',
    options: ['True', 'False'],
    correct: [1],
    marks: 1
  },
  {
    id: 'q4',
    type: 'image',
    prompt: 'The diagram shows a stationary wave on a string fixed at both ends. How many antinodes are shown?',
    options: ['2', '3', '4', '5'],
    correct: [1],
    marks: 2
  },
  {
    id: 'q5',
    type: 'numerical',
    prompt: 'A wave has frequency 50 Hz and wavelength 6.8 m. Calculate its speed.',
    answerText: '340',
    unit: 'm s⁻¹',
    marks: 3
  },
  {
    id: 'q6',
    type: 'short',
    prompt: 'Name the phenomenon where two waves combine to form a resultant wave.',
    answerText: 'Superposition',
    marks: 2
  },
  {
    id: 'q7',
    type: 'essay',
    prompt: 'Explain, with reference to phase, how a stationary wave is formed on a stretched string.',
    marks: 7
  }]

},
{
  id: 'exm-02',
  title: 'Combined Maths — Integration MCQ',
  className: 'A/L Combined Mathematics — Theory',
  subject: 'Combined Mathematics',
  institute: 'Sarasavi Academy',
  durationMins: 45,
  totalMarks: 25,
  status: 'upcoming',
  windowLabel: 'Opens Saturday 8:00 AM',
  submissions: 0,
  questions: []
},
{
  id: 'exm-03',
  title: 'Chemistry — Unit 4 MCQ',
  className: 'A/L Chemistry — Revision Batch',
  subject: 'Chemistry',
  institute: 'Lanka Science Centre',
  durationMins: 40,
  totalMarks: 40,
  status: 'graded',
  windowLabel: 'Completed 1 March',
  score: 32,
  submissions: 44,
  questions: []
},
{
  id: 'exm-04',
  title: 'Physics — Electric Fields Quiz',
  className: 'A/L Physics — Theory & Revision',
  subject: 'Physics',
  institute: 'Sarasavi Academy',
  durationMins: 20,
  totalMarks: 15,
  status: 'draft',
  windowLabel: 'Draft · not published',
  submissions: 0,
  questions: []
}];


export const examById = (id: string) => exams.find((e) => e.id === id);