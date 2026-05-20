import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import ClientShell from '../ClientShell.vue'

let pageProps = { auth: { user: null }, notifications: null }
let currentUrl = '/'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href', 'method', 'as', 'aria-current'] },
  usePage: () => ({ props: pageProps, url: currentUrl }),
}))
vi.mock('@/Components/foundation', () => ({
  NotificationBell: { template: '<div data-testid="bell"></div>' },
}))

describe('ClientShell — adaptive', () => {
  it('guest renders login/register CTAs and 4-tab nav', () => {
    pageProps = { auth: { user: null } }
    currentUrl = '/'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    const html = w.html()
    expect(html).toContain('تسجيل الدخول')
    expect(html).toContain('إنشاء حساب')
    expect(w.findAll('nav a').length).toBe(4)
    expect(w.find('[data-testid="bell"]').exists()).toBe(false)
  })

  it('authed customer renders bell + 6-tab nav', () => {
    pageProps = { auth: { user: { id: 1, name: 'أحمد', role: 'customer' } }, notifications: { unread_count: 0 } }
    currentUrl = '/portal'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    expect(w.find('[data-testid="bell"]').exists()).toBe(true)
    expect(w.html()).toContain('خروج')
    expect(w.findAll('nav a').length).toBe(6)
  })

  it('authed customer header shows their name', () => {
    pageProps = { auth: { user: { id: 1, name: 'أحمد', role: 'customer' } }, notifications: null }
    currentUrl = '/portal'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    expect(w.html()).toContain('أحمد')
  })
})
