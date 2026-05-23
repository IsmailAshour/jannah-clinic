<script setup>
import { computed, defineComponent, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  BarChart3,
  Briefcase,
  CalendarDays,
  CalendarPlus,
  ChevronRight,
  ChevronsUpDown,
  Contact2,
  Globe,
  LayoutDashboard,
  LogOut,
  MailOpen,
  MapPin,
  Package,
  Receipt,
  Settings,
  Stethoscope,
  Tags,
  User as UserIcon,
  Users,
} from 'lucide-vue-next'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarProvider,
  SidebarRail,
  SidebarTrigger,
  useSidebar,
} from '@/Components/ui/sidebar'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/Components/ui/collapsible'
import { NotificationBell } from '@/Components/foundation'

// TODO(P1): consider Inertia persistent layout (defineOptions layout) to keep shell state across navigations.
//
// Nav model — sidebar-07 pattern (icon-collapsible). Every entry carries an
// icon: leaves render the icon as the only visible cue when the sidebar is
// collapsed to icon-rail width; group icons (Briefcase / Stethoscope) act as
// the same cue for grouped sections. Children carry icons too so they show
// next to the label in expanded mode (sub-buttons hide entirely in icon mode
// — see SidebarMenuSubButton.vue: group-data-[collapsible=icon]:hidden).
// Labels are user-authored Arabic and preserved verbatim, including the
// deliberate double space in "حجز موعد  لعميل".
const nav = [
  { type: 'leaf', label: 'لوحة التحكم', href: '/admin', icon: LayoutDashboard },
  {
    type: 'group',
    label: 'الخدمات',
    icon: Briefcase,
    children: [
      { label: 'تصنيفات الخدمات', href: '/admin/catalog/categories', icon: Tags },
      { label: 'الخدمات', href: '/admin/catalog/services', icon: Package },
    ],
  },
  {
    type: 'group',
    label: 'العيادة',
    icon: Stethoscope,
    children: [
      { label: 'العملاء', href: '/admin/customers', icon: Contact2 },
      { label: 'الفريق الطبي', href: '/admin/doctors', icon: Users },
      { label: 'الفريق الإداري', href: '/admin/staff', icon: UserIcon },
      { label: 'المواعيد', href: '/admin/appointments', icon: CalendarDays },
      { label: 'المدفوعات', href: '/admin/payments', icon: Receipt, badgeKey: 'submitted_payments' },
      { label: 'حجز موعد  لعميل', href: '/admin/booking', icon: CalendarPlus },
    ],
  },
  { type: 'leaf', label: 'الرسائل', href: '/admin/messages', icon: MailOpen },
  { type: 'leaf', label: 'التقارير', href: '/admin/reports', icon: BarChart3, managerOnly: true },
  { type: 'leaf', label: 'مناطق التغطية', href: '/admin/coverage', icon: MapPin },
  { type: 'leaf', label: 'الإعدادات', href: '/admin/settings', icon: Settings },
]
const page = usePage()
// Filter manager-only entries when the current user is not a manager. Mirrors
// the backend role:manager gate on /admin/reports — sidebar hides what the
// backend would refuse anyway.
const visibleNav = computed(() => {
  const isManager = page.props?.auth?.user?.role === 'manager'
  return nav.filter((item) => !item.managerOnly || isManager)
})
function isActive(href) {
  const current = page.url
  if (href === '/admin') return current === '/admin' || current === '/admin/'
  return current === href || current.startsWith(href + '/')
}
// A group is default-open when any of its children is the current route — so
// the active sub-item is visible immediately on first render. Plain `.some()`
// over children; cheap, no reactivity surprises.
function groupHasActiveChild(group) {
  return group.children?.some((c) => isActive(c.href)) ?? false
}

