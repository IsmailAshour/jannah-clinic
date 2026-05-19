import { mount } from '@vue/test-utils'
import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock @inertiajs/vue3 the way a shell needs it: a passthrough <Link>
// rendering an <a> (so click/keyboard work) and a usePage() with a url.
let currentUrl = '/admin/doctors'
vi.mock('@inertiajs/vue3', () => ({
  Link: {
    name: 'Link',
    props: ['href', 'method', 'as'],
    template: '<a :href="href"><slot /></a>',
  },
  usePage: () => ({ get url() { return currentUrl } }),
}))

import AdminShell from '../AdminShell.vue'

function mountShell() {
  return mount(AdminShell, {
    slots: { default: '<p data-testid="content">صفحة</p>' },
    attachTo: document.body,
  })
}

describe('AdminShell', () => {
  beforeEach(() => {
    currentUrl = '/admin/doctors'
    window.localStorage.clear()
  })

  it('renders nav leaves, groups, headings, brand, slot, and active state', () => {
    const w = mountShell()
    const text = w.text()
    // Brand + group headings + the user-authored label preserved verbatim
    expect(text).toContain('عيادة جنّة')
    expect(text).toContain('الخدمات')
    expect(text).toContain('العيادة')
    expect(text).toContain('حجز موعد  لعميل')
    expect(text).toContain('مناطق التغطية')
    expect(w.find('[data-testid="content"]').exists()).toBe(true)
    // Active state for current url (/admin/doctors)
    const active = w.find('a[href="/admin/doctors"]')
    expect(active.attributes('aria-current')).toBe('page')
    expect(active.classes()).toContain('bg-white/15')
    expect(active.classes()).toContain('font-semibold')
    // Dashboard root not active when on /admin/doctors
    const dash = w.find('a[href="/admin"]')
    expect(dash.attributes('aria-current')).toBeUndefined()
    w.unmount()
  })

  it('hamburger toggles the mobile drawer open/closed (aria-expanded flips)', async () => {
    const w = mountShell()
    const hamburger = w.get('button[aria-label="القائمة"]')
    expect(hamburger.attributes('aria-expanded')).toBe('false')
    expect(w.vm.open).toBe(false)

    await hamburger.trigger('click')
    expect(hamburger.attributes('aria-expanded')).toBe('true')
    expect(w.vm.open).toBe(true)
    w.unmount()
  })

  it('Esc closes the open mobile drawer', async () => {
    const w = mountShell()
    await w.get('button[aria-label="القائمة"]').trigger('click')
    expect(w.vm.open).toBe(true)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    await w.vm.$nextTick()
    expect(w.vm.open).toBe(false)
    w.unmount()
  })

  it('backdrop click closes the open mobile drawer', async () => {
    const w = mountShell()
    await w.get('button[aria-label="القائمة"]').trigger('click')
    expect(w.vm.open).toBe(true)

    const backdrop = w.get('.z-overlay.fixed.inset-0')
    await backdrop.trigger('click')
    expect(w.vm.open).toBe(false)
    w.unmount()
  })

  it('selecting a nav link closes the mobile drawer', async () => {
    const w = mountShell()
    await w.get('button[aria-label="القائمة"]').trigger('click')
    expect(w.vm.open).toBe(true)

    await w.get('a[href="/admin/doctors"]').trigger('click')
    expect(w.vm.open).toBe(false)
    w.unmount()
  })

  it('desktop collapse toggle persists collapsed state to localStorage', async () => {
    const w = mountShell()
    const toggle = w.get('button[aria-label="طيّ القائمة"]')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(w.vm.collapsed).toBe(false)

    await toggle.trigger('click')
    expect(w.vm.collapsed).toBe(true)
    expect(window.localStorage.getItem('jannah.adminSidebarCollapsed')).toBe('1')
    // aria-label/aria-expanded reflect collapsed state
    const collapsedToggle = w.get('button[aria-label="إظهار القائمة"]')
    expect(collapsedToggle.attributes('aria-expanded')).toBe('false')
    w.unmount()
  })

  it('restores collapsed state from localStorage on (re)mount', async () => {
    window.localStorage.setItem('jannah.adminSidebarCollapsed', '1')
    const w = mountShell()
    await w.vm.$nextTick()
    expect(w.vm.collapsed).toBe(true)
    expect(w.get('button[aria-label="إظهار القائمة"]').exists()).toBe(true)
    w.unmount()
  })
})
