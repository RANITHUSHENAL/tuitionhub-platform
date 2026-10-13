import React from 'react';
import {
  BanknoteIcon,
  BarChart3Icon,
  BookOpenIcon,
  BriefcaseIcon,
  CalendarIcon,
  ClipboardCheckIcon,
  FileQuestionIcon,
  GaugeIcon,
  GraduationCapIcon,
  HomeIcon,
  LayersIcon,
  MegaphoneIcon,
  MessagesSquareIcon,
  PencilLineIcon,
  UserIcon,
  UserRoundCogIcon,
  UsersIcon } from
'lucide-react';
import type { ShellNavItem } from './AppShell';
import type { TeachingWorkspace } from '../../types';

const icon = (I: React.ElementType) => <I className="h-4 w-4" />;

export const parentNav: ShellNavItem[] = [
{ to: '/parent', label: 'Overview', icon: icon(GaugeIcon), end: true },
{ to: '/parent/family', label: 'Family', icon: icon(HomeIcon) },
{ to: '/parent/attendance', label: 'Attendance', icon: icon(ClipboardCheckIcon) },
{ to: '/parent/fees', label: 'Fees & payments', icon: icon(BanknoteIcon) },
{ to: '/parent/marks', label: 'Marks', icon: icon(GraduationCapIcon) },
{ to: '/parent/announcements', label: 'Announcements', icon: icon(MegaphoneIcon) },
{ to: '/parent/profile', label: 'Profile & verification', icon: icon(UserIcon) }];


export const studentNav: ShellNavItem[] = [
{ to: '/student', label: 'Home', icon: icon(GaugeIcon), end: true },
{ to: '/student/schedule', label: 'Schedule', icon: icon(CalendarIcon) },
{ to: '/student/attendance', label: 'Attendance', icon: icon(ClipboardCheckIcon) },
{ to: '/student/exams', label: 'Exams', icon: icon(FileQuestionIcon) },
{ to: '/student/marks', label: 'Results', icon: icon(GraduationCapIcon) },
{ to: '/study', label: 'Study', icon: icon(UsersIcon) },
{ to: '/messages', label: 'Messages', icon: icon(MessagesSquareIcon) },
{ to: '/student/profile', label: 'Profile', icon: icon(UserIcon) }];


/**
 * Institute owners work at the whole-institute level. Attendance and exams are
 * oversight here — the working tools live with the teacher of each class.
 */
export const instituteNav: ShellNavItem[] = [
{ to: '/institute', label: 'Dashboard', icon: icon(GaugeIcon), end: true },
{ to: '/institute/classes', label: 'Classes', icon: icon(LayersIcon) },
{ to: '/institute/students', label: 'Students', icon: icon(UsersIcon) },
{ to: '/institute/teachers', label: 'Teachers', icon: icon(UserRoundCogIcon) },
{ to: '/institute/fees', label: 'Fees & payments', icon: icon(BanknoteIcon) },
{ to: '/institute/attendance', label: 'Attendance overview', icon: icon(BarChart3Icon), group: 'More' },
{ to: '/institute/exams', label: 'Exams & academic', icon: icon(BookOpenIcon), group: 'More' },
{ to: '/institute/announcements', label: 'Announcements', icon: icon(MegaphoneIcon), group: 'More' }];


/** Teacher navigation is always "my" scope. Payments only for independent teachers. */
export function teacherNavFor(workspace: TeachingWorkspace | null): ShellNavItem[] {
  const items: ShellNavItem[] = [
  { to: '/teacher', label: 'Home', icon: icon(GaugeIcon), end: true },
  { to: '/teacher/classes', label: 'My classes', icon: icon(LayersIcon) },
  { to: '/teacher/attendance', label: 'Attendance', icon: icon(ClipboardCheckIcon) },
  { to: '/teacher/marks', label: 'Marks', icon: icon(PencilLineIcon) },
  { to: '/teacher/exams', label: 'Exams', icon: icon(FileQuestionIcon) },
  { to: '/teacher/announcements', label: 'Announcements', icon: icon(MegaphoneIcon) }];

  if (workspace?.kind === 'independent') {
    items.push({ to: '/teacher/payments', label: 'Payments', icon: icon(BanknoteIcon) });
  }
  items.push({ to: '/teacher/workspaces', label: 'Workspaces', icon: icon(BriefcaseIcon) });
  return items;
}