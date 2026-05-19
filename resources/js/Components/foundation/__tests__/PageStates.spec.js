import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import PageStates from '../PageStates.vue'
const slots = { loading:'<div>L</div>', error:'<div>E</div>', empty:'<div>M</div>', default:'<div>S</div>' }
describe('PageStates', () => {
  it('loading first', () => { const w=mount(PageStates,{props:{loading:true,error:null,isEmpty:true},slots}); expect(w.text()).toBe('L') })
  it('error when not loading', () => { const w=mount(PageStates,{props:{loading:false,error:'x',isEmpty:true},slots}); expect(w.text()).toBe('E') })
  it('empty when no error', () => { const w=mount(PageStates,{props:{loading:false,error:null,isEmpty:true},slots}); expect(w.text()).toBe('M') })
  it('success otherwise', () => { const w=mount(PageStates,{props:{loading:false,error:null,isEmpty:false},slots}); expect(w.text()).toBe('S') })
})
