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
  AuthGuardLink: { template: '<a data-testid="fab"><slot /></a>' },
  FlashToast: { template: '<div data-testid="flash-toast"></div>' },
}))
// shadcn-vue's DropdownMenu teleports content into body only when open. Stub it
// so the menu items render inline and assertions can see them without simulating clicks.
vi.mock('@/Components/ui/dropdown-menu', () => ({
  DropdownMenu: { template: '<div><slot /></div>' },
  DropdownMenuTrigger: { template: '<div><slot /></div>' },
  DropdownMenuContent: { template: '<div data-testid="profile-menu"><slot /></div>' },
  DropdownMenuItem: { template: '<div><slot /></div>' },
  DropdownMenuLabel: { template: '<div><slot /></div>' },
  DropdownMenuSeparator: { template: '<hr />' },
}))

describe('ClientShell — adaptive', () => {
  it('guest renders login/register CTAs and 4 side tabs + center FAB', () => {
    pageProps = { auth: { user: null } }
    currentUrl = '/'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    const html = w.html()
    expect(html).toContain('تسجيل الدخول')
    expect(html).toContain('إنشاء حساب')
    expect(w.findAll('nav a').filter((el) => el.attributes('data-testid') !== 'fab').length).toBe(4)
    expect(w.find('[data-testid="fab"]').exists()).toBe(true)
    expect(w.find('[aria-label="الإشعارات"]').exists()).toBe(false)
  })

  it('authed customer renders bell + profile menu (with logout) + 4 side tabs + center FAB', () => {
    pageProps = { auth: { user: { id: 1, name: 'أحمد', role: 'customer' } }, notifications: { unread_count: 0 } }
    currentUrl = '/portal'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    // Bell is now a Link with aria-label='الإشعارات' (no longer the NotificationBell component)
    expect(w.find('[aria-label="الإشعارات"]').exists()).toBe(true)
    expect(w.find('[data-testid="profile-menu"]').exists()).toBe(true)
    // Logout link is inside the profile dropdown (stubbed inline).
    expect(w.html()).toContain('تسجيل الخروج')
    expect(w.html()).toContain('البروفايل')
    expect(w.findAll('nav a').filter((el) => el.attributes('data-testid') !== 'fab').length).toBe(4)
    expect(w.find('[data-testid="fab"]').exists()).toBe(true)
    expect(w.html()).toContain('مواعيدي')
    expect(w.html()).toContain('الإشعارات')
  })

  it('authed customer name appears in the profile dropdown label', () => {
    pageProps = { auth: { user: { id: 1, name: 'أحمد', role: 'customer' } }, notifications: null }
    currentUrl = '/portal'
    const w = mount(ClientShell, { slots: { default: '<p>x</p>' } })
    expect(w.find('[data-testid="profile-menu"]').html()).toContain('أحمد')
  })
})
