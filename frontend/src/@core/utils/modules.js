// Module list per .claude/build-plan/01-feature-inventory.md — most are
// placeholders until their phase in 06-roadmap.md is built. Only Dashboard
// and CRM are real right now. Icon colors loosely follow the reference app's
// multicolor sidebar style (see ai_sass_screenshot/dashboar navigation.png).
// Shared between the sidebar nav and the app-bar service search. `titleKey`
// is an i18n key (see @core/locales/*.js `nav.*`) — consumers should
// translate it via `t(item.titleKey)` rather than showing a raw title.
export const modules = [
  { titleKey: 'nav.dashboard', icon: 'mdi-view-dashboard', color: '#1976D2', route: 'dashboard', enabled: true },
  { titleKey: 'nav.whatsapp', icon: 'mdi-whatsapp', color: '#25D366', enabled: false },
  { titleKey: 'nav.callerDesk', icon: 'mdi-phone', color: '#5C6BC0', enabled: false },
  { titleKey: 'nav.emailMarketing', icon: 'mdi-email-outline', color: '#EF5350', enabled: false },
  { titleKey: 'nav.liveChat', icon: 'mdi-chat-outline', color: '#42A5F5', enabled: false },
  { titleKey: 'nav.supportSystem', icon: 'mdi-headset', color: '#8E24AA', enabled: false },
  { titleKey: 'nav.commerce', icon: 'mdi-storefront-outline', color: '#43A047', enabled: false },
  { titleKey: 'nav.socialMedia', icon: 'mdi-share-variant-outline', color: '#8E24AA', enabled: false },
  { titleKey: 'nav.metaCloudApi', icon: 'mdi-whatsapp', color: '#26A69A', enabled: false },
  { titleKey: 'nav.crm', icon: 'mdi-view-column-outline', color: '#1E88E5', route: 'crm', enabled: true },
  { titleKey: 'nav.omnichannel', icon: 'mdi-forum-outline', color: '#7E57C2', enabled: false },
  { titleKey: 'nav.numberWarmer', icon: 'mdi-fire', color: '#FB8C00', enabled: false },
  { titleKey: 'nav.crmPipelines', icon: 'mdi-view-column', color: '#1E88E5', enabled: false },
  { titleKey: 'nav.aiAppointments', icon: 'mdi-calendar-clock', color: '#00897B', enabled: false },
  { titleKey: 'nav.chatAgents', icon: 'mdi-account-voice', color: '#5E35B1', enabled: false },
  { titleKey: 'nav.socialLeadMaster', icon: 'mdi-magnet', color: '#D81B60', enabled: false },
  { titleKey: 'nav.accountManager', icon: 'mdi-account-multiple-outline', color: '#3949AB', enabled: false },
  { titleKey: 'nav.fileManager', icon: 'mdi-folder-outline', color: '#6D4C41', enabled: false },
  { titleKey: 'nav.tools', icon: 'mdi-tools', color: '#546E7A', enabled: false },
  { titleKey: 'nav.invoices', icon: 'mdi-receipt-text-outline', color: '#E53935', enabled: false },
  { titleKey: 'nav.googleSheets', icon: 'mdi-google-spreadsheet', color: '#2E7D32', enabled: false },
  { titleKey: 'nav.wooCommerce', icon: 'mdi-wordpress', color: '#7B1FA2', enabled: false },
  { titleKey: 'nav.aiChatbot', icon: 'mdi-robot-outline', color: '#D81B60', enabled: false },
  { titleKey: 'nav.apiAutomation', icon: 'mdi-lightning-bolt-outline', color: '#FB8C00', enabled: false },
]
