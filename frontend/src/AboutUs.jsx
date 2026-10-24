import { useTranslation } from 'react-i18next';
import './public-pages.css';

export default function AboutUs() {
  const { t } = useTranslation();
  const objectives = ['objectiveOne', 'objectiveTwo', 'objectiveThree'];

  return (
    <main className="public-content about-page">
      <section className="about-intro">
        <span className="public-eyebrow">{t('aboutNav')}</span>
        <h1>{t('aboutHeading')}</h1>
        <p>{t('aboutIntroduction')}</p>
      </section>
      <section className="about-location">
        <div>
          <h2>{t('universityName')}</h2>
          <p>{t('aboutUniversityBackground')}</p>
        </div>
        <address>
          <strong>{t('contactLabel')}</strong>
          <span>{t('campusAddress')}</span>
          <a href={`mailto:${t('contactEmail')}`}>{t('contactEmail')}</a>
        </address>
      </section>
      <section className="about-values">
        <article>
          <h2>{t('hrmsMissionTitle')}</h2>
          <p>{t('hrmsMission')}</p>
        </article>
        <article>
          <h2>{t('hrmsVisionTitle')}</h2>
          <p>{t('hrmsVision')}</p>
        </article>
      </section>
      <section className="about-objectives">
        <h2>{t('hrmsObjectivesTitle')}</h2>
        <ul>{objectives.map(objective => <li key={objective}>{t(objective)}</li>)}</ul>
      </section>
    </main>
  );
}
