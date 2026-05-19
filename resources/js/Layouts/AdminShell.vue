<script setup>
import { defineComponent, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  CalendarDays,
  CalendarPlus,
  LayoutDashboard,
  MapPin,
  Package,
  Settings,
  Tags,
  Users,
} from 'lucide-vue-next'
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
// Leaf items carry an `icon` (lucide-vue-next component reference) rendered
// inline-start of the label inside SidebarMenu(Sub)Button. Group headings stay
// text-only — matches shadcn-vue's default SidebarGroupLabel pattern and keeps
// the visual hierarchy clean (label icon would compete with leaf icons).
const nav = [
  { label: 'لوحة التحكم', href: '/admin', icon: LayoutDashboard },
  {
    label: 'الخدمات',
    children: [
      { label: 'تصنيفات الخدمات', href: '/admin/catalog/categories', icon: Tags },
      { label: 'الخدمات', href: '/admin/catalog/services', icon: Package },
    ],
  },
  {
    label: 'العيادة',
    children: [
      { label: 'الأطباء', href: '/admin/doctors', icon: Users },
      { label: 'المواعيد', href: '/admin/appointments', icon: CalendarDays },
      { label: 'حجز موعد  لعميل', href: '/admin/booking', icon: CalendarPlus },
    ],
  },
  { label: 'مناطق التغطية', href: '/admin/coverage', icon: MapPin },
  { label: 'الإعدادات', href: '/admin/settings', icon: Settings },
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
// Renders the default slot inside the <Link> so the parent can supply
// `[icon, <span>label</span>]` — flexbox + dir="rtl" handles inline-start
// placement (icon visually right of the label) without physical classes.
// `label` is still required (used for accessibility/active-state symmetry
// even though the visible text now comes from the slot).
const NavLink = defineComponent({
  name: 'AdminShellNavLink',
  props: {
    href: { type: String, required: true },
    label: { type: String, required: true },
    active: { type: Boolean, default: false },
  },
  setup(props, { slots }) {
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
          // flex + items-center + gap-2 here gives the Link itself the
          // icon/label layout — SidebarMenu(Sub)Button passes its row
          // styles via the sub-button container, but the Link is the
          // immediate flex parent of the icon+span.
          class: 'flex w-full items-center gap-2',
        },
        () => (slots.default ? slots.default() : props.label),
      )
  },
})
</script>

<template>
  <SidebarProvider>
    <!--
      Sidebar — RTL-correct side: shell is dir="rtl" globally, and reka-ui's
      `side` is the *visual* side (it does NOT auto-flip under dir). For
      Arabic, the visually-right edge is the natural inline-start, so we
      pass side="right" — desktop pins the panel to the right edge, and the
      mobile Sheet slides in from the right edge (SheetContent already ships
      data-[side=right] slide-in animation classes; verified in vendored
      Components/ui/sheet/SheetContent.vue).

      `collapsible="offcanvas"` is the only correct collapse mode here: the
      nav now has icons but the offcanvas mode hides the panel entirely on
      collapse, which matches the existing P1 UX (no icon rail).
    -->
    <Sidebar side="right" collapsible="offcanvas">
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
                    <NavLink :href="n.href" :label="n.label" :active="isActive(n.href)">
                      <!--
                        Icon first in source order: flexbox row direction
                        flips visually under dir="rtl" so the icon sits at
                        the inline-start (visually right of the label). No
                        physical (left/right) utilities needed — gap-2 from
                        sidebarMenuButtonVariants handles spacing.
                      -->
                      <component :is="n.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                      <span>{{ n.label }}</span>
                    </NavLink>
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
                        <NavLink :href="c.href" :label="c.label" :active="isActive(c.href)">
                          <component :is="c.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                          <span>{{ c.label }}</span>
                        </NavLink>
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
          automatically — no hand-rolled mobile/desktop split. `me-auto`
          keeps it at the inline-start of the header (visually right under
          RTL = adjacent to the sidebar's right edge).
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
