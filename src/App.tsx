import React from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { RequireAuth } from './components/auth/RequireAuth';
import { Splash } from './components/site/Splash';
import { Home } from './pages/Home';
import { Search } from './pages/Search';
import { ClassDetail } from './pages/ClassDetail';
import { Institutes } from './pages/Institutes';
import { Teachers } from './pages/Teachers';
import { Blueprint } from './pages/Blueprint';
import { DesignSystem } from './pages/DesignSystem';
import { Messages } from './pages/Messages';
import { TeacherHome } from './pages/teacher/TeacherHome';
import { TeacherClasses } from './pages/teacher/TeacherClasses';
import { TeacherClassDetail } from './pages/teacher/TeacherClassDetail';
import { TeacherAttendance } from './pages/teacher/TeacherAttendance';
import { TeacherMarks } from './pages/teacher/TeacherMarks';
import { TeacherExams } from './pages/teacher/TeacherExams';
import { ExamBuilder } from './pages/teacher/ExamBuilder';
import { TeacherAnnouncements } from './pages/teacher/TeacherAnnouncements';
import { TeacherPayments } from './pages/teacher/TeacherPayments';
import { TeacherWorkspaces } from './pages/teacher/TeacherWorkspaces';
import { TeacherSignup } from './pages/auth/TeacherSignup';
import { InstituteTeachers } from './pages/institute/InstituteTeachers';
import { Auth } from './pages/auth/Auth';
import { StudentSignup } from './pages/auth/StudentSignup';
import { ParentSignup } from './pages/auth/ParentSignup';
import { ParentOverview } from './pages/parent/ParentOverview';
import { ParentFamily } from './pages/parent/ParentFamily';
import { GuardianVerification } from './pages/parent/GuardianVerification';
import { ParentProfile } from './pages/parent/ParentProfile';
import { ParentAttendance } from './pages/parent/ParentAttendance';
import { ParentFees } from './pages/parent/ParentFees';
import { ParentMarks } from './pages/parent/ParentMarks';
import { ParentAnnouncements } from './pages/parent/ParentAnnouncements';
import { StudentHome } from './pages/student/StudentHome';
import { StudentSchedule } from './pages/student/StudentSchedule';
import { StudentAttendance } from './pages/student/StudentAttendance';
import { StudentExams } from './pages/student/StudentExams';
import { ExamTake } from './pages/student/ExamTake';
import { StudentMarks } from './pages/student/StudentMarks';
import { StudentProfile } from './pages/student/StudentProfile';
import { ConnectGuardian } from './pages/student/ConnectGuardian';
import { StudyDiscover } from './pages/study/StudyDiscover';
import { ActivityDetail } from './pages/study/ActivityDetail';
import { CreateActivity } from './pages/study/CreateActivity';
import { CircleDetail } from './pages/study/CircleDetail';
import { InstituteOverview } from './pages/institute/InstituteOverview';
import { InstituteClasses } from './pages/institute/InstituteClasses';
import { InstituteStudents } from './pages/institute/InstituteStudents';
import { InstituteAttendance } from './pages/institute/InstituteAttendance';
import { InstituteExams } from './pages/institute/InstituteExams';
import { InstituteFees } from './pages/institute/InstituteFees';
import { InstituteAnnouncements } from './pages/institute/InstituteAnnouncements';

const student = (el: React.ReactElement, action = 'dashboard') =>
<RequireAuth roles={['student']} action={action}>
    {el}
  </RequireAuth>;

const parent = (el: React.ReactElement, action = 'dashboard') =>
<RequireAuth roles={['parent']} action={action}>
    {el}
  </RequireAuth>;

/** Institute pages are whole-institute scope — teachers never land here. */
const operator = (el: React.ReactElement) => <RequireAuth roles={['institute']}>{el}</RequireAuth>;
/** Teacher pages are scoped to the teacher's own classes in the active workspace. */
const teacher = (el: React.ReactElement) =>
<RequireAuth roles={['teacher']} action="teach">
    {el}
  </RequireAuth>;


