import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import PaymentMethodPicker from '../PaymentMethodPicker.vue'

describe('PaymentMethodPicker', () => {
  it('hides entirely when service does not have loyalty_enabled', () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: false, loyaltyRedemptionPoints: 500, loyaltyBalance: 1000, modelValue: 'cash' },
    })
    expect(w.find('[data-testid="picker"]').exists()).toBe(false)
  })

  it('shows insufficient-balance hint when balance < cost', () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: true, loyaltyRedemptionPoints: 500, loyaltyBalance: 100, modelValue: 'cash' },
    })
    expect(w.find('[data-testid="picker"]').exists()).toBe(true)
    expect(w.text()).toContain('لا يكفي')
    expect(w.findAll('input[type="radio"]').length).toBe(1) // only cash option, no loyalty radio
  })

  it('shows both options when balance >= cost', () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: true, loyaltyRedemptionPoints: 500, loyaltyBalance: 600, modelValue: 'cash' },
    })
    expect(w.findAll('input[type="radio"]').length).toBe(2)
    expect(w.text()).toContain('500')
    expect(w.text()).toContain('600')
  })

  it('emits update:modelValue when picking loyalty', async () => {
    const w = mount(PaymentMethodPicker, {
      props: { loyaltyEnabled: true, loyaltyRedemptionPoints: 500, loyaltyBalance: 600, modelValue: 'cash' },
    })
    const loyaltyRadio = w.findAll('input[type="radio"]')[1]
    await loyaltyRadio.setValue(true)
    expect(w.emitted('update:modelValue')?.[0]).toEqual(['loyalty_points'])
  })
})
