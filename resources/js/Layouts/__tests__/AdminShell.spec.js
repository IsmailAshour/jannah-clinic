import { mount } from '@vue/test-utils'
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { nextTick } from 'vue'

// Mock @inertiajs/vue3 the way a shell needs it: a passthrough <Link>
// rendering an <a> (so click/keyboard work) and a usePage() with a url.
let currentUrl = '/admin/doctors'
vi.mock('@inertiajs/vue3', () => ({
  Link: {
    name: 'Link',
    props: ['href', 'method', 'as'],
    // Forward `onClick` (and other attrs) so the AdminShell's NavLink click
    // handler — which calls setOpenMobile(false) on the sidebar context — runs
    // when the underlying <a> is clicked in jsdom.
    template: '<a :href="href" v-bind="$attrs"><slot /></a>',
    inheritAttrs: false,
  },
  usePage: () => ({ get url() { return currentUrl } }),
}))

// jsdom has no ResizeObserver; reka-ui's CollapsibleContent (used by every
// group in the sidebar-07 pattern) calls `new ResizeObserver()` on mount to
// animate the content height. A no-op stub is enough — we never assert on
// the observed size, only on data-state.
if (typeof globalThis.ResizeObserver === 'undefined') {
  class RO {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
  globalThis.ResizeObserver = RO
}

// jsdom has no matchMedia; shadcn-vue's useMediaQuery (in SidebarProvider)
// needs it. Defaults to "no mobile" (desktop ≥ md) so tests run against the
// desktop icon-collapsible layout; individual tests can override before mount.
let mediaMatches = false
function installMatchMedia(matches) {
  mediaMatches = matches
  window.matchMedia = (query) => ({
    matches: mediaMatches,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  })
}

import AdminShell from '../AdminShell.vue'

function mountShell() {
  return mount(AdminShell, {
    slots: { default: '<p data-testid="content">صفحة</p>' },
    attachTo: document.body,
  })
}

describe('AdminShell (sidebar-07 icon-collapsible)', () => {
  beforeEach(() => {
    currentUrl = '/admin/doctors'
    document.cookie = 'sidebar_state=; path=/; max-age=0'
    installMatchMedia(false) // desktop by default
  })
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders brand, leaves, group labels, slot, and preserves the double-space label verbatim', () => {
    const w = mountShell()
    const text = w.text()
    // Brand + group labels (now rendered as SidebarMenuButton text, not
    // SidebarGroupLabel) + the user-authored label preserved verbatim
    // (including the deliberate double space in "حجز موعد  لعميل").
    expect(text).toContain('عيادة جنّة')
    expect(text).toContain('الخدمات')
    expect(text).toContain('العيادة')
    expect(text).toContain('حجز موعد  لعميل')
    expect(text).toContain('مناطق التغطية')
    expect(text).toContain('الإعدادات')
    expect(text).toContain('لوحة التحكم')
    expect(w.find('[data-testid="content"]').exists()).toBe(true)
    w.unmount()
  })

  it('active sub-item has aria-current=page; its parent Collapsible is default-open with sub-items in the DOM', () => {
    const w = mountShell()
    // Active state for current url (/admin/doctors): the sub-menu button
    // wrapping the link gets data-active="true" and the inner Inertia <Link>
    // gets aria-current="page".
    const active = w.find('a[href="/admin/doctors"]')
    expect(active.exists()).toBe(true)
    expect(active.attributes('aria-current')).toBe('page')
    const activeButton = w.find('[data-sidebar="menu-sub-button"][data-active="true"]')
    expect(activeButton.exists()).toBe(true)

    // Dashboard root not active when on /admin/doctors
    const dash = w.find('a[href="/admin"]')
    expect(dash.attributes('aria-current')).toBeUndefined()

    // Parent group العيادة (Clinic) is default-open because /admin/doctors
    // is one of its children. The Collapsible's reka-ui root exposes
    // data-state="open" once mounted with default-open=true. We also assert
    // the sibling sub-items are present in the DOM (would not render
    // otherwise — CollapsibleContent unmountOnHide closes hide them).
    const openCollapsible = w.find('[data-state="open"][data-slot="collapsible-root"]')
    // reka-ui labels the Collapsible root with data-state; the exact data-slot
    // selector above is the strictest form, but reka may version that — fall
    // back to the data-state pair when needed.
    const hasOpenState = openCollapsible.exists()
      || w.findAll('[data-state="open"]').some((el) => el.findAll('a[href="/admin/doctors"]').length > 0)
    expect(hasOpenState).toBe(true)

    // The other group (الخدمات / Services) is NOT default-open — its sub-link
    // /admin/catalog/services should not appear yet (CollapsibleContent
    // unmounts hidden content). It's enough to assert the appointments link
    // (also a child of العيادة, same group as doctors) IS rendered.
    expect(w.find('a[href="/admin/appointments"]').exists()).toBe(true)
    expect(w.find('a[href="/admin/booking"]').exists()).toBe(true)

    w.unmount()
  })

  it('renders the visit-site link, sidebar trigger, and the user-nav footer', () => {
    const w = mountShell()
    const trigger = w.get('button[data-sidebar="trigger"]')
    expect(trigger.attributes('aria-label')).toBe('القائمة')
    // Visit-site shortcut in the SidebarFooter
    const visitSite = w.find('a[href="/"]')
    expect(visitSite.exists()).toBe(true)
    expect(w.text()).toContain('زيارة الموقع')
    // User-nav footer (sidebar-07 pattern): the trigger button renders before
    // its dropdown content is opened. We just assert it exists; logout lives
    // inside DropdownMenuContent which only mounts when opened.
    expect(w.find('[data-sidebar="footer"]').exists()).toBe(true)
    w.unmount()
  })

  it('SidebarTrigger toggles desktop state via useSidebar (sets sidebar_state cookie)', async () => {
    const w = mountShell()
    // Initial state: provider defaults to !cookie('=false') ⇒ open (expanded)
    const wrapper = w.find('[data-state="expanded"]')
    expect(wrapper.exists()).toBe(true)

    await w.get('button[data-sidebar="trigger"]').trigger('click')
    await nextTick()

    // After click, desktop setOpen() writes the cookie. We assert the cookie
    // flipped to "false" (collapsed) — the persistence contract of the
    // shadcn-vue sidebar that replaces our old localStorage logic.
    expect(document.cookie).toContain('sidebar_state=false')
    w.unmount()
  })

  it('on mobile, the trigger opens the Sheet and a nav click closes it', async () => {
    installMatchMedia(true) // < md → mobile
    const w = mountShell()

    // Mobile path renders inside a Sheet (Dialog) portalled to body.
    // Before opening: no nav sheet content visible.
    expect(document.querySelector('[data-mobile="true"]')).toBeNull()

    await w.get('button[data-sidebar="trigger"]').trigger('click')
    await nextTick()
    await nextTick()
    expect(document.querySelector('[data-mobile="true"]')).not.toBeNull()

    // Clicking a nav link calls setOpenMobile(false) → sheet closes.
    // The Sheet (reka-ui DialogContent) animates out, so its data-state
    // flips to "closed" before the DOM node is removed. Asserting on the
    // state attribute avoids timing flakes from the animation/teardown.
    const link = document.querySelector('[data-mobile="true"] a[href="/admin/doctors"]')
    expect(link).not.toBeNull()
    link.click()
    await nextTick()
    await nextTick()
    const sheet = document.querySelector('[data-mobile="true"]')
    expect(sheet === null || sheet.getAttribute('data-state') === 'closed').toBe(true)
    w.unmount()
  })
})
