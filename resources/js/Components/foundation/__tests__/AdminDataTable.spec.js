import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminDataTable from '../AdminDataTable.vue'

describe('AdminDataTable', () => {
  it('renders rows', () => {
    const columns = [
      { accessorKey: 'name', header: 'Name', meta: { label: 'Name' } },
      { accessorKey: 'role', header: 'Role', meta: { label: 'Role' } },
    ]
    const data = [
      { name: 'Alice', role: 'Doctor' },
      { name: 'Bob', role: 'Manager' },
    ]
    const wrapper = mount(AdminDataTable, { props: { columns, data } })
    expect(wrapper.text()).toContain('Alice')
    expect(wrapper.text()).toContain('Bob')
  })

  it('shows empty state when data is empty', () => {
    const wrapper = mount(AdminDataTable, {
      props: { columns: [{ accessorKey: 'x', header: 'x', meta: { label: 'x' } }], data: [], emptyText: 'فارغ' },
    })
    expect(wrapper.text()).toContain('فارغ')
  })
})
