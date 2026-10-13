import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

export const languages = [
  { id: 'en', name: 'English' },
  { id: 'yo', name: 'Yorùbá' },
  { id: 'ig', name: 'Igbo' },
  { id: 'ha', name: 'Hausa' },
  { id: 'fr', name: 'Français' },
] as const

export type Lang = (typeof languages)[number]['id']

const storageKey = 'tailormate_lang'

const english: Record<string, string> = {
  language: 'Language',
  'language.note':
    'English is the usual language. This changes the home screen, the tabs, and sign-in on this phone. Names, measurements, and notes stay as they were typed.',
  'nav.home': 'Home',
  'nav.clients': 'Clients',
  'nav.jobs': 'Jobs',
  'nav.finance': 'Finance',
  'nav.more': 'More',
  'login.title': 'Sign in',
  'login.lede': 'Use your {shop} account.',
  'login.email': 'Email',
  'login.password': 'Password',
  'login.submit': 'Sign in',
  'login.busy': 'Signing in…',
  'login.forgot': 'Forgot password?',
  'login.new': 'New shop?',
  'login.create': 'Create an account',
  'home.morning': 'Good morning',
  'home.afternoon': 'Good afternoon',
  'home.evening': 'Good evening',
  'home.organize': 'Let’s organize your shop.',
  'home.outstanding': 'Outstanding balances',
  'home.owed': 'Still owed across open jobs.',
  'home.active': 'Active jobs',
  'home.due': 'Due soon',
  'home.newCustomer': 'New customer',
  'home.newCustomerNote': 'Save details & measurements',
  'home.newJob': 'New job',
  'home.newJobNote': 'Record an outfit order',
  'home.payment': 'Record payment',
  'home.paymentNote': 'Deposit or final payment',
  'home.expense': 'Add expense',
  'home.expenseNote': 'Track shop spending',
  'home.upcoming': 'Upcoming jobs',
  'home.viewAll': 'View all',
  'home.week': 'Jobs due this week',
  'home.weekNote': 'Check delivery dates and fittings',
  'home.none': 'No upcoming jobs to display.',
  'home.customer': 'Customer',
  'customers.title': 'Customers',
  'customers.add': 'Add',
  'customers.search': 'Search',
  'customers.placeholder': 'Name or phone',
  'jobs.title': 'Jobs',
  'jobs.lede': 'Delivery dates across your shop.',
  'jobs.back': 'Back',
  'jobs.loading': 'Loading jobs…',
  'finance.title': 'Finance',
  'finance.add': 'Add expense',
  'finance.lede': 'Money in, money out, and what customers still owe.',
  'more.title': 'More',
  'more.lede': 'Shop settings, your account, and sign out.',
}

const catalogs: Record<Lang, Record<string, string>> = {
  en: english,
  yo: {
    ...english,
    language: 'Èdè',
    'language.note':
      'Gẹ̀ẹ́sì ni èdè tó wọ́pọ̀. Èyí yí ojú ilé, àwọn táàbù, àti ìwọlé pada lórí fóònù yìí. Orúkọ, ìwọ̀n, àti àkọsílẹ̀ dúró bí a ṣe kọ wọ́n.',
    'nav.home': 'Ilé',
    'nav.clients': 'Oníbàárà',
    'nav.jobs': 'Iṣẹ́',
    'nav.finance': 'Owó',
    'nav.more': 'Síi',
    'login.title': 'Wọlé',
    'login.lede': 'Lo àkántì {shop} rẹ.',
    'login.email': 'Ímeèlì',
    'login.password': 'Ọ̀rọ̀ aṣínà',
    'login.submit': 'Wọlé',
    'login.busy': 'Ó ń wọlé…',
    'login.forgot': 'Ṣe o gbàgbé ọ̀rọ̀ aṣínà?',
    'login.new': 'Ilé-iṣẹ́ tuntun?',
    'login.create': 'Ṣẹ̀dá àkántì',
    'home.morning': 'Káàárọ̀',
    'home.afternoon': 'Káàsán',
    'home.evening': 'Káalẹ́',
    'home.organize': 'Ẹ jẹ́ ká tò ilé-iṣẹ́ yín.',
    'home.outstanding': 'Owó tí wọ́n ṣì jẹ',
    'home.owed': 'Ohun tí àwọn iṣẹ́ ṣí ṣì jẹ.',
    'home.active': 'Iṣẹ́ tó ń lọ',
    'home.due': 'Tí ó sún mọ́',
    'home.newCustomer': 'Oníbàárà tuntun',
    'home.newCustomerNote': 'Fi àwọn àlàyé àti ìwọ̀n pamọ́',
    'home.newJob': 'Iṣẹ́ tuntun',
    'home.newJobNote': 'Kọ aṣọ tí wọ́n pè',
    'home.payment': 'Kọ ìsanwó',
    'home.paymentNote': 'Àbẹ̀wò tàbí ìsanwó ìkẹyìn',
    'home.expense': 'Ináwó',
    'home.expenseNote': 'Tọpinpin owó ilé-iṣẹ́',
    'home.upcoming': 'Iṣẹ́ tó ń bọ̀',
    'home.viewAll': 'Wo gbogbo rẹ̀',
    'home.week': 'Iṣẹ́ ọ̀sẹ̀ yìí',
    'home.weekNote': 'Yẹ ọjọ́ ìfijíṣẹ́ àti ìwọ̀n',
    'home.none': 'Kò sí iṣẹ́ tó ń bọ̀.',
    'home.customer': 'Oníbàárà',
    'customers.title': 'Oníbàárà',
    'customers.add': 'Fikún',
    'customers.search': 'Wá',
    'customers.placeholder': 'Orúkọ tàbí fóònù',
    'jobs.title': 'Iṣẹ́',
    'jobs.lede': 'Ọjọ́ ìfijíṣẹ́ ní ilé-iṣẹ́ rẹ.',
    'jobs.back': 'Padà',
    'jobs.loading': 'Ó ń kó iṣẹ́ wá…',
    'finance.title': 'Owó',
    'finance.add': 'Fikún ináwó',
    'finance.lede': 'Owó tí ó wọlé, owó tí ó jáde, àti ohun tí oníbàárà ṣì jẹ.',
    'more.title': 'Síi',
    'more.lede': 'Ètò ilé-iṣẹ́, àkántì rẹ, àti jáde.',
  },
  ig: {
    ...english,
    language: 'Asụsụ',
    'language.note':
      'Bekee bụ asụsụ a na-ahụkarị. Nke a na-agbanwe ihu ụlọ, taabụ, na mbanye n’ekwentị a. Aha, nha, na ndetu na-anọ ka e si dee ha.',
    'nav.home': 'Ụlọ',
    'nav.clients': 'Ndị ahịa',
    'nav.jobs': 'Ọrụ',
    'nav.finance': 'Ego',
    'nav.more': 'Ọzọ',
    'login.title': 'Banye',
    'login.lede': 'Jiri akaụntụ {shop} gị.',
    'login.email': 'Email',
    'login.password': 'Okwuntụghe',
    'login.submit': 'Banye',
    'login.busy': 'Na-abanye…',
    'login.forgot': 'Ị chefuru okwuntụghe?',
    'login.new': 'Ụlọ ọrụ ọhụrụ?',
    'login.create': 'Mepụta akaụntụ',
    'home.morning': 'Ụtụtụ ọma',
    'home.afternoon': 'Ehihie ọma',
    'home.evening': 'Mgbede ọma',
    'home.organize': 'Ka anyị hazie ụlọ ọrụ gị.',
    'home.outstanding': 'Ego fọdụrụ',
    'home.owed': 'Ihe ọrụ mepere emepe ka ji.',
    'home.active': 'Ọrụ na-aga',
    'home.due': 'Nke dị nso',
    'home.newCustomer': 'Onye ahịa ọhụrụ',
    'home.newCustomerNote': 'Chekwaa nkọwa na nha',
    'home.newJob': 'Ọrụ ọhụrụ',
    'home.newJobNote': 'Dee iwu uwe',
    'home.payment': 'Dee ụgwọ',
    'home.paymentNote': 'Ego mbido ma ọ bụ nke ikpeazụ',
    'home.expense': 'Mmefu',
    'home.expenseNote': 'Soro ego ụlọ ọrụ',
    'home.upcoming': 'Ọrụ na-abịa',
    'home.viewAll': 'Lee ha niile',
    'home.week': 'Ọrụ izu a',
    'home.weekNote': 'Lelee ụbọchị nnyefe na nha',
    'home.none': 'Enweghị ọrụ na-abịa.',
    'home.customer': 'Onye ahịa',
    'customers.title': 'Ndị ahịa',
    'customers.add': 'Tinye',
    'customers.search': 'Chọọ',
    'customers.placeholder': 'Aha ma ọ bụ ekwentị',
    'jobs.title': 'Ọrụ',
    'jobs.lede': 'Ụbọchị nnyefe n’ụlọ ọrụ gị.',
    'jobs.back': 'Laghachi',
    'jobs.loading': 'Na-ebudata ọrụ…',
    'finance.title': 'Ego',
    'finance.add': 'Tinye mmefu',
    'finance.lede': 'Ego batara, ego pụrụ, na ihe ndị ahịa ka ji.',
    'more.title': 'Ọzọ',
    'more.lede': 'Ntọala ụlọ ọrụ, akaụntụ gị, na ịpụ.',
  },
  ha: {
    ...english,
    language: 'Harshe',
    'language.note':
      'Turanci ne yaren da aka saba. Wannan yana canza allon gida, shafukan, da shiga a wannan wayar. Sunaye, ma’auni, da rubuce-rubuce suna ci gaba kamar yadda aka rubuta su.',
    'nav.home': 'Gida',
    'nav.clients': 'Abokan ciniki',
    'nav.jobs': 'Ayyuka',
    'nav.finance': 'Kuɗi',
    'nav.more': 'Ƙari',
    'login.title': 'Shiga',
    'login.lede': 'Yi amfani da asusun {shop}.',
    'login.email': 'Imel',
    'login.password': 'Kalmar sirri',
    'login.submit': 'Shiga',
    'login.busy': 'Ana shigowa…',
    'login.forgot': 'Ka manta kalmar sirri?',
    'login.new': 'Sabon shago?',
    'login.create': 'Ƙirƙiri asusu',
    'home.morning': 'Barka da safiya',
    'home.afternoon': 'Barka da rana',
    'home.evening': 'Barka da yamma',
    'home.organize': 'Mu tsara shagonku.',
    'home.outstanding': 'Bashin da ya rage',
    'home.owed': 'Abin da ayyuka a buɗe ke bin.',
    'home.active': 'Ayyuka masu gudana',
    'home.due': 'Wadanda ke kusa',
    'home.newCustomer': 'Sabon abokin ciniki',
    'home.newCustomerNote': 'Ajiye bayanai da ma’auni',
    'home.newJob': 'Sabon aiki',
    'home.newJobNote': 'Rubuta odar tufafi',
    'home.payment': 'Rubuta biyan kuɗi',
    'home.paymentNote': 'Jarin kuɗi ko biyan ƙarshe',
    'home.expense': 'Kuɗin kashewa',
    'home.expenseNote': 'Bi kuɗin shago',
    'home.upcoming': 'Ayyukan da ke zuwa',
    'home.viewAll': 'Duba duka',
    'home.week': 'Ayyukan wannan mako',
    'home.weekNote': 'Duba ranakun isarwa da gwajin tufafi',
    'home.none': 'Babu ayyukan da ke zuwa.',
    'home.customer': 'Abokin ciniki',
    'customers.title': 'Abokan ciniki',
    'customers.add': 'Ƙara',
    'customers.search': 'Nemo',
    'customers.placeholder': 'Suna ko waya',
    'jobs.title': 'Ayyuka',
    'jobs.lede': 'Ranakun isarwa a shagonka.',
    'jobs.back': 'Komo',
    'jobs.loading': 'Ana loda ayyuka…',
    'finance.title': 'Kuɗi',
    'finance.add': 'Ƙara kashewa',
    'finance.lede': 'Kuɗin shiga, kuɗin fita, da abin da abokan ciniki ke bin.',
    'more.title': 'Ƙari',
    'more.lede': 'Saitin shago, asusunka, da fita.',
  },
  fr: {
    ...english,
    language: 'Langue',
    'language.note':
      'L’anglais est la langue habituelle. Ce choix change l’accueil, les onglets et la connexion sur ce téléphone. Les noms, mesures et notes restent tels qu’ils ont été saisis.',
    'nav.home': 'Accueil',
    'nav.clients': 'Clients',
    'nav.jobs': 'Commandes',
    'nav.finance': 'Finances',
    'nav.more': 'Plus',
    'login.title': 'Connexion',
    'login.lede': 'Utilisez le compte {shop}.',
    'login.email': 'E-mail',
    'login.password': 'Mot de passe',
    'login.submit': 'Se connecter',
    'login.busy': 'Connexion…',
    'login.forgot': 'Mot de passe oublié ?',
    'login.new': 'Nouvel atelier ?',
    'login.create': 'Créer un compte',
    'home.morning': 'Bonjour',
    'home.afternoon': 'Bon après-midi',
    'home.evening': 'Bonsoir',
    'home.organize': 'Organisons votre atelier.',
    'home.outstanding': 'Soldes dus',
    'home.owed': 'Reste à payer sur les commandes ouvertes.',
    'home.active': 'Commandes en cours',
    'home.due': 'Bientôt dues',
    'home.newCustomer': 'Nouveau client',
    'home.newCustomerNote': 'Enregistrer les détails et les mesures',
    'home.newJob': 'Nouvelle commande',
    'home.newJobNote': 'Noter une commande de tenue',
    'home.payment': 'Enregistrer un paiement',
    'home.paymentNote': 'Acompte ou solde',
    'home.expense': 'Ajouter une dépense',
    'home.expenseNote': 'Suivre les dépenses de l’atelier',
    'home.upcoming': 'Commandes à venir',
    'home.viewAll': 'Tout voir',
    'home.week': 'Commandes de la semaine',
    'home.weekNote': 'Vérifier les dates de livraison et les essayages',
    'home.none': 'Aucune commande à venir.',
    'home.customer': 'Client',
    'customers.title': 'Clients',
    'customers.add': 'Ajouter',
    'customers.search': 'Recherche',
    'customers.placeholder': 'Nom ou téléphone',
    'jobs.title': 'Commandes',
    'jobs.lede': 'Dates de livraison de l’atelier.',
    'jobs.back': 'Retour',
    'jobs.loading': 'Chargement des commandes…',
    'finance.title': 'Finances',
    'finance.add': 'Ajouter une dépense',
    'finance.lede': 'Entrées, sorties, et ce que les clients doivent encore.',
    'more.title': 'Plus',
    'more.lede': 'Réglages de l’atelier, votre compte, et déconnexion.',
  },
}

