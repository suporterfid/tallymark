<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

type Report = { data: { pageviews: number; visitors: number; sessions: number; bounces: number; duration_sum: number; breakdown: { key: string; pageviews: number; visitors: number }[] }; meta: { visitor_label: string; timezone: string } }
const { t, locale } = useI18n()
const screen = ref('dashboard')
const tenantId = ref('')
const siteId = ref('')
const from = ref(new Date().toISOString().slice(0, 10))
const to = ref(from.value)
const report = ref<Report | null>(null)
const error = ref('')
const screens = ['login', 'sites', 'dashboard', 'pages', 'referrers', 'countries', 'devices', 'campaigns', 'goals', 'realtime', 'settings', 'sharedDashboard', 'members', 'apiKeys', 'auditLog']
const title = computed(() => t(screen.value))
const number = (value: number) => new Intl.NumberFormat(locale.value).format(value)
const duration = (value: number) => new Intl.DateTimeFormat(locale.value, { timeZone: report.value?.meta.timezone ?? 'UTC', minute: '2-digit', second: '2-digit', timeZoneName: 'short' }).format(new Date(value * 1000))
async function load() { error.value = ''; try { const response = await fetch(`/api/v1/tenants/${tenantId.value}/sites/${siteId.value}/report?from=${from.value}&to=${to.value}&dimension=${screen.value}`); if (!response.ok) throw new Error(); report.value = await response.json() } catch { error.value = t('loadError') } }
</script>

<template>
  <main><header><h1>TallyMark</h1><label>{{ t('language') }}<select v-model="locale"><option value="en">{{ t('english') }}</option><option value="pt-BR">{{ t('portuguese') }}</option></select></label></header>
    <nav :aria-label="t('navigation')"><button v-for="item in screens" :key="item" :aria-current="screen === item ? 'page' : undefined" @click="screen = item">{{ t(item) }}</button></nav>
    <section><h2>{{ title }}</h2><form @submit.prevent="load"><label>{{ t('tenant') }}<input v-model="tenantId" required /></label><label>{{ t('site') }}<input v-model="siteId" required /></label><label>{{ t('from') }}<input v-model="from" type="date" required /></label><label>{{ t('to') }}<input v-model="to" type="date" required /></label><button>{{ t('load') }}</button></form><p v-if="error" role="alert">{{ error }}</p><template v-if="report"><p>{{ report.meta.visitor_label === 'visitors' ? t('exactVisitors') : t('approximate') }}</p><p>{{ t('timezone') }}: {{ report.meta.timezone }}</p><table><caption>{{ title }}</caption><thead><tr><th>{{ t('metric') }}</th><th>{{ t('value') }}</th></tr></thead><tbody><tr><td>{{ t('pageviews') }}</td><td>{{ number(report.data.pageviews) }}</td></tr><tr><td>{{ t('visitors') }}</td><td>{{ number(report.data.visitors) }}</td></tr><tr><td>{{ t('sessions') }}</td><td>{{ number(report.data.sessions) }}</td></tr><tr><td>{{ t('duration') }}</td><td>{{ duration(report.data.duration_sum) }}</td></tr></tbody></table><table v-if="report.data.breakdown.length"><caption>{{ t('breakdown') }}</caption><thead><tr><th>{{ t('dimension') }}</th><th>{{ t('pageviews') }}</th></tr></thead><tbody><tr v-for="row in report.data.breakdown" :key="row.key"><td>{{ row.key }}</td><td>{{ number(row.pageviews) }}</td></tr></tbody></table></template></section></main>
</template>
