import { useTranslation } from 'react-i18next';
import './language-switcher.css';

const languages = [
  { code: 'en', label: 'English', flag: '🇬🇧' },
  { code: 'am', label: 'አማርኛ', flag: '🇪🇹' },
];

export default function LanguageSwitcher() {
  const { i18n, t } = useTranslation();
  const activeLanguage = i18n.resolvedLanguage || i18n.language;

  return (
    <div aria-label={t('signInLanguage')} role="group" className="language-switcher">
      {languages.map(({ code, label, flag }) => {
        const active = activeLanguage === code;
        return (
          <button
            key={code}
            type="button"
            aria-pressed={active}
            aria-label={`${label}`}
            onClick={() => i18n.changeLanguage(code)}
            className={`language-switcher__button${active ? ' is-active' : ''}`}
          >
            <span aria-hidden="true" className="language-switcher__flag">{flag}</span>
            {label}
          </button>
        );
      })}
    </div>
  );
}
