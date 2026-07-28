# 10 — Frontend Conventions

Established during the CRM build (Phase 1) and meant to apply to **every** future module (WhatsApp, Omnichannel, etc.), not just CRM. This is the reference for "how do I structure a new frontend feature in this app."

## Folder structure (Vuexy/Materio-style modular layout)

- `src/@layouts/` — `DefaultLayout.vue` (the authenticated app shell) + `@layouts/components/`: `NavDrawer.vue` (sidebar), `AppBarNav.vue` (top bar: service search, language switcher, theme-settings trigger, activity-log trigger, profile menu), `AppFooter.vue`, `ThemeSettingsDrawer.vue`, `ActivityLogDrawer.vue`.
- `src/@core/` — global utilities and config, not tied to any one feature: `@core/plugins/vuetify.js` (Vuetify instance + theme defaults), `@core/plugins/i18n.js` (vue-i18n instance), `@core/plugins/sweetalert.js` (themed SweetAlert2 wrapper), `@core/locales/{en,hi,es,fr}.js`, `@core/utils/api.js` (axios client), `@core/utils/time.js`, `@core/utils/modules.js` (the sidebar/search module list, shared by nav + search).
- `src/views/<module>/` — one folder per feature module (`auth`, `dashboard`, `crm`, and future `whatsapp` etc.), each with `<Module>View.vue` + a `components/` subfolder splitting the page into small pieces (stats row, filters, board, cards, dialogs) instead of one large file.
- `src/stores/<module>/` — one Pinia store file per module folder, plus cross-cutting stores that aren't tied to one feature: `stores/theme/`, `stores/locale/`, `stores/alert/`, `stores/activity/`.
- `src/router/routes/<module>.js` — each module exports its own route array; `router/index.js` only composes them + the auth guard.
- `@` and `@core`/`@layouts` are all Vite aliases (see `vite.config.js`) pointing at `src`, `src/@core`, `src/@layouts` respectively.

**When adding a new module**: create `views/<module>/`, `stores/<module>/`, `router/routes/<module>.js` — do not add flat files back to `views/`, `stores/`, or inline route arrays in `router/index.js`.

## Theming

`stores/theme/theme.js` persists (localStorage) and drives: light/dark/system mode (reactive to OS changes), "skin" (default vs. border — toggles whether `VCard` renders outlined instead of filled/tonal, applied via Vuetify's reactive `defaults` object in `App.vue`), a user-overridable primary color (falls back to the reseller's branding color until customized), and content width (fluid vs. boxed). All exposed via the right-side `ThemeSettingsDrawer.vue`, opened from the app bar's gear icon.

New components should stay theme-safe automatically by using Vuetify's own theme-aware props (`variant="tonal"`, `:color="..."`) rather than hardcoded hex colors. The few places with genuinely custom CSS colors (scrollbar thumb, button/card hover shadows, the login page's hero panel) have explicit `.v-theme--dark` overrides in their stylesheets (`:global(.v-theme--dark) .some-class { ... }` inside `<style scoped>`) — follow that pattern for any new hardcoded color.

## Localization

vue-i18n is set up with 4 languages (`en`/`hi`/`es`/`fr`), a persisted locale store (`stores/locale/locale.js`), and a language switcher in the app bar (full language name, not a flag emoji — flags render as literal country-code text like "GB" on Windows, which lacks flag glyph support). **Coverage is intentionally incomplete**: the initial pass covered nav/app-bar/theme/auth/dashboard/crm chrome, then the user explicitly said to stop expanding it. Use plain hardcoded English text for new UI copy going forward unless asked specifically to localize that new area — don't reflexively add keys to all four locale files for every new string.

## Reusable alerts (success/warning/error feedback)

Two channels, both established as the standard pattern for any form/action:
- **Snackbar** (`stores/alert/alert.js` + global `components/AppSnackbar.vue`, rendered once in `App.vue`) — lightweight, non-blocking. `useAlertStore().success('...')` / `.warning(...)` / `.error(...)` / `.info(...)` from anywhere.
- **SweetAlert2** (`@core/plugins/sweetalert.js` — `fireSuccess`, `fireError`, `fireWarning`, `fireConfirm`) — blocking modal, used for more prominent moments (e.g. right after a form submit succeeds) or destructive-action confirmations (e.g. delete).

## Form pattern (vee-validate + yup + server-error mapping)

See `views/crm/components/NewLeadDialog.vue` as the reference implementation: the dialog owns its own submission (calls the Pinia store action directly, not brokered through the parent view), a local `saving` ref drives the submit button's loading state, and on a Laravel 422 response the `errors: {field: [...]}` shape is mapped straight onto the matching vee-validate field via `setErrors(...)` — so a rejected field shows its actual server message inline, not just a generic toast. Non-validation failures (500s, network errors) go to `useAlertStore().error(...)`.

## Mobile responsiveness

`useDisplay().mobile` from Vuetify drives: the nav drawer (permanent+rail on desktop, Vuetify's own auto-switch to a temporary overlay with scrim below its breakpoint on mobile — don't fight this with a manual `temporary` prop, just let it happen and pass `v-model` + `rail`), and the app-bar search (inline on desktop, collapses to a magnify icon that expands into a full-width search on mobile). Kanban-style scrollable lists should have both a `min-height` (so content doesn't get squeezed to nothing — a real Vuetify `v-card` clips overflow by default, so an undersized container silently hides its children instead of showing a scrollbar) and a `max-height` (so they don't grow unbounded on tall viewports), with `overflow-y: auto` scrolling the range between them.

## Known gotchas hit during this build (don't re-debug these)

- **Horizontal drag gestures get hijacked by Chrome's swipe-to-navigate-back** unless `overscroll-behavior-x: none` (global) / `contain` (on the scroll container) is set — see `style.css`. Was mistaken for a Sortable.js/vuedraggable bug for a long time before finding this.
- **vue-i18n treats a bare `@` as the start of "linked message" syntax** (`@:key`) and throws a render-crashing compile error on strings like `"jane@example.com"` — escape literal `@` as `{'@'}` in locale files.
- **A controller missing `use App\Http\Controllers\Controller;`** (easy to forget when a new `Api\XxxController` is created in an editor that doesn't auto-import) resolves the bare `Controller` name to the wrong namespace and 500s at runtime, not at write-time — always double check this import on a new controller.
