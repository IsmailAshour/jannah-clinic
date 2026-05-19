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

// jsdom has no matchMedia; shadcn-vue's useMediaQuery (in SidebarProvider)
// needs it. Defaults to "no mobile" (desktop ≥ md) so tests run against the
// desktop offcanvas layout; individual tests can override before mount.
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

describe('AdminShell (shadcn-vue Sidebar)', () => {
  beforeEach(() => {
    currentUrl = '/admin/doctors'
    document.cookie = 'sidebar_state=; path=/; max-age=0'
    installMatchMedia(false) // desktop by default
  })
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders nav leaves, groups, headings, brand, slot, and active state', () => {
    const w = mountShell()
    const text = w.text()
    // Brand + group headings + the user-authored label preserved verbatim
    // (including the deliberate double space in "حجز موعد  لعميل").
    expect(text).toContain('عيادة جنّة')
    expect(text).toContain('الخدمات')
    expect(text).toContain('العيادة')
    expect(text).toContain('حجز موعد  لعميل')
    expect(text).toContain('مناطق التغطية')
    expect(text).toContain('الإعدادات')
    expect(w.find('[data-testid="content"]').exists()).toBe(true)

    // Active state for current url (/admin/doctors): the sub-menu button
    // wrapping the link gets data-active="true" and the inner Inertia <Link>
    // gets aria-current="page".
    const active = w.find('a[href="/admin/doctors"]')
    expect(active.exists()).toBe(true)
    expect(active.attributes('aria-current')).toBe('page')
    // The shadcn-vue sub-button container exposes data-active for styling.
    const activeButton = w.find('[data-sidebar="menu-sub-button"][data-active="true"]')
    expect(activeButton.exists()).toBe(true)

    // Dashboard root not active when on /admin/doctors
    const dash = w.find('a[href="/admin"]')
    expect(dash.attributes('aria-current')).toBeUndefined()
    w.unmount()
  })

  it('renders the header logout link and a single sidebar trigger with Arabic aria-label', () => {
    const w = mountShell()
    const trigger = w.get('button[data-sidebar="trigger"]')
    expect(trigger.attributes('aria-label')).toBe('القائمة')
    // Logout is an Inertia Link (mocked → <a>) with method=post via attrs.
    const logout = w.find('a[href="/logout"]')
    expect(logout.exists()).toBe(true)
    expect(logout.text()).toBe('تسجيل الخروج')
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
