import React from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { RequireAuth } from './components/auth/RequireAuth';
import { RoleHomeRedirect } from './components/auth/RoleHomeRedirect';
import { Splash } from './components/site/Splash';
import { Home } from './pages/Home';
import { Search } from './pages/Search';
import { ClassDetail } from './pages/ClassDetail';
import { Institutes } from './pages/Institutes';
import { Teachers } from './pages/Teachers';
import { Blueprint } from './pages/Blueprint';
import { DesignSystem } from './pages/DesignSystem';
import { TeacherHome } from './pages/teacher/TeacherHome';
import { TeacherClasses } from './pages/teacher/TeacherClasses';
import { TeacherClassDetail } from './pages/teacher/TeacherClassDetail';
import { TeacherAttendance } from './pages/teacher/TeacherAttendance';
import { TeacherAnnouncements } from './pages/teacher/TeacherAnnouncements';
import { TeacherPayments } from './pages/teacher/TeacherPayments';
import { TeacherWorkspaces } from './pages/teacher/TeacherWorkspaces';
import { InstituteTeachers } from './pages/institute/InstituteTeachers';
import { Auth } from './pages/auth/Auth';
import { Onboarding } from './pages/auth/Onboarding';
import { AccountHome } from './pages/auth/AccountHome';
import { ParentOverview } from './pages/parent/ParentOverview';
import { ParentFamily } from './pages/parent/ParentFamily';
import { ParentProfile } from './pages/parent/ParentProfile';
import { ParentAttendance } from './pages/parent/ParentAttendance';
import { ParentFees } from './pages/parent/ParentFees';
import { ParentAnnouncements } from './pages/parent/ParentAnnouncements';
import { StudentHome } from './pages/student/StudentHome';
import { StudentSchedule } from './pages/student/StudentSchedule';
import { StudentAttendance } from './pages/student/StudentAttendance';
import { StudentProfile } from './pages/student/StudentProfile';
import { InstituteOverview } from './pages/institute/InstituteOverview';
import { InstituteClasses } from './pages/institute/InstituteClasses';
import { InstituteStudents } from './pages/institute/InstituteStudents';
import { InstituteAttendance } from './pages/institute/InstituteAttendance';
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
          <Route path="/blueprint" element={<Blueprint />} />
          <Route path="/design-system" element={<DesignSystem />} />

          {/* Authentication */}
          <Route path="/auth" element={<Auth />} />
          {/* The prototype signup screens accept any code, so they are not routed (files kept for
              later). Signing in with a new number creates the account. */}
          <Route path="/auth/student" element={<Navigate to="/auth" replace />} />
          <Route path="/auth/parent" element={<Navigate to="/auth" replace />} />
          <Route path="/auth/teacher" element={<Navigate to="/auth" replace />} />
          <Route path="/onboarding" element={<Onboarding />} />
          <Route path="/account" element={<AccountHome />} />

          {/* Study and Messages are out of sprint scope (C7): files kept, direct URLs go home. */}
          <Route path="/study/*" element={<Navigate to="/" replace />} />
          <Route path="/messages/*" element={<Navigate to="/" replace />} />

          {/* Exams/Marks (proposed F2c choice, awaiting founder confirmation): the legacy screens
              are kept as files, but every URL, including nested ones, goes to the user's role home. */}
          <Route path="/student/exams/*" element={<RoleHomeRedirect />} />
          <Route path="/student/marks/*" element={<RoleHomeRedirect />} />
          <Route path="/parent/marks/*" element={<RoleHomeRedirect />} />
          <Route path="/institute/exams/*" element={<RoleHomeRedirect />} />
          <Route path="/teacher/marks/*" element={<RoleHomeRedirect />} />
          <Route path="/teacher/exams/*" element={<RoleHomeRedirect />} />

          {/* C6: parents link by an institute invitation code; there is no NIC/passport or face
              check, so the legacy guardian-verification screen is retired (file kept). */}
          <Route path="/parent/verification/*" element={<Navigate to="/parent" replace />} />

          <Route path="/student" element={student(<StudentHome />)} />
          <Route path="/student/schedule" element={student(<StudentSchedule />)} />
          <Route path="/student/attendance" element={student(<StudentAttendance />)} />
          <Route path="/student/profile" element={student(<StudentProfile />)} />
          {/* C6: the institute registers students and parents link by invitation code, so the
              student-initiated linking screen is retired (file kept). */}
          <Route path="/student/connect-guardian" element={<Navigate to="/student" replace />} />

          <Route path="/parent" element={parent(<ParentOverview />)} />
          <Route path="/parent/family" element={parent(<ParentFamily />, 'connect')} />
          <Route path="/parent/profile" element={parent(<ParentProfile />)} />
          <Route path="/parent/attendance" element={parent(<ParentAttendance />)} />
          <Route path="/parent/fees" element={parent(<ParentFees />, 'pay')} />
          <Route path="/parent/announcements" element={parent(<ParentAnnouncements />)} />

          <Route path="/institute" element={operator(<InstituteOverview />)} />
          <Route path="/institute/classes" element={operator(<InstituteClasses />)} />
          <Route path="/institute/students" element={operator(<InstituteStudents />)} />
          <Route path="/institute/attendance" element={operator(<InstituteAttendance />)} />
          <Route path="/institute/teachers" element={operator(<InstituteTeachers />)} />
          <Route path="/institute/fees" element={operator(<InstituteFees />)} />
          <Route path="/institute/announcements" element={operator(<InstituteAnnouncements />)} />

          <Route path="/teacher" element={teacher(<TeacherHome />)} />
          <Route path="/teacher/classes" element={teacher(<TeacherClasses />)} />
          <Route path="/teacher/classes/:id" element={teacher(<TeacherClassDetail />)} />
          <Route path="/teacher/attendance" element={teacher(<TeacherAttendance />)} />
          <Route path="/teacher/announcements" element={teacher(<TeacherAnnouncements />)} />
          <Route path="/teacher/payments" element={teacher(<TeacherPayments />)} />
          <Route path="/teacher/workspaces" element={teacher(<TeacherWorkspaces />)} />

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>);

}