export function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Splash />
        <Routes>
          {/* Public — no account needed */}
          <Route path="/" element={<Home />} />
          <Route path="/search" element={<Search />} />
          <Route path="/class/:id" element={<ClassDetail />} />
          <Route path="/institutes" element={<Institutes />} />
          <Route path="/teachers" element={<Teachers />} />
          <Route path="/study" element={<StudyDiscover />} />
          <Route path="/study/activity/:id" element={<ActivityDetail />} />
          <Route path="/blueprint" element={<Blueprint />} />
          <Route path="/design-system" element={<DesignSystem />} />

          {/* Authentication */}
          <Route path="/auth" element={<Auth />} />
          <Route path="/auth/student" element={<StudentSignup />} />
          <Route path="/auth/parent" element={<ParentSignup />} />
          <Route path="/auth/teacher" element={<TeacherSignup />} />

          {/* Signed in — any role */}
          <Route path="/messages" element={<RequireAuth action="message"><Messages /></RequireAuth>} />
          <Route path="/study/create" element={student(<CreateActivity />, 'create')} />
          <Route path="/study/circles/:id" element={student(<CircleDetail />)} />

          <Route path="/student" element={student(<StudentHome />)} />
          <Route path="/student/schedule" element={student(<StudentSchedule />)} />
          <Route path="/student/attendance" element={student(<StudentAttendance />)} />
          <Route path="/student/exams" element={student(<StudentExams />)} />
          <Route path="/student/exams/:id" element={student(<ExamTake />)} />
          <Route path="/student/marks" element={student(<StudentMarks />)} />
          <Route path="/student/profile" element={student(<StudentProfile />)} />
          <Route path="/student/connect-guardian" element={student(<ConnectGuardian />, 'connect')} />

          <Route path="/parent" element={parent(<ParentOverview />)} />
          <Route path="/parent/family" element={parent(<ParentFamily />, 'connect')} />
          <Route path="/parent/profile" element={parent(<ParentProfile />)} />
          <Route path="/parent/verification" element={parent(<GuardianVerification />)} />
          <Route path="/parent/attendance" element={parent(<ParentAttendance />)} />
          <Route path="/parent/fees" element={parent(<ParentFees />, 'pay')} />
          <Route path="/parent/marks" element={parent(<ParentMarks />)} />
          <Route path="/parent/announcements" element={parent(<ParentAnnouncements />)} />

          <Route path="/institute" element={operator(<InstituteOverview />)} />
          <Route path="/institute/classes" element={operator(<InstituteClasses />)} />
          <Route path="/institute/students" element={operator(<InstituteStudents />)} />
          <Route path="/institute/attendance" element={operator(<InstituteAttendance />)} />
          <Route path="/institute/exams" element={operator(<InstituteExams />)} />
          <Route path="/institute/teachers" element={operator(<InstituteTeachers />)} />
          <Route path="/institute/fees" element={operator(<InstituteFees />)} />
          <Route path="/institute/announcements" element={operator(<InstituteAnnouncements />)} />

          <Route path="/teacher" element={teacher(<TeacherHome />)} />
          <Route path="/teacher/classes" element={teacher(<TeacherClasses />)} />
          <Route path="/teacher/classes/:id" element={teacher(<TeacherClassDetail />)} />
          <Route path="/teacher/attendance" element={teacher(<TeacherAttendance />)} />
          <Route path="/teacher/marks" element={teacher(<TeacherMarks />)} />
          <Route path="/teacher/exams" element={teacher(<TeacherExams />)} />
          <Route path="/teacher/exams/new" element={teacher(<ExamBuilder />)} />
          <Route path="/teacher/announcements" element={teacher(<TeacherAnnouncements />)} />
          <Route path="/teacher/payments" element={teacher(<TeacherPayments />)} />
          <Route path="/teacher/workspaces" element={teacher(<TeacherWorkspaces />)} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>);

}