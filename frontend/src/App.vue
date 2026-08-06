<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

type Report = { data: { pageviews: number; visitors: number; sessions: number; bounces: number; duration_sum: number; views_per_session: number; bounce_rate: number; average_session_duration: number; breakdown_metric: string; breakdown: { key: string; pageviews?: number; conversions?: number; visitors: number; conversion_rate?: number }[] }; comparison: { pageviews: number; visitors: number; sessions: number }; meta: { visitor_label: string; comparison_visitor_label: string; timezone: string; operational: { ingest: { fresh: boolean; last_seen_at: string | null }; cardinality_warning: boolean; shed_events: number } } }
const { t, locale } = useI18n()
const screen = ref('dashboard')
const tenantId = ref('')
const siteId = ref('')
const from = ref(new Date().toISOString().slice(0, 10))
const to = ref(from.value)
const report = ref<Report | null>(null)
const realtime = ref<{ bucket: string; pageviews: number; events: number; visitors: number }[]>([])
const realtimeTimezone = ref('UTC')
const error = ref('')
const screens = ['login', 'sites', 'dashboard', 'pages', 'referrers', 'countries', 'devices', 'campaigns', 'goals', 'realtime', 'settings', 'sharedDashboard', 'members', 'apiKeys', 'auditLog']
const dimensions = ['pages', 'referrers', 'countries', 'devices', 'campaigns', 'goals']
const reportScreen = computed(() => ['dashboard', 'realtime', ...dimensions].includes(screen.value))
const title = computed(() => t(screen.value))
const number = (value: number) => new Intl.NumberFormat(locale.value).format(value)
const duration = (value: number) => `${Math.floor(value / 60)} ${t('minutes')} ${Math.round(value % 60)} ${t('seconds')}`
const bucket = (value: string) => new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short', timeZone: realtimeTimezone.value }).format(new Date(value))
let syncingLocale = false

onMounted(async () => {
  try {
    const response = await fetch('/api/v1/me')
    if (response.ok) {
      const payload = await response.json()
      syncingLocale = true
      locale.value = payload.data.locale
      syncingLocale = false
    }
  } catch {
    // Not logged in yet, or request failed — keep the navigator-based default.
  }
})

watch(locale, async (next) => {
  if (syncingLocale) return
  try {
    await fetch('/api/v1/me/locale', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ locale: next }),
    })
  } catch {
    // Best-effort — the switch already took effect locally.
  }
})

async function load() { error.value = ''; try { if (screen.value === 'realtime') { const response = await fetch(`/api/v1/tenants/${tenantId.value}/sites/${siteId.value}/realtime?until=${encodeURIComponent(new Date().toISOString())}`); if (!response.ok) throw new Error(); const payload = await response.json(); realtime.value = payload.data; realtimeTimezone.value = payload.meta.timezone; return } const dimension = dimensions.includes(screen.value) ? `&dimension=${screen.value}` : ''; const response = await fetch(`/api/v1/tenants/${tenantId.value}/sites/${siteId.value}/report?from=${from.value}&to=${to.value}${dimension}`); if (!response.ok) throw new Error(); report.value = await response.json() } catch { error.value = t('loadError') } }
</script>

<template>
  <main><header><h1>TallyMark</h1><label>{{ t('language') }}<select v-model="locale"><option value="en">{{ t('english') }}</option><option value="pt-BR">{{ t('portuguese') }}</option></select></label></header>
    <nav :aria-label="t('navigation')"><button v-for="item in screens" :key="item" :aria-current="screen === item ? 'page' : undefined" @click="screen = item">{{ t(item) }}</button></nav>
    <section><h2>{{ title }}</h2><template v-if="reportScreen"><form @submit.prevent="load"><label>{{ t('tenant') }}<input v-model="tenantId" required /></label><label>{{ t('site') }}<input v-model="siteId" required /></label><label>{{ t('from') }}<input v-model="from" type="date" required /></label><label>{{ t('to') }}<input v-model="to" type="date" required /></label><button>{{ t('load') }}</button></form><p v-if="error" role="alert">{{ error }}</p><template v-if="screen === 'realtime'"><p>{{ t('realtimeLag') }}</p><p>{{ t('realtimeBucketApproximation') }}</p><p>{{ t('timezone') }}: {{ realtimeTimezone }}</p><table v-if="realtime.length"><caption>{{ t('realtime') }}</caption><thead><tr><th>{{ t('bucket') }}</th><th>{{ t('pageviews') }}</th><th>{{ t('events') }}</th></tr></thead><tbody><tr v-for="row in realtime" :key="row.bucket"><td>{{ bucket(row.bucket) }}</td><td>{{ number(row.pageviews) }}</td><td>{{ number(row.events) }}</td></tr></tbody></table></template><template v-else-if="report"><p>{{ report.meta.visitor_label === 'visitors' ? t('exactVisitors') : t('approximate') }}</p><p>{{ report.meta.comparison_visitor_label === 'visitors' ? t('comparisonExactVisitors') : t('comparisonApproximate') }}</p><p>{{ t('timezone') }}: {{ report.meta.timezone }}</p><p>{{ report.meta.operational.ingest.fresh ? t('ingestFresh') : t('ingestStale') }}</p><p v-if="report.meta.operational.shed_events" role="alert">{{ t('shedWarning', { count: number(report.meta.operational.shed_events) }) }}</p><p v-if="report.meta.operational.cardinality_warning" role="alert">{{ t('cardinalityWarning') }}</p><table><caption>{{ title }}</caption><thead><tr><th>{{ t('metric') }}</th><th>{{ t('value') }}</th><th>{{ t('previousPeriod') }}</th></tr></thead><tbody><tr><td>{{ t('pageviews') }}</td><td>{{ number(report.data.pageviews) }}</td><td>{{ number(report.comparison.pageviews) }}</td></tr><tr><td>{{ t('visitors') }}</td><td>{{ number(report.data.visitors) }}</td><td>{{ number(report.comparison.visitors) }}</td></tr><tr><td>{{ t('sessions') }}</td><td>{{ number(report.data.sessions) }}</td><td>{{ number(report.comparison.sessions) }}</td></tr><tr><td>{{ t('bounceRate') }}</td><td>{{ number(report.data.bounce_rate) }}%</td><td>—</td></tr><tr><td>{{ t('viewsPerSession') }}</td><td>{{ number(report.data.views_per_session) }}</td><td>—</td></tr><tr><td>{{ t('averageDuration') }}</td><td>{{ duration(report.data.average_session_duration) }}</td><td>—</td></tr></tbody></table><table v-if="report.data.breakdown.length"><caption>{{ t('breakdown') }}</caption><thead><tr><th>{{ t('dimension') }}</th><th>{{ report.data.breakdown_metric === 'conversions' ? t('conversions') : t('pageviews') }}</th><th v-if="report.data.breakdown_metric === 'conversions'">{{ t('conversionRate') }}</th></tr></thead><tbody><tr v-for="row in report.data.breakdown" :key="row.key"><td>{{ row.key }}</td><td>{{ number(row.conversions ?? row.pageviews ?? 0) }}</td><td v-if="report.data.breakdown_metric === 'conversions'">{{ number(row.conversion_rate ?? 0) }}%</td></tr></tbody></table></template></template><template v-else><p>{{ t('manageScreen') }}</p><form><label>{{ t('name') }}<input /></label><button type="button">{{ t('save') }}</button></form></template></section></main>
</template>
