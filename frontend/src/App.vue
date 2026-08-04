<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

type Report = { data: { pageviews: number; visitors: number; sessions: number; bounces: number; duration_sum: number; views_per_session: number; bounce_rate: number; average_session_duration: number; breakdown: { key: string; pageviews: number; visitors: number }[] }; comparison: { pageviews: number; visitors: number; sessions: number }; meta: { visitor_label: string; timezone: string } }
const { t, locale } = useI18n()
const screen = ref('dashboard')
const tenantId = ref('')
const siteId = ref('')
const from = ref(new Date().toISOString().slice(0, 10))
const to = ref(from.value)
const report = ref<Report | null>(null)
const error = ref('')
const screens = ['login', 'sites', 'dashboard', 'pages', 'referrers', 'countries', 'devices', 'campaigns', 'goals', 'realtime', 'settings', 'sharedDashboard', 'members', 'apiKeys', 'auditLog']
const dimensions = ['pages', 'referrers', 'countries', 'devices', 'campaigns', 'goals']
const reportScreen = computed(() => ['dashboard', 'realtime', ...dimensions].includes(screen.value))
const title = computed(() => t(screen.value))
const number = (value: number) => new Intl.NumberFormat(locale.value).format(value)
const duration = (value: number) => `${Math.floor(value / 60)} ${t('minutes')} ${Math.round(value % 60)} ${t('seconds')}`
async function load() { error.value = ''; try { const dimension = dimensions.includes(screen.value) ? `&dimension=${screen.value}` : ''; const response = await fetch(`/api/v1/tenants/${tenantId.value}/sites/${siteId.value}/report?from=${from.value}&to=${to.value}${dimension}`); if (!response.ok) throw new Error(); report.value = await response.json() } catch { error.value = t('loadError') } }
</script>

<template>
  <main><header><h1>TallyMark</h1><label>{{ t('language') }}<select v-model="locale"><option value="en">{{ t('english') }}</option><option value="pt-BR">{{ t('portuguese') }}</option></select></label></header>
    <nav :aria-label="t('navigation')"><button v-for="item in screens" :key="item" :aria-current="screen === item ? 'page' : undefined" @click="screen = item">{{ t(item) }}</button></nav>
    <section><h2>{{ title }}</h2><template v-if="reportScreen"><form @submit.prevent="load"><label>{{ t('tenant') }}<input v-model="tenantId" required /></label><label>{{ t('site') }}<input v-model="siteId" required /></label><label>{{ t('from') }}<input v-model="from" type="date" required /></label><label>{{ t('to') }}<input v-model="to" type="date" required /></label><button>{{ t('load') }}</button></form><p v-if="error" role="alert">{{ error }}</p><template v-if="report"><p>{{ report.meta.visitor_label === 'visitors' ? t('exactVisitors') : t('approximate') }}</p><p>{{ t('timezone') }}: {{ report.meta.timezone }}</p><table><caption>{{ title }}</caption><thead><tr><th>{{ t('metric') }}</th><th>{{ t('value') }}</th><th>{{ t('previousPeriod') }}</th></tr></thead><tbody><tr><td>{{ t('pageviews') }}</td><td>{{ number(report.data.pageviews) }}</td><td>{{ number(report.comparison.pageviews) }}</td></tr><tr><td>{{ t('visitors') }}</td><td>{{ number(report.data.visitors) }}</td><td>{{ number(report.comparison.visitors) }}</td></tr><tr><td>{{ t('sessions') }}</td><td>{{ number(report.data.sessions) }}</td><td>{{ number(report.comparison.sessions) }}</td></tr><tr><td>{{ t('bounceRate') }}</td><td>{{ number(report.data.bounce_rate) }}%</td><td>—</td></tr><tr><td>{{ t('viewsPerSession') }}</td><td>{{ number(report.data.views_per_session) }}</td><td>—</td></tr><tr><td>{{ t('averageDuration') }}</td><td>{{ duration(report.data.average_session_duration) }}</td><td>—</td></tr></tbody></table><table v-if="report.data.breakdown.length"><caption>{{ t('breakdown') }}</caption><thead><tr><th>{{ t('dimension') }}</th><th>{{ t('pageviews') }}</th></tr></thead><tbody><tr v-for="row in report.data.breakdown" :key="row.key"><td>{{ row.key }}</td><td>{{ number(row.pageviews) }}</td></tr></tbody></table></template></template><template v-else><p>{{ t('manageScreen') }}</p><form><label>{{ t('name') }}<input /></label><button type="button">{{ t('save') }}</button></form></template></section></main>
</template>
