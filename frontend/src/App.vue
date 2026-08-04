<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const { t, locale } = useI18n()
const screen = ref('dashboard')
const screens = ['login', 'sites', 'dashboard', 'pages', 'referrers', 'countries', 'devices', 'campaigns', 'goals', 'realtime', 'settings', 'sharedDashboard', 'members', 'apiKeys', 'auditLog']
const title = computed(() => t(screen.value))
</script>

<template>
  <main>
    <header><h1>TallyMark</h1><select v-model="locale" aria-label="Language"><option value="en">English</option><option value="pt-BR">Português (Brasil)</option></select></header>
    <nav aria-label="Analytics"><button v-for="item in screens" :key="item" :aria-current="screen === item ? 'page' : undefined" @click="screen = item">{{ t(item) }}</button></nav>
    <section aria-live="polite"><h2>{{ title }}</h2><p>{{ t('approximate') }}</p><p><strong>{{ t('timezone') }}:</strong> UTC</p><table><caption>{{ title }}</caption><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody><tr><td>Pageviews</td><td>—</td></tr></tbody></table></section>
  </main>
</template>
