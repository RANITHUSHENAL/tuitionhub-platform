import { useAuth } from '../contexts/AuthContext';
import { teacherClasses, teacherSessions } from '../data/teaching';
import { exams } from '../data/exams';

/**
 * Everything a teacher sees is filtered to the active workspace and the
 * classes assigned to them there — never the whole institute.
 */
export function useTeacherScope() {
  const { user, activeWorkspace } = useAuth();
  // No approved workspace yet → no classes. Nothing leaks from a pending institute.
  const wsId = activeWorkspace?.id ?? '';
  const myClasses = teacherClasses.filter((c) => c.workspaceId === wsId);
  const classIds = myClasses.map((c) => c.id);
  const mySessions = teacherSessions.filter((s) => classIds.includes(s.classId));
  const classNames = myClasses.map((c) => c.name);
  const myExams = exams.filter((e) => classNames.includes(e.className));
  const firstName = (user?.name ?? 'Nimal Perera').replace(/^(Mr\.|Mrs\.|Ms\.|Dr\.)\s*/, '').split(' ')[0];

  return {
    user,
    workspace: activeWorkspace,
    isIndependent: activeWorkspace?.kind === 'independent',
    myClasses,
    mySessions,
    myExams,
    firstName
  };
}