type I18nValue = {
  lang: Lang
  setLang: (lang: Lang) => void
  t: (key: string, vars?: Record<string, string>) => string
}

const I18nContext = createContext<I18nValue | null>(null)

function storedLang(): Lang {
  const saved = localStorage.getItem(storageKey)
  return languages.some((item) => item.id === saved) ? (saved as Lang) : 'en'
}

export function LanguageProvider({ children }: { children: ReactNode }) {
  const [lang, setLang] = useState<Lang>(storedLang)

  useEffect(() => {
    localStorage.setItem(storageKey, lang)
    document.documentElement.lang = lang
  }, [lang])

  const value = useMemo<I18nValue>(
    () => ({
      lang,
      setLang,
      t: (key, vars) => {
        let text = catalogs[lang][key] ?? catalogs.en[key] ?? key
        if (vars) {
          for (const [name, replacement] of Object.entries(vars)) {
            text = text.replaceAll(`{${name}}`, replacement)
          }
        }
        return text
      },
    }),
    [lang],
  )

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
}

export function useI18n(): I18nValue {
  const value = useContext(I18nContext)
  if (!value) {
    throw new Error('Language is not available.')
  }
  return value
}

export function LanguageSelect() {
  const { lang, setLang, t } = useI18n()

  return (
    <label>
      {t('language')}
      <select
        value={lang}
        onChange={(event) => setLang(event.target.value as Lang)}
      >
        {languages.map((item) => (
          <option key={item.id} value={item.id}>
            {item.name}
          </option>
        ))}
      </select>
    </label>
  )
}
