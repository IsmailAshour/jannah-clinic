import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import AuthGuardLink from '../AuthGuardLink.vue'

let pageProps = { auth: { user: null } }

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a :href="href"><slot /></a>', props: ['href'] },
  usePage: () => ({ props: pageProps }),
}))

describe('AuthGuardLink', () => {
  it('renders authed href when user is set', () => {
    pageProps = { auth: { user: { id: 1, name: 'x' } } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking' },
      slots: { default: 'احجز' },
    })
    expect(w.find('a').attributes('href')).toBe('/portal/booking')
  })

  it('renders /login?intent=… when user is null', () => {
    pageProps = { auth: { user: null } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking' },
      slots: { default: 'احجز' },
    })
    expect(w.find('a').attributes('href')).toBe('/login?intent=booking')
  })

  it('encodes context into query string for guest', () => {
    pageProps = { auth: { user: null } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking', context: { service: 5, doctor: 3 } },
    })
    expect(w.find('a').attributes('href')).toBe('/login?intent=booking&service=5&doctor=3')
  })

  it('skips null/empty context values', () => {
    pageProps = { auth: { user: null } }
    const w = mount(AuthGuardLink, {
      props: { intent: 'booking', authedHref: '/portal/booking', context: { service: 5, doctor: null, category: '' } },
    })
    expect(w.find('a').attributes('href')).toBe('/login?intent=booking&service=5')
  })
})
