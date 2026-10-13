import React from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { roleHome } from '../data/accounts';
import { SiteHeader } from '../components/site/SiteHeader';
import { SiteFooter } from '../components/site/SiteFooter';
import { Hero } from '../components/home/Hero';
import { EnrollingSection } from '../components/home/EnrollingSection';
import { FindClassSection } from '../components/home/FindClassSection';
import { ExploreSection } from '../components/home/ExploreSection';
import { EcosystemSection } from '../components/home/EcosystemSection';
import { ParentSection } from '../components/home/ParentSection';
import { StudentSection } from '../components/home/StudentSection';
import { TeacherSection } from '../components/home/TeacherSection';
import { InstituteSection } from '../components/home/InstituteSection';
import { StudySection } from '../components/home/StudySection';
import { FinalCtaSection } from '../components/home/FinalCtaSection';

/**
 * Public, guest-first landing. Story: Discover → Choose → Understand →
 * Manage → Teach → Study. Signed-in users go to their own home.
 */
export function Home() {
  const { user } = useAuth();
  if (user) return <Navigate to={roleHome[user.role]} replace />;

  return (
    <div className="min-h-screen bg-canvas">
      <SiteHeader />
      <main>
        <Hero />
        <EnrollingSection />
        <FindClassSection />
        <ExploreSection />
        <EcosystemSection />
        <ParentSection />
        <StudentSection />
        <TeacherSection />
        <InstituteSection />
        <StudySection />
        <FinalCtaSection />
      </main>
      <SiteFooter />
    </div>);

}