// Auth context for the footer card (name + email + initial avatar). The
// dropdown lives in the SidebarFooter so it sits at the bottom of the rail —
// shadcn's sidebar-07 'user nav' pattern.
const authedUser = computed(() => page.props?.auth?.user ?? null)
const userInitial = computed(() => {
  const n = (authedUser.value?.name ?? '').trim()
  return n ? Array.from(n)[0] : 'م'
})
const userRoleLabel = computed(() => {
  switch (authedUser.value?.role) {
    case 'manager':      return 'مدير'
    case 'doctor':       return 'طبيب'
    case 'receptionist': return 'استقبال'
    default:             return ''
  }
})

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
      mobile Sheet slides in from the right edge.

      `collapsible="icon"` — sidebar-07 pattern: when collapsed the panel
      narrows to an icon rail (--sidebar-width-icon) showing only the icons,
      with tooltips on hover (the tooltip plumbing lives in
      SidebarMenuButton.vue, gated by the sidebar `state` so tooltips only
      appear when state === 'collapsed').
    -->
    <Sidebar side="right" collapsible="icon">
      <SidebarHeader>
        <!--
          Brand line — in icon-collapsed mode the text is hidden via the
          group-data variant (SidebarProvider's wrapper exposes
          data-collapsible="icon" on the peer), so only the empty header
          gutter remains. This avoids overflow / clipping at the narrow
          icon-rail width. The brand intentionally has no icon glyph — the
          rail's content cue is the row of menu icons below.
        -->
        <div class="text-lg font-bold px-2 py-1 whitespace-nowrap group-data-[collapsible=icon]:hidden">{{ page.props?.clinic?.name ?? 'عيادة جنّة' }}</div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              <template v-for="n in visibleNav" :key="n.label">
                <!-- Leaf entry: tooltip prop is what makes the Arabic label
                     appear on hover when the sidebar is in icon-collapsed
                     mode (TooltipContent is :hidden="state !== 'collapsed'"
                     inside SidebarMenuButton). -->
                <SidebarMenuItem v-if="n.type === 'leaf'">
                  <SidebarMenuButton as-child :tooltip="n.label" :is-active="isActive(n.href)">
                    <NavLink :href="n.href" :label="n.label" :active="isActive(n.href)">
                      <component :is="n.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                      <span>{{ n.label }}</span>
                    </NavLink>
                  </SidebarMenuButton>
                </SidebarMenuItem>

                <!--
                  Group: Collapsible wraps the SidebarMenuItem so the parent
                  row toggles its sub-list in place. `default-open` honours
                  the current route — if a child matches, the parent group
                  is expanded on first render. `group/collapsible` exposes
                  `data-state=open|closed` to descendants so the ChevronRight
                  can rotate via `group-data-[state=open]/collapsible:rotate-90`.
                -->
                <Collapsible
                  v-else
                  v-slot="{ open }"
                  as-child
                  :default-open="groupHasActiveChild(n)"
                  class="group/collapsible"
                >
                  <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                      <SidebarMenuButton :tooltip="n.label">
                        <component :is="n.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                        <span>{{ n.label }}</span>
                        <!--
                          Chevron: `ms-auto` keeps it inline-end (RTL-safe).
                          Under RTL, the chevron points visually toward the
                          inline-start edge of the row; rotate-90 on open
                          turns it to point down, matching the upstream
                          sidebar-07 visual cue for an open group.
                          `rtl:rotate-180` on the base reorients the closed
                          chevron so it reads correctly as "expand" in RTL.
                        -->
                        <ChevronRight class="ms-auto rtl:rotate-180 transition-transform group-data-[state=open]/collapsible:rotate-90" />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub>
                        <SidebarMenuSubItem v-for="c in n.children" :key="c.href">
                          <SidebarMenuSubButton as-child :is-active="isActive(c.href)">
                            <NavLink :href="c.href" :label="c.label" :active="isActive(c.href)">
                              <component :is="c.icon" class="h-4 w-4 shrink-0" aria-hidden="true" />
                              <span>{{ c.label }}</span>
                              <!--
                                Sidebar badge: shows a numeric pill (e.g. count of submitted payments
                                awaiting review) for staff only. Source: HandleInertiaRequests shares
                                adminCounts gated to isStaff(); customers/guests never see the count.
                              -->
                              <span
                                v-if="c.badgeKey && page.props?.adminCounts?.[c.badgeKey] > 0"
                                class="ms-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1.5 text-[10px] font-bold text-white"
                              >{{ page.props.adminCounts[c.badgeKey] }}</span>
                            </NavLink>
                          </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>
              </template>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <!-- User nav footer (sidebar-07 pattern): a 'visit site' shortcut and
           an avatar + name dropdown for settings/logout. In icon-collapsed
           mode only the icons show; meta text + chevron are hidden via
           group-data variant. -->
      <SidebarFooter>
        <SidebarMenu>
          <!-- Visit public site (admin → /) -->
          <SidebarMenuItem>
            <SidebarMenuButton as-child tooltip="زيارة الموقع">
              <a href="/" class="flex w-full items-center gap-2">
                <Globe class="h-4 w-4 shrink-0" aria-hidden="true" />
                <span>زيارة الموقع</span>
              </a>
            </SidebarMenuButton>
          </SidebarMenuItem>

          <SidebarMenuItem>
            <DropdownMenu>
              <DropdownMenuTrigger as-child>
                <SidebarMenuButton
                  size="lg"
                  class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                >
                  <span class="grid place-items-center w-8 h-8 rounded-lg bg-brand text-white font-extrabold text-sm shrink-0">
                    {{ userInitial }}
                  </span>
                  <div class="grid flex-1 text-start text-sm leading-tight group-data-[collapsible=icon]:hidden min-w-0">
                    <span class="truncate font-bold">{{ authedUser?.name }}</span>
                    <span class="truncate text-xs text-text-tertiary">
                      {{ userRoleLabel }}<span v-if="authedUser?.email"> · {{ authedUser.email }}</span>
                    </span>
                  </div>
                  <ChevronsUpDown class="ms-auto h-4 w-4 group-data-[collapsible=icon]:hidden" aria-hidden="true" />
                </SidebarMenuButton>
              </DropdownMenuTrigger>
              <DropdownMenuContent
                side="top"
                align="end"
                :side-offset="4"
                class="min-w-56 rounded-lg"
              >
                <DropdownMenuLabel class="p-0">
                  <div class="flex items-center gap-2.5 px-2 py-2.5 text-start">
                    <span class="grid place-items-center w-9 h-9 rounded-lg bg-brand text-white font-extrabold shrink-0">
                      {{ userInitial }}
                    </span>
                    <div class="grid flex-1 leading-tight min-w-0">
                      <span class="truncate text-sm font-bold">{{ authedUser?.name }}</span>
                      <span v-if="authedUser?.email" class="truncate text-xs text-text-tertiary" dir="ltr">{{ authedUser.email }}</span>
                    </div>
                  </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                  <Link href="/admin/settings" class="flex items-center gap-2 w-full cursor-pointer">
                    <Settings class="h-4 w-4 text-text-secondary" aria-hidden="true" />
                    <span>الإعدادات</span>
                  </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                  <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="flex items-center gap-2 w-full cursor-pointer text-danger"
                  >
                    <LogOut class="h-4 w-4" aria-hidden="true" />
                    <span>تسجيل الخروج</span>
                  </Link>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>

      <!--
        Rail: thin edge handle that toggles collapse on click. Part of the
        sidebar-07 pattern; positions itself via data-side/data-collapsible
        attributes on the parent peer wrapper.
      -->
      <SidebarRail />
    </Sidebar>
    <!--
      SidebarInset IS a <main> (see Components/ui/sidebar/SidebarInset.vue);
      do NOT nest another <main> inside it. We pass `bg-surface-page` to
      override the shadcn default `bg-background` so the body matches the
      app's surface palette. `flex-col` + `min-h-svh` come from the component.
    -->
    <SidebarInset class="bg-surface-page">
      <header class="h-16 shrink-0 bg-surface-card border-b border-border-default flex items-center px-4 sm:px-6">
        <!--
          Single trigger: shadcn-vue's SidebarTrigger uses useSidebar() to
          toggle the mobile sheet (< md) or desktop icon-rail (≥ md)
          automatically — no hand-rolled mobile/desktop split. `me-auto`
          on the trigger expands the inline-end margin, pushing the logout
          to the inline-end (visually left under RTL); the trigger stays at
          the inline-start (visually right, adjacent to the sidebar).
        -->
        <SidebarTrigger class="-ms-2 me-auto" aria-label="القائمة" />
        <NotificationBell href="/admin/notifications" />
      </header>
      <div class="flex-1"><slot /></div>
    </SidebarInset>
  </SidebarProvider>
</template>
