<script setup>
import { defineComponent, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarProvider,
  SidebarTrigger,
  useSidebar,
} from '@/Components/ui/sidebar'

// TODO(P1): consider Inertia persistent layout (defineOptions layout) to keep shell state across navigations.
const nav = [
  { label: 'لوحة التحكم', href: '/admin' },
  {
    label: 'الخدمات',
    children: [
      { label: 'تصنيفات الخدمات', href: '/admin/catalog/categories' },
      { label: 'الخدمات', href: '/admin/catalog/services' },
    ],
  },
  {
    label: 'العيادة',
    children: [
      { label: 'الأطباء', href: '/admin/doctors' },
      { label: 'المواعيد', href: '/admin/appointments' },
      { label: 'حجز موعد  لعميل', href: '/admin/booking' },
    ],
  },
  { label: 'مناطق التغطية', href: '/admin/coverage' },
  { label: 'الإعدادات', href: '/admin/settings' },
]
const page = usePage()
function isActive(href) {
  const current = page.url
  if (href === '/admin') return current === '/admin' || current === '/admin/'
  return current === href || current.startsWith(href + '/')
}

// Inertia <Link> that also closes the mobile sheet on click. Uses the
// sidebar context provided by <SidebarProvider> below. Defined inline so the
// AdminShell layout stays a single module; the closure captures useSidebar().
const NavLink = defineComponent({
  name: 'AdminShellNavLink',
  props: {
    href: { type: String, required: true },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
  },
  setup(props) {
    const { isMobile, setOpenMobile } = useSidebar()
    function onClick() {
      if (isMobile.value) setOpenMobile(false)
    }
    return () =>
      h(
        Link,
        {
          href: props.href,
          onClick,
          'aria-current': props.active ? 'page' : undefined,
          class: 'block w-full',
        },
        () => props.label,
      )
  },
})
</script>

<template>
  <SidebarProvider>
    <!--
      Sidebar — RTL-correct side via Tailwind logical mapping:
      Arabic shell uses dir="rtl" globally, so reka-ui's default `side="left"`
      flips to inline-start = visually-right under RTL. The mobile Sheet
      inherits the same side and slides from the correct edge.
      `collapsible="offcanvas"` is the only correct collapse mode here:
      the nav has no icons, so icon-rail would render an empty column.
    -->
    <Sidebar collapsible="offcanvas">
      <SidebarHeader>
        <div class="text-lg font-bold px-2 py-1 whitespace-nowrap">عيادة جنّة</div>
      </SidebarHeader>
      <SidebarContent>
        <template v-for="n in nav" :key="n.label">
          <!-- Leaf entry -->
          <SidebarGroup v-if="n.href">
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem>
                  <SidebarMenuButton as-child :is-active="isActive(n.href)">
                    <NavLink :href="n.href" :label="n.label" :active="isActive(n.href)" />
                  </SidebarMenuButton>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
          <!-- Group with children -->
          <SidebarGroup v-else>
            <SidebarGroupLabel>{{ n.label }}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                <SidebarMenuItem v-for="c in n.children" :key="c.href">
                  <SidebarMenuSub>
                    <SidebarMenuSubItem>
                      <SidebarMenuSubButton as-child :is-active="isActive(c.href)">
                        <NavLink :href="c.href" :label="c.label" :active="isActive(c.href)" />
                      </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                  </SidebarMenuSub>
                </SidebarMenuItem>
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        </template>
      </SidebarContent>
    </Sidebar>
    <SidebarInset>
      <header class="z-sticky h-16 bg-surface-card border-b border-border-default flex items-center px-6">
        <!--
          Single trigger: shadcn-vue's SidebarTrigger uses useSidebar() to
          toggle the mobile sheet (< md) or desktop offcanvas (≥ md)
          automatically — no hand-rolled mobile/desktop split.
        -->
        <SidebarTrigger class="-ms-2 me-auto" aria-label="القائمة" />
        <div class="ms-auto">
          <Link href="/logout" method="post" as="button" class="text-sm text-text-secondary hover:text-text-primary">تسجيل الخروج</Link>
        </div>
      </header>
      <main class="flex-1"><slot /></main>
    </SidebarInset>
  </SidebarProvider>
</template>
