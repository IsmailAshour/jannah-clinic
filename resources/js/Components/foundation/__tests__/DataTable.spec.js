import { mount } from '@vue/test-utils'
import { describe, it, expect } from 'vitest'
import DataTable from '../DataTable.vue'

const columns = [{ key: 'name', label: 'الاسم' }, { key: 'role', label: 'الدور', align: 'end' }]

describe('DataTable', () => {
  it('renders the empty message when rows is empty', () => {
    const w = mount(DataTable, { props: { columns, rows: [], emptyText: 'لا سجلات' } })
    expect(w.text()).toContain('لا سجلات')
  })
  it('renders a row per item with cell values', () => {
    const w = mount(DataTable, { props: { columns, rows: [{ name: 'سارة', role: 'عميل' }, { name: 'أحمد', role: 'طبيب' }] } })
    expect(w.text()).toContain('سارة')
    expect(w.text()).toContain('أحمد')
    expect(w.findAll('tbody tr')).toHaveLength(2)
  })
  it('renders column headers', () => {
    const w = mount(DataTable, { props: { columns, rows: [] } })
    expect(w.text()).toContain('الاسم')
    expect(w.text()).toContain('الدور')
  })
})
