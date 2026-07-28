<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth/auth'
import { useLocaleStore } from '@/stores/locale/locale'
import { modules } from '@core/utils/modules'
import { availableLocales } from '@core/plugins/i18n'

defineEmits(['toggle-nav', 'open-settings'])

const auth = useAuthStore()
const router = useRouter()
const { mobile } = useDisplay()
const { t } = useI18n()
const localeStore = useLocaleStore()

const initials = computed(() => {
  const name = auth.user?.name ?? ''
  return name
    .split(' ')
    .map((part) => part[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
})

const currentLocale = computed(() => availableLocales.find((l) => l.value === localeStore.current) ?? availableLocales[0])

const search = ref(null)
const mobileSearchOpen = ref(false)

function onSearchSelect(routeName) {
  if (routeName) {
    router.push({ name: routeName })
  }
  search.value = null
  mobileSearchOpen.value = false
}

// No dedicated profile-settings page yet — placeholder until that module is built.
function openProfileSettings() {}

async function logout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <v-app-bar>
    <template v-if="mobile && mobileSearchOpen">
      <v-btn icon="mdi-arrow-left" variant="text" @click="mobileSearchOpen = false" />
      <v-autocomplete
        v-model="search"
        :items="modules"
        :item-title="(item) => t(item.titleKey)"
        item-value="route"
        :placeholder="t('appBar.searchPlaceholder')"
        variant="solo-filled"
        density="compact"
        flat
        hide-details
        autofocus
        class="mx-2 flex-grow-1"
        @update:model-value="onSearchSelect"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item
            v-bind="itemProps"
            :disabled="!item.raw.enabled"
            :subtitle="item.raw.enabled ? undefined : t('nav.comingSoon')"
            title=""
          >
            <template #prepend>
              <v-icon :icon="item.raw.icon" :color="item.raw.enabled ? item.raw.color : 'grey'" />
            </template>
            {{ t(item.raw.titleKey) }}
          </v-list-item>
        </template>
      </v-autocomplete>
    </template>

    <template v-else>
      <v-app-bar-nav-icon @click="$emit('toggle-nav')" />

      <v-autocomplete
        v-if="!mobile"
        v-model="search"
        :items="modules"
        :item-title="(item) => t(item.titleKey)"
        item-value="route"
        :placeholder="t('appBar.searchPlaceholder')"
        prepend-inner-icon="mdi-magnify"
        variant="solo-filled"
        density="compact"
        flat
        hide-details
        clearable
        class="mx-4"
        style="max-width: 360px"
        @update:model-value="onSearchSelect"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item
            v-bind="itemProps"
            :disabled="!item.raw.enabled"
            :subtitle="item.raw.enabled ? undefined : t('nav.comingSoon')"
            title=""
          >
            <template #prepend>
              <v-icon :icon="item.raw.icon" :color="item.raw.enabled ? item.raw.color : 'grey'" />
            </template>
            {{ t(item.raw.titleKey) }}
          </v-list-item>
        </template>
      </v-autocomplete>

      <v-spacer />

      <v-btn v-if="mobile" icon="mdi-magnify" variant="text" @click="mobileSearchOpen = true" />

      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" variant="text" class="mr-1" prepend-icon="mdi-web">
            <span v-if="!mobile" class="text-body-2">{{ currentLocale.label }}</span>
          </v-btn>
        </template>
        <v-list density="compact">
          <v-list-item
            v-for="loc in availableLocales"
            :key="loc.value"
            :active="loc.value === localeStore.current"
            @click="localeStore.setLocale(loc.value)"
          >
            <v-list-item-title>{{ loc.label }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-menu>

      <v-btn icon="mdi-cog-outline" variant="text" class="mr-2" @click="$emit('open-settings')" />

      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn v-bind="menuProps" icon variant="text">
            <v-avatar color="primary" size="36">
              <span class="text-body-2 font-weight-bold">{{ initials }}</span>
            </v-avatar>
          </v-btn>
        </template>

        <v-card min-width="240">
          <v-list-item>
            <template #prepend>
              <v-avatar color="primary" size="40">
                <span class="font-weight-bold">{{ initials }}</span>
              </v-avatar>
            </template>
            <v-list-item-title class="font-weight-bold">{{ auth.user?.name }}</v-list-item-title>
            <v-list-item-subtitle class="text-capitalize">{{ auth.user?.role }}</v-list-item-subtitle>
          </v-list-item>
          <v-divider />
          <v-list density="compact">
            <v-list-item prepend-icon="mdi-account-cog-outline" :title="t('appBar.profileSettings')" @click="openProfileSettings" />
            <v-list-item prepend-icon="mdi-logout" :title="t('appBar.logout')" @click="logout" />
          </v-list>
        </v-card>
      </v-menu>
    </template>
  </v-app-bar>
</template>
