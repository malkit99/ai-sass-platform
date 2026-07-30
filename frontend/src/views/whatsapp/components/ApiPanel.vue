<script setup>
import { computed, onMounted, ref } from 'vue'
import { useWhatsappStore } from '@/stores/whatsapp/whatsapp'
import { useAlertStore } from '@/stores/alert/alert'
import api from '@core/utils/api'
import AppButton from '@/components/AppButton.vue'

const whatsapp = useWhatsappStore()
const alertStore = useAlertStore()

const statusColor = { connected: 'success', connecting: 'warning', disconnected: 'default' }
const statusLabel = { connected: 'Connected', connecting: 'Connecting', disconnected: 'Not connected' }

const channelId = ref(whatsapp.channels[0]?.id ?? null)
const channel = computed(() => whatsapp.channels.find((c) => c.id === channelId.value) ?? null)

// All groups enabled until the real settings load, so nothing flashes
// hidden for accounts that aren't reseller-gated at all.
const settings = ref({ enabled_groups: ['instance', 'messages', 'groups'], editable: false })
const settingsLoading = ref(true)
const savingSettings = ref(false)

onMounted(async () => {
  try {
    settings.value = await whatsapp.fetchApiSettings()
  } finally {
    settingsLoading.value = false
  }
})

function toggleGroup(group, value) {
  const set = new Set(settings.value.enabled_groups)
  if (value) set.add(group)
  else set.delete(group)
  settings.value.enabled_groups = [...set]
}

async function saveSettings() {
  savingSettings.value = true
  try {
    settings.value = await whatsapp.updateApiSettings(settings.value.enabled_groups)
    alertStore.success('API settings saved — your client accounts will see this immediately.')
  } catch {
    alertStore.error('Failed to save API settings.')
  } finally {
    savingSettings.value = false
  }
}

function copy(value, label = 'Value') {
  navigator.clipboard.writeText(String(value))
  alertStore.info(`${label} copied.`)
}

const base = computed(() => `${api.defaults.baseURL}/api/whatsapp`)
const instanceId = computed(() => channel.value?.id ?? '{instance_id}')
const accessToken = computed(() => channel.value?.access_token ?? '{access_token}')

function queryUrl(path, params) {
  const qs = params.map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&')
  return `${base.value}${path}?${qs}`
}

