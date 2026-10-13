import React from 'react';
import { TeacherShell } from '../../components/teacher/TeacherShell';
import { MarksEntry } from '../../components/teacher/MarksEntry';
import { useTeacherScope } from '../../hooks/useTeacherScope';

export function TeacherMarks() {
  const { myClasses } = useTeacherScope();
  return (
    <TeacherShell
      title="Marks"
      subtitle="Enter marks for your classes. Drafts are private until you publish.">
      
      <div className="max-w-3xl">
        <MarksEntry classes={myClasses} />
      </div>
    </TeacherShell>);

}