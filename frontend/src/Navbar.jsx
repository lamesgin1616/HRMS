import { useTranslation } from 'react-i18next';
import mauLogo from './assets/mau-logo.jpg';
import LanguageSwitcher from './LanguageSwitcher.jsx';
import './public-pages.css';

const links = [
  ['home', 'homeNav'],
  ['about', 'aboutNav'],
  ['login', 'signIn'],
  ['register', 'register'],
];

export default function Navbar({ currentPage, onNavigate }) {
  const { t } = useTranslation();

  return (
    <header className="public-navbar">
      <a className="public-navbar__brand" href="#home" onClick={event => { event.preventDefault(); onNavigate('home'); }}>
        <img src={mauLogo} alt="" />
        <span>{t('institutionName')}</span>
      </a>
      <nav className="public-navbar__links" aria-label={t('mainNavigation')}>
        {links.map(([route, key]) => (
          <a
            key={route}
            href={`#${route}`}
            aria-current={currentPage === route ? 'page' : undefined}
            className={currentPage === route ? 'is-active' : ''}
            onClick={event => { event.preventDefault(); onNavigate(route); }}
          >
            {t(key)}
          </a>
        ))}
      </nav>
      <LanguageSwitcher />
    </header>
  );
}
