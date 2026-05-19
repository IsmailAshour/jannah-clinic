import { mount, flushPromises } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import BookingWizard from '../BookingWizard.vue'

// Stub foundation components so we don't need full CSS/env
const stubComponents = {
  FormGroup: {
    name: 'FormGroup',
    props: ['label', 'name', 'error', 'required'],
    template: '<div><slot :describedby="name + \'-desc\'" /></div>',
  },
  FormSection: {
    name: 'FormSection',
    props: ['title'],
    template: '<section><slot /></section>',
  },
  PageStates: {
    name: 'PageStates',
    props: ['isEmpty'],
    template: '<div><slot v-if="!isEmpty" /><slot v-else name="empty" /></div>',
  },
  Button: {
    name: 'Button',
    props: ['disabled', 'variant'],
    template: '<button :disabled="disabled"><slot /></button>',
  },
  Input: {
    name: 'Input',
    props: ['modelValue', 'type', 'placeholder', 'dir'],
    emits: ['update:modelValue'],
    template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
  },
}

// Deterministic future month: the first day of next month is always fully
// in the future and entirely within a single calendar month, so every day
// button in that month is enabled iff it is in the available set.
function pad(n) { return String(n).padStart(2, '0') }
const NOW = new Date()
const NEXT_MONTH = new Date(NOW.getFullYear(), NOW.getMonth() + 1, 1)
const FM_YEAR = NEXT_MONTH.getFullYear()
const FM_MONTH = NEXT_MONTH.getMonth() // 0-based
const day = (d) => `${FM_YEAR}-${pad(FM_MONTH + 1)}-${pad(d)}`

// URL-aware fetch mock: days endpoint vs slots endpoint, order-independent.
function makeFetchMock(daysData, slotsData) {
  return vi.fn(async (url) => {
    if (String(url).includes('/availability/days')) {
      return { ok: true, json: async () => daysData }
    }
    return { ok: true, json: async () => slotsData }
  })
}

// Open step 3 with the calendar anchored on next month.
// The wizard's watch(doctorId) resets serviceId to null on the next flush,
// so set doctorId first, let that settle, THEN set serviceId. Seed
// selectedDate after serviceId (its watcher clears it) so the real
// MonthCalendar opens on next month.
async function openStep3NextMonth(wrapper) {
  wrapper.vm.doctorId = 1
  await wrapper.vm.$nextTick()
  wrapper.vm.serviceId = 10
  await wrapper.vm.$nextTick()
  wrapper.vm.selectedDate = day(1)
  wrapper.vm.step = 3
  await wrapper.vm.$nextTick()
  await flushPromises()
  await wrapper.vm.$nextTick()
}

const sampleDoctors = [
  {
    id: 1,
    name: 'د. سارة',
    services: [
      { id: 10, name: 'استشارة عامة', base_price: 100, price_override: null, duration_minutes: 30, home_service_enabled: true },
      { id: 11, name: 'فحص خاص', base_price: 200, price_override: null, duration_minutes: 60, home_service_enabled: false },
    ],
  },
  {
    id: 2,
    name: 'د. أحمد',
    services: [
      { id: 20, name: 'استشارة متخصصة', base_price: 150, price_override: null, duration_minutes: 45, home_service_enabled: true },
    ],
  },
]

const sampleAreas = [
  { id: 1, name: 'رام الله' },
  { id: 2, name: 'نابلس' },
]

function mountWizard(overrideProps = {}) {
  return mount(BookingWizard, {
    props: {
      doctors: sampleDoctors,
      coverageAreas: sampleAreas,
      availabilityUrl: '/portal/availability',
      availabilityDaysUrl: '/portal/availability/days',
      homeSurchargePct: 30,
      customerPicker: false,
      ...overrideProps,
    },
    global: {
      components: stubComponents,
    },
  })
}

