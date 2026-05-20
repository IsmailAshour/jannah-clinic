import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import NotificationBell from '../NotificationBell.vue'

let pageProps = { notifications: null }

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a><slot /></a>' },
  usePage: () => ({ props: pageProps }),
}))

describe('NotificationBell', () => {
  it('hides badge when unread_count is 0', () => {
    pageProps = { notifications: { unread_count: 0 } }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').exists()).toBe(false)
  })

  it('shows badge with count when unread_count > 0', () => {
    pageProps = { notifications: { unread_count: 3 } }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').text()).toBe('3')
  })

  it('caps display at 99+ for large counts', () => {
    pageProps = { notifications: { unread_count: 150 } }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').text()).toBe('99+')
  })

  it('hides badge when share is null (guest)', () => {
    pageProps = { notifications: null }
    const w = mount(NotificationBell, { props: { href: '/portal/notifications' } })
    expect(w.find('[data-testid="bell-badge"]').exists()).toBe(false)
  })
})
