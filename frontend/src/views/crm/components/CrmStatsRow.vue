<script setup>
import { useI18n } from 'vue-i18n'
import StatCard from '@/components/StatCard.vue'

defineProps({
  stats: { type: Object, required: true },
})

const { t } = useI18n()
</script>

<template>
  <div class="d-flex ga-4 mb-4 flex-wrap flex-shrink-0">
    <StatCard icon="mdi-account-group-outline" color="deep-purple" :value="stats.total" :label="t('crm.totalLeads')" />
    <StatCard icon="mdi-currency-usd" color="blue" :value="`$${stats.revenue.toFixed(2)}`" :label="t('crm.revenue')" />
    <StatCard icon="mdi-fire" color="orange" :value="stats.hot" :label="t('crm.hotLeads')" />
    <StatCard icon="mdi-email-outline" color="indigo" :value="stats.unread" :label="t('crm.unread')" />
    <StatCard icon="mdi-trophy-outline" color="green" :value="stats.won" :label="t('crm.wonLeads')" />
  </div>
</template>
