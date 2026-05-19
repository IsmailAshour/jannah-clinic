<script setup>
import { defineComponent, h } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  Briefcase,
  CalendarDays,
  CalendarPlus,
  ChevronRight,
  LayoutDashboard,
  MapPin,
  Package,
  Settings,
  Stethoscope,
  Tags,
  Users,
} from 'lucide-vue-next'
import {
  Sidebar,
  SidebarContent,
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
      { label: 'الأطباء', href: '/admin/doctors', icon: Users },
      { label: 'المواعيد', href: '/admin/appointments', icon: CalendarDays },
      { label: 'حجز موعد  لعميل', href: '/admin/booking', icon: CalendarPlus },
    ],
  },
  { type: 'leaf', label: 'مناطق التغطية', href: '/admin/coverage', icon: MapPin },
  { type: 'leaf', label: 'الإعدادات', href: '/admin/settings', icon: Settings },
]
const page = usePage()
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
        <div class="text-lg font-bold px-2 py-1 whitespace-nowrap group-data-[collapsible=icon]:hidden">عيادة جنّة</div>
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu>
              <template v-for="n in nav" :key="n.label">
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
      <header class="h-16 shrink-0 bg-surface-card border-b border-border-default flex items-center px-6">
        <!--
          Single trigger: shadcn-vue's SidebarTrigger uses useSidebar() to
          toggle the mobile sheet (< md) or desktop icon-rail (≥ md)
          automatically — no hand-rolled mobile/desktop split. `me-auto`
          on the trigger expands the inline-end margin, pushing the logout
          to the inline-end (visually left under RTL); the trigger stays at
          the inline-start (visually right, adjacent to the sidebar).
        -->
        <SidebarTrigger class="-ms-2 me-auto" aria-label="القائمة" />
        <Link href="/logout" method="post" as="button" class="text-sm text-text-secondary hover:text-text-primary">تسجيل الخروج</Link>
      </header>
      <div class="flex-1"><slot /></div>
    </SidebarInset>
  </SidebarProvider>
</template>
