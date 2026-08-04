import { createI18n } from 'vue-i18n'

const messages = {
  en: { login: 'Login', sites: 'Sites', sharedDashboard: 'Shared dashboard', dashboard: 'Dashboard', pages: 'Pages', referrers: 'Referrers', countries: 'Countries', devices: 'Devices', campaigns: 'Campaigns', goals: 'Goals', realtime: 'Last 30 minutes', settings: 'Settings', members: 'Members', apiKeys: 'API keys', auditLog: 'Audit log', approximate: 'Visits are approximate for hourly ranges.', timezone: 'Timezone' },
  'pt-BR': { login: 'Entrar', sites: 'Sites', sharedDashboard: 'Painel compartilhado', dashboard: 'Painel', pages: 'Páginas', referrers: 'Referências', countries: 'Países', devices: 'Dispositivos', campaigns: 'Campanhas', goals: 'Metas', realtime: 'Últimos 30 minutos', settings: 'Configurações', members: 'Membros', apiKeys: 'Chaves de API', auditLog: 'Registro de auditoria', approximate: 'As visitas são aproximadas em intervalos horários.', timezone: 'Fuso horário' },
}

export default createI18n({ legacy: false, locale: navigator.language === 'pt-BR' ? 'pt-BR' : 'en', fallbackLocale: 'en', messages })
