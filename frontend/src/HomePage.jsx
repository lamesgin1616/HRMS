import { ArrowRight, CalendarDays, ShieldCheck, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import './public-pages.css';

const highlights = [
  ['employeeManagement', Users],
  ['leaveRequests', CalendarDays],
  ['secureProfiles', ShieldCheck],
];

export default function HomePage({ onNavigate }) {
  const { t } = useTranslation();

  return (
    <main className="public-content home-page">
      <section className="home-hero">
        <span className="public-eyebrow">{t('universityName')}</span>
        <h1>{t('homeHeading')}</h1>
        <p>{t('homeIntroduction')}</p>
        <div className="home-hero__actions">
          <button className="primary" onClick={() => onNavigate('login')}>{t('signIn')} <ArrowRight size={16} /></button>
          <button className="secondary" onClick={() => onNavigate('register')}>{t('createAccount')}</button>
          <button className="home-dashboard-link" onClick={() => onNavigate('login')}>{t('dashboard')}</button>
        </div>
      </section>
      <section className="home-highlights" aria-labelledby="home-highlights-title">
        <div className="public-section-heading">
          <span className="public-eyebrow">{t('hrmsTitle')}</span>
          <h2 id="home-highlights-title">{t('homeHighlightsTitle')}</h2>
          <p>{t('homeHighlightsDescription')}</p>
        </div>
        <div className="home-highlights__grid">
          {highlights.map(([key, Icon]) => (
            <article className="home-highlight" key={key}>
              <span className="home-highlight__icon"><Icon size={21} aria-hidden="true" /></span>
              <h3>{t(`${key}Title`)}</h3>
              <p>{t(`${key}Description`)}</p>
            </article>
          ))}
        </div>
      </section>
    </main>
  );
}