const endpointGroups = computed(() => [
  {
    key: 'instance',
    label: 'Instance API',
    icon: 'mdi-cellphone-cog',
    color: '#1976D2',
    endpoints: [
      {
        method: 'POST',
        title: 'Create Instance',
        url: queryUrl('/create_instance', [['access_token', accessToken.value]]),
        description: 'Starts the connection for an account you already created in Accounts.',
        params: [['access_token', accessToken.value]],
      },
      {
        method: 'GET',
        title: 'Get QR Code',
        url: queryUrl('/get_qrcode', [['instance_id', instanceId.value], ['access_token', accessToken.value]]),
        description: 'Returns the current QR code (base64 PNG) to scan and log in to WhatsApp Web. Poll while connecting, or use Set Webhook to be notified instead.',
        params: [['instance_id', instanceId.value], ['access_token', accessToken.value]],
      },
      {
        method: 'POST',
        title: 'Set Webhook',
        url: queryUrl('/set_webhook', [
          ['webhook_url', 'https://webhook.site/your-id'],
          ['enable', 'true'],
          ['instance_id', instanceId.value],
          ['access_token', accessToken.value],
        ]),
        description: 'Forwards connection status changes and inbound messages to your own URL. Each delivery is HMAC-SHA256 signed (X-Webhook-Signature header) using your access_token as the secret.',
        params: [
          ['webhook_url', 'https://webhook.site/your-id'],
          ['enable', 'true'],
          ['instance_id', instanceId.value],
          ['access_token', accessToken.value],
        ],
      },
      {
        method: 'POST',
        title: 'Reboot Instance',
        url: queryUrl('/reboot', [['instance_id', instanceId.value], ['access_token', accessToken.value]]),
        description: 'Logs out and reconnects — use this to force a fresh QR scan.',
        params: [['instance_id', instanceId.value], ['access_token', accessToken.value]],
      },
      {
        method: 'POST',
        title: 'Reset Instance',
        url: queryUrl('/reset_instance', [['instance_id', instanceId.value], ['access_token', accessToken.value]]),
        description: 'Logs out and clears the saved session. instance_id itself does not change.',
        params: [['instance_id', instanceId.value], ['access_token', accessToken.value]],
      },
      {
        method: 'POST',
        title: 'Reconnect',
        url: queryUrl('/reconnect', [['instance_id', instanceId.value], ['access_token', accessToken.value]]),
        description: 'Re-initiates the connection for an instance that has gone offline.',
        params: [['instance_id', instanceId.value], ['access_token', accessToken.value]],
      },
    ],
  },
  {
    key: 'messages',
    label: 'Send Direct Message API',
    icon: 'mdi-send-outline',
    color: '#25D366',
    endpoints: [
      {
        method: 'POST',
        title: 'Send Text / Media',
        url: queryUrl('/send', [
          ['number', '14155552671'], ['type', 'text'], ['message', 'test message'],
          ['instance_id', instanceId.value], ['access_token', accessToken.value],
        ]),
        plainUrl: `${base.value}/send`,
        body: {
          number: '{int}', type: 'text', message: '{string}',
          media_url: '{string, required if type=media}',
          instance_id: instanceId.value, access_token: accessToken.value,
        },
        description: 'Sends a text or media message to a phone number through this account. Costs 1 WhatsApp credit, same as sending from the app.',
        params: [
          ['number', '14155552671'], ['type', 'text | media'], ['message', 'test message'],
          ['media_url', '(required if type=media)'],
          ['instance_id', instanceId.value], ['access_token', accessToken.value],
        ],
      },
      {
        method: 'POST',
        title: 'Send Buttons / List',
        plainUrl: `${base.value}/send_template`,
        body: {
          number: '{int}', type: 'buttons', message: '{string}',
          config: { buttons: ['Yes', 'No'] },
          instance_id: instanceId.value, access_token: accessToken.value,
        },
        description: 'Sends an inline interactive message — quick-reply buttons (type=buttons, config: {buttons:[...]}) or a list (type=list, config: {button_text, sections}). Costs 1 credit.',
        params: [
          ['number', '14155552671'], ['type', 'buttons | list'], ['message', 'test message'],
          ['config', '{ buttons: [...] } or { button_text, sections: [...] }'],
          ['instance_id', instanceId.value], ['access_token', accessToken.value],
        ],
      },
      {
        method: 'POST',
        title: 'Send by Saved Template',
        plainUrl: `${base.value}/send_by_template`,
        body: {
          number: '{int}', template_type: 'button', template_id: '{int}',
          instance_id: instanceId.value, access_token: accessToken.value,
        },
        description: 'Sends one of your saved Templates by ID. Open Templates, click a template’s ID to copy it, and use it as template_id here.',
        params: [
          ['number', '14155552671'], ['template_type', 'button | list | poll'], ['template_id', 'e.g. 12'],
          ['instance_id', instanceId.value], ['access_token', accessToken.value],
        ],
      },
    ],
  },
  {
    key: 'groups',
    label: 'Group API',
    icon: 'mdi-account-group-outline',
    color: '#8E24AA',
    endpoints: [
      {
        method: 'GET',
        title: 'Get Groups from Instance',
        url: queryUrl('/get_groups', [['instance_id', instanceId.value], ['access_token', accessToken.value]]),
        description: 'Lists every group this account currently participates in (group_id, name, participant_count).',
        params: [['instance_id', instanceId.value], ['access_token', accessToken.value]],
      },
      {
        method: 'POST',
        title: 'Send Message to Group',
        url: queryUrl('/send_group', [
          ['group_id', '1234567890-1234567890@g.us'], ['type', 'text'], ['message', 'test message'],
          ['instance_id', instanceId.value], ['access_token', accessToken.value],
        ]),
        plainUrl: `${base.value}/send_group`,
        body: {
          group_id: '{string, ends with @g.us}', type: 'text', message: '{string}',
          media_url: '{string, required if type=media}',
          instance_id: instanceId.value, access_token: accessToken.value,
        },
        description: 'Sends a text or media message to a group through this account — get group_id from Get Groups. Costs 1 credit.',
        params: [
          ['group_id', '1234567890-1234567890@g.us'], ['type', 'text | media'], ['message', 'test message'],
          ['media_url', '(required if type=media)'],
          ['instance_id', instanceId.value], ['access_token', accessToken.value],
        ],
      },
    ],
  },
])