describe('BookingWizard', () => {
  beforeEach(() => {
    global.fetch = vi.fn()
  })

  it('renders step 1 with two delivery-mode radio options', () => {
    const wrapper = mountWizard()
    const radios = wrapper.findAll('input[type="radio"]')
    const values = radios.map(r => r.element.value)
    expect(values).toContain('center')
    expect(values).toContain('home')
  })

  it('shows coverage-area and address fields when home mode is selected', async () => {
    const wrapper = mountWizard()
    // Initially coverage fields should not be visible
    expect(wrapper.find('[data-testid="home-fields"]').exists()).toBe(false)

    // Select home delivery
    const homeRadio = wrapper.find('input[value="home"]')
    await homeRadio.setValue(true)
    await homeRadio.trigger('change')

    expect(wrapper.find('[data-testid="home-fields"]').exists()).toBe(true)

    // Coverage area select should be visible
    const coverageSelect = wrapper.find('#coverage_area_id')
    expect(coverageSelect.exists()).toBe(true)

    // Address input should be visible
    const addressInput = wrapper.find('#address_text')
    expect(addressInput.exists()).toBe(true)
  })

  it('filters services by chosen doctor on step 2', async () => {
    const wrapper = mountWizard()

    // Navigate to step 2
    await wrapper.find('button:not([disabled])').trigger('click')

    // Select doctor 1
    const doctorSelect = wrapper.find('#doctor')
    await doctorSelect.setValue(1)
    await doctorSelect.trigger('change')

    const serviceOptions = wrapper.findAll('#service option').filter(o => o.element.value !== '')
    expect(serviceOptions).toHaveLength(2)
    expect(wrapper.text()).toContain('استشارة عامة')
    expect(wrapper.text()).toContain('فحص خاص')

    // Switch to doctor 2
    await doctorSelect.setValue(2)
    await doctorSelect.trigger('change')

    const serviceOptions2 = wrapper.findAll('#service option').filter(o => o.element.value !== '')
    expect(serviceOptions2).toHaveLength(1)
    expect(wrapper.text()).toContain('استشارة متخصصة')
  })

  it('filters home-only services when delivery mode is home on step 2', async () => {
    const wrapper = mountWizard()

    // Set delivery mode to home via radio (step 1)
    const homeRadio = wrapper.find('input[value="home"]')
    await homeRadio.setValue(true)
    await homeRadio.trigger('change')

    // Navigate to step 2 (skip step 1 validation by navigating directly)
    wrapper.vm.step = 2

    await wrapper.vm.$nextTick()

    // Select doctor 1 — has one home-enabled service (id=10) and one not (id=11)
    const doctorSelect = wrapper.find('#doctor')
    await doctorSelect.setValue(1)
    await doctorSelect.trigger('change')

    const serviceOptions = wrapper.findAll('#service option').filter(o => o.element.value !== '')
    expect(serviceOptions).toHaveLength(1)
    expect(wrapper.text()).toContain('استشارة عامة')
    expect(wrapper.text()).not.toContain('فحص خاص')
  })

  it('shows empty state text when fetch returns no slots', async () => {
    const mockFetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => [],
    })
    vi.stubGlobal('fetch', mockFetch)

    const wrapper = mountWizard()

    // Navigate to step 3
    wrapper.vm.step = 3
    await wrapper.vm.$nextTick()

    // Use the test helper that sets values inside component scope and calls fetchSlots
    await wrapper.vm.fetchSlotsForTest(1, 10, '2026-06-02')

    // Wait for promises and DOM update
    await flushPromises()
    await wrapper.vm.$nextTick()

    // fetch should have been called
    expect(mockFetch).toHaveBeenCalled()
    // slotsEmpty should be true after empty response
    expect(wrapper.vm.slotsEmpty).toBe(true)
    expect(wrapper.text()).toContain('لا فترات متاحة')

    vi.unstubAllGlobals()
  })

  it('shows error message when fetch fails (non-ok response)', async () => {
    const mockFetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 500,
      json: async () => ({}),
    })
    vi.stubGlobal('fetch', mockFetch)

    const wrapper = mountWizard()
    wrapper.vm.step = 3
    await wrapper.vm.$nextTick()

    await wrapper.vm.fetchSlotsForTest(1, 10, '2026-06-02')
    await flushPromises()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.slotsError).toBe(true)
    expect(wrapper.vm.slotsEmpty).toBe(false)
    expect(wrapper.text()).toContain('تعذّر تحميل الفترات')

    vi.unstubAllGlobals()
  })

  it('clears stale slots when serviceId changes (I3 watcher)', async () => {
    const mockFetch = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => [{ start: '2026-06-02T09:00:00+03:00', end: '2026-06-02T09:30:00+03:00', label: '09:00' }],
    })
    vi.stubGlobal('fetch', mockFetch)

    const wrapper = mountWizard()

    // Simulate having fetched slots for a service
    await wrapper.vm.fetchSlotsForTest(1, 10, '2026-06-02')
    await flushPromises()

    expect(wrapper.vm.slots.length).toBeGreaterThan(0)
    expect(wrapper.vm.selectedDate).toBe('2026-06-02')

    // Now change serviceId (as happens when doctor/delivery_mode changes)
    wrapper.vm.serviceId = 11
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.selectedDate).toBe('')
    expect(wrapper.vm.slots).toHaveLength(0)
    expect(wrapper.vm.slotsEmpty).toBe(false)
    expect(wrapper.vm.slotsError).toBe(false)
    expect(wrapper.vm.selectedStart).toBeNull()

    vi.unstubAllGlobals()
  })

  it('selecting an available calendar day triggers the slot fetch and renders slots', async () => {
    const mockFetch = makeFetchMock(
      [day(2), day(9)],
      [{ start: `${day(2)}T09:00:00+03:00`, end: `${day(2)}T09:30:00+03:00`, label: '09:00' }],
    )
    vi.stubGlobal('fetch', mockFetch)

    const wrapper = mountWizard()
    await openStep3NextMonth(wrapper)

    // Days endpoint was hit
    const daysCall = mockFetch.mock.calls.find(c => String(c[0]).includes('/availability/days'))
    expect(daysCall).toBeTruthy()
    expect(wrapper.vm.availableDays).toEqual([day(2), day(9)])

    // The real calendar renders an enabled button for an available day
    const dayBtn = wrapper.find(`button[data-date="${day(2)}"]`)
    expect(dayBtn.exists()).toBe(true)
    expect(dayBtn.element.disabled).toBe(false)
    await dayBtn.trigger('click')
    await flushPromises()
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.selectedDate).toBe(day(2))
    // Slot endpoint was hit and slots rendered below
    const slotCall = mockFetch.mock.calls.find(
      c => String(c[0]).includes('/availability?') && String(c[0]).includes(`date=${day(2)}`),
    )
    expect(slotCall).toBeTruthy()
    expect(wrapper.vm.slots.length).toBe(1)
    expect(wrapper.text()).toContain('09:00')

    vi.unstubAllGlobals()
  })

  it('a day not in the available set is disabled / not selectable', async () => {
    const mockFetch = makeFetchMock([day(2)], [])
    vi.stubGlobal('fetch', mockFetch)

    const wrapper = mountWizard()
    await openStep3NextMonth(wrapper)

    // day(15) is NOT in the available set → its calendar button is disabled
    const unavailable = wrapper.find(`button[data-date="${day(15)}"]`)
    expect(unavailable.exists()).toBe(true)
    expect(unavailable.element.disabled).toBe(true)
    expect(unavailable.attributes('data-available')).toBe('false')

    // Clicking it does not select it
    await unavailable.trigger('click')
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.selectedDate).not.toBe(day(15))

    vi.unstubAllGlobals()
  })

  it('shows the no-available-days hint when the days response is empty', async () => {
    const mockFetch = makeFetchMock([], [])
    vi.stubGlobal('fetch', mockFetch)

    const wrapper = mountWizard()
    await openStep3NextMonth(wrapper)

    expect(wrapper.vm.availableDays).toEqual([])
    expect(wrapper.text()).toContain('لا أيام متاحة هذا الشهر')

    vi.unstubAllGlobals()
  })

  it('renders customer picker UI in admin mode (step 0)', async () => {
    const wrapper = mountWizard({
      customerPicker: true,
      customers: [{ id: 1, name: 'محمد', email: null, phone: '0591234567' }],
    })

    // Should be on step 0 with customer picker
    const radios = wrapper.findAll('input[type="radio"]')
    const values = radios.map(r => r.element.value)
    expect(values).toContain('existing')
    expect(values).toContain('new')
  })
})
