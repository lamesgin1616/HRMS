import { Mail, MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import mauLogo from './assets/mau-logo.jpg';
import './footer.css';

const campusMapUrl = 'https://www.google.com/maps/search/?api=1&query=Mekdela+Amba+University+Tulu+Awulia+Main+Campus';

export default function Footer() {
  const { t } = useTranslation();

  return (
    <footer className="contact-footer">
      <div className="contact-footer__identity">
        <img className="university-logo university-logo--footer" src={mauLogo} alt={t('institutionName')} />
        <div className="contact-footer__identity-copy">
          <strong>{t('institutionName')}</strong>
          <span>{t('hrmsTitle')}</span>
        </div>
      </div>
      <div className="contact-footer__details">
        <div className="contact-footer__item">
          <MapPin size={16} aria-hidden="true" />
          <div>
            <strong>{t('addressLabel')}</strong>
            <span>{t('campusAddress')}</span>
          </div>
        </div>
        <div className="contact-footer__item">
          <Mail size={16} aria-hidden="true" />
          <div>
            <strong>{t('contactLabel')}</strong>
            <a href={`mailto:${t('contactEmail')}`}>{t('contactEmail')}</a>
          </div>
        </div>
      </div>
      <a className="contact-footer__map" href={campusMapUrl} target="_blank" rel="noreferrer">
        {t('findUs')} ↗
      </a>
      <small className="contact-footer__copyright">
        © {new Date().getFullYear()} {t('institutionName')}
      </small>
    </footer>
  );
}
