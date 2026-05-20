import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import Index from '../Index.vue'

vi.mock('@inertiajs/vue3', () => ({
  Link: { template: '<a><slot /></a>' },
  router: { get: vi.fn() },
  usePage: () => ({ props: { auth: { user: { role: 'customer' } }, notifications: null } }),
}))
vi.mock('@/Layouts/ClientShell.vue', () => ({ default: { template: '<div><slot /></div>' } }))

describe('Portal/Loyalty Index', () => {
  it('renders the balance', () => {
    const w = mount(Index, {
      props: {
        balance: 1247,
        summary: { earned: 3200, redeemed: 1953 },
        ledger: { data: [], links: [], last_page: 1 },
        tab: 'all',
      },
    })
    expect(w.text()).toContain('1247')
  })

  it('renders ledger rows with Arabic reason labels', () => {
    const w = mount(Index, {
      props: {
        balance: 100,
        summary: { earned: 100, redeemed: 0 },
        ledger: {
          data: [{ id: 1, points_delta: 100, balance_after: 100, reason: 'earned_from_payment', notes: null, created_at: '2026-05-20T10:00:00Z' }],
          links: [],
          last_page: 1,
        },
        tab: 'all',
      },
    })
    expect(w.text()).toContain('كسب من زيارة')
  })

  it('renders empty state when no ledger rows', () => {
    const w = mount(Index, {
      props: {
        balance: 0,
        summary: { earned: 0, redeemed: 0 },
        ledger: { data: [], links: [], last_page: 1 },
        tab: 'all',
      },
    })
    expect(w.text()).toContain('لا توجد حركات')
  })
})