const visibleGroups = computed(() => endpointGroups.value.filter((g) => settings.value.enabled_groups.includes(g.key)))
</script>

<template>
  <div>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <div class="flex-grow-1">
        <h2 class="text-h5">API</h2>
        <div class="text-caption text-medium-emphasis">API WhatApp REST — for external apps and automation tools (Zapier, n8n, etc.)</div>
      </div>
    </div>

    <v-select
      v-model="channelId" :items="whatsapp.channels" item-title="display_name" item-value="id"
      placeholder="Choose account…" variant="outlined" density="comfortable" style="max-width: 420px" class="mb-4"
    >
      <template #item="{ props: itemProps, item }">
        <v-list-item v-bind="itemProps">
          <template #title>{{ item.raw.display_name }}</template>
          <template #append>
            <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
          </template>
        </v-list-item>
      </template>
      <template #selection="{ item }">
        {{ item.raw.display_name }}
        <v-chip :color="statusColor[item.raw.status] ?? 'default'" size="x-small" variant="flat" class="ml-2">{{ statusLabel[item.raw.status] ?? item.raw.status }}</v-chip>
      </template>
    </v-select>

    <template v-if="channel">
      <v-card variant="tonal" color="success" class="pa-4 mb-4 d-flex align-center justify-space-between flex-wrap ga-2">
        <div>
          <div class="text-caption text-medium-emphasis">Instance ID</div>
          <div class="font-weight-medium" style="font-family: monospace">{{ instanceId }}</div>
        </div>
        <div>
          <div class="text-caption text-medium-emphasis">Your Access Token</div>
          <div class="font-weight-medium" style="font-family: monospace">{{ accessToken }}</div>
        </div>
        <div class="d-flex ga-2">
          <v-btn icon="mdi-content-copy" variant="text" size="small" @click="copy(instanceId, 'Instance ID')" />
          <v-btn icon="mdi-content-copy" variant="text" size="small" @click="copy(accessToken, 'Access token')" />
        </div>
      </v-card>

      <v-card v-if="!settingsLoading && settings.editable" variant="outlined" class="pa-4 mb-5">
        <div class="d-flex align-center ga-2 mb-3">
          <v-icon icon="mdi-shield-key-outline" color="primary" />
          <span class="text-subtitle-1 font-weight-medium">API Access — Your Client Accounts</span>
        </div>
        <div class="text-caption text-medium-emphasis mb-3">
          Control which endpoint groups your client accounts can see and use through this API. This only applies to accounts onboarded under you as a reseller.
        </div>
        <div class="d-flex flex-wrap ga-4 mb-3">
          <v-switch
            :model-value="settings.enabled_groups.includes('instance')" label="Instance API"
            color="primary" density="comfortable" hide-details
            @update:model-value="(v) => toggleGroup('instance', v)"
          />
          <v-switch
            :model-value="settings.enabled_groups.includes('messages')" label="Send Direct Message API"
            color="primary" density="comfortable" hide-details
            @update:model-value="(v) => toggleGroup('messages', v)"
          />
          <v-switch
            :model-value="settings.enabled_groups.includes('groups')" label="Group API"
            color="primary" density="comfortable" hide-details
            @update:model-value="(v) => toggleGroup('groups', v)"
          />
        </div>
        <AppButton :loading="savingSettings" prepend-icon="mdi-content-save" @click="saveSettings">Save</AppButton>
      </v-card>

      <v-alert v-else-if="!settingsLoading && visibleGroups.length < endpointGroups.length" variant="tonal" color="info" class="mb-5" icon="mdi-information-outline">
        Your reseller has disabled some API sections for your account.
      </v-alert>

      <template v-for="group in visibleGroups" :key="group.key">
        <div class="d-flex align-center ga-2 mt-2 mb-3">
          <v-icon :icon="group.icon" :color="group.color" />
          <span class="text-h6">{{ group.label }}</span>
        </div>

        <v-card v-for="endpoint in group.endpoints" :key="endpoint.title" variant="outlined" class="pa-4 mb-4">
          <div class="d-flex align-center ga-2 mb-3">
            <v-chip :color="endpoint.method === 'GET' ? 'info' : 'success'" size="small" variant="flat" class="font-weight-bold">{{ endpoint.method }}</v-chip>
            <span class="text-subtitle-1 font-weight-medium">{{ endpoint.title }}</span>
          </div>

          <div v-if="endpoint.url">
            <div class="text-caption text-medium-emphasis mb-1">Resource URL:</div>
            <div class="url-box mb-3">
              <span class="url-text">{{ endpoint.url }}</span>
              <v-btn icon="mdi-content-copy" variant="text" size="small" @click="copy(endpoint.url, 'URL')" />
            </div>
          </div>

          <div v-if="endpoint.plainUrl">
            <div class="text-caption text-medium-emphasis mb-1">Resource URL:</div>
            <div class="url-box mb-3">
              <span class="url-text">{{ endpoint.plainUrl }}</span>
              <v-btn icon="mdi-content-copy" variant="text" size="small" @click="copy(endpoint.plainUrl, 'URL')" />
            </div>
          </div>

          <div v-if="endpoint.body">
            <div class="text-caption text-medium-emphasis mb-1">Structure of the POST request body:</div>
            <div class="text-caption text-success mb-1">Content-Type: application/json</div>
            <pre class="body-box mb-3">{{ JSON.stringify(endpoint.body, null, 2) }}</pre>
          </div>

          <p class="text-body-2 mb-3">{{ endpoint.description }}</p>

          <div class="text-caption text-medium-emphasis font-weight-bold mb-1">PARAMS</div>
          <v-table density="compact" class="params-table">
            <tbody>
              <tr v-for="p in endpoint.params" :key="p[0]">
                <td class="font-weight-medium" style="width: 220px">{{ p[0] }}</td>
                <td class="text-medium-emphasis" style="font-family: monospace">{{ p[1] }}</td>
              </tr>
            </tbody>
          </v-table>
        </v-card>
      </template>
    </template>

    <v-card v-else variant="tonal" class="pa-8 text-center">
      <v-icon icon="mdi-connection" size="48" class="mb-2" />
      <div class="text-h6">Select an account</div>
      <div class="text-body-2 text-medium-emphasis">Choose a WhatsApp account to see its live API credentials and endpoints.</div>
    </v-card>
  </div>
</template>

<style scoped>
.url-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  background: rgba(var(--v-theme-on-surface), 0.05);
  border-radius: 8px;
  padding: 10px 12px;
}

.url-text {
  font-family: monospace;
  font-size: 0.8125rem;
  word-break: break-all;
}

.body-box {
  background: rgba(var(--v-theme-on-surface), 0.05);
  border-radius: 8px;
  padding: 12px;
  font-family: monospace;
  font-size: 0.8125rem;
  white-space: pre-wrap;
  word-break: break-all;
}

.params-table :deep(td) {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
