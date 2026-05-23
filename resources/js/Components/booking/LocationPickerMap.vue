<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { MapPin, Crosshair, X } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: Object,
    default: null,
    validator: (v) => v === null || (typeof v === 'object' && typeof v.lat === 'number' && typeof v.lng === 'number'),
  },
  defaultCenter: {
    type: Array,
    default: () => [31.5017, 34.4668],
  },
  defaultZoom: { type: Number, default: 13 },
})

const emit = defineEmits(['update:modelValue'])

const mapEl = ref(null)
const status = ref('')
const isLocating = ref(false)

let L = null
let map = null
let marker = null

const brandPinHtml = `
  <span style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50% 50% 50% 0;background:var(--color-brand,#0d9488);transform:rotate(-45deg);box-shadow:0 4px 12px rgba(0,0,0,.18);border:2px solid #fff;">
    <span style="width:10px;height:10px;border-radius:50%;background:#fff;transform:rotate(45deg);"></span>
  </span>
`

async function ensureLeafletLoaded() {
  if (L) return L
  const mod = await import('leaflet')
  L = mod.default ?? mod
  if (!document.querySelector('link[data-leaflet-css]')) {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY='
    link.crossOrigin = ''
    link.setAttribute('data-leaflet-css', '')
    document.head.appendChild(link)
  }
  return L
}

function buildIcon() {
  return L.divIcon({
    html: brandPinHtml,
    className: 'jannah-location-pin',
    iconSize: [32, 32],
    iconAnchor: [16, 30],
  })
}

function setMarker(latlng) {
  if (!marker) {
    marker = L.marker(latlng, { draggable: true, icon: buildIcon() }).addTo(map)
    marker.on('dragend', () => {
      const p = marker.getLatLng()
      emit('update:modelValue', { lat: round(p.lat), lng: round(p.lng) })
    })
  } else {
    marker.setLatLng(latlng)
  }
}

function round(n) {
  return Math.round(n * 1e7) / 1e7
}

function locateMe() {
  if (!('geolocation' in navigator)) {
    status.value = 'المتصفّح لا يدعم تحديد الموقع.'
    return
  }
  isLocating.value = true
  status.value = 'جارٍ تحديد موقعك...'

  const onSuccess = (pos) => {
    const lat = round(pos.coords.latitude)
    const lng = round(pos.coords.longitude)
    const latlng = [lat, lng]
    if (map) {
      map.setView(latlng, 16)
      setMarker(latlng)
    }
    emit('update:modelValue', { lat, lng })
    isLocating.value = false
    status.value = ''
  }

  const onFinalError = (err) => {
    isLocating.value = false
    if (err.code === 1) status.value = 'تم رفض إذن الموقع. اختر النقطة يدويًا على الخريطة.'
    else if (err.code === 2) status.value = 'تعذّر تحديد الموقع. اختر النقطة يدويًا على الخريطة.'
    else status.value = 'تعذّر تحديد موقعك تلقائيًا — اختر النقطة يدويًا على الخريطة.'
  }

  // Single attempt — network-based geolocation with a 20s window. High
  // accuracy is off because GPS often hangs past the timeout on desktop and
  // indoors; for a clinic home visit network accuracy (~100m) is plenty,
  // and the user can drag the pin to fine-tune.
  navigator.geolocation.getCurrentPosition(
    onSuccess,
    onFinalError,
    { enableHighAccuracy: false, timeout: 20000, maximumAge: 300000 },
  )
}

function clearLocation() {
  if (marker && map) {
    map.removeLayer(marker)
    marker = null
  }
  emit('update:modelValue', null)
  status.value = ''
}

onMounted(async () => {
  await ensureLeafletLoaded()
  if (!mapEl.value) return

  const initial = props.modelValue
    ? [props.modelValue.lat, props.modelValue.lng]
    : props.defaultCenter
  const zoom = props.modelValue ? 16 : props.defaultZoom

  map = L.map(mapEl.value, { zoomControl: true, attributionControl: true }).setView(initial, zoom)

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map)

  if (props.modelValue) {
    setMarker(initial)
  }

  map.on('click', (e) => {
    const lat = round(e.latlng.lat)
    const lng = round(e.latlng.lng)
    setMarker([lat, lng])
    emit('update:modelValue', { lat, lng })
  })

  setTimeout(() => map && map.invalidateSize(), 100)
})

onBeforeUnmount(() => {
  if (map) {
    map.remove()
    map = null
    marker = null
  }
})

watch(
  () => props.modelValue,
  (val) => {
    if (!map) return
    if (val && (!marker || marker.getLatLng().lat !== val.lat || marker.getLatLng().lng !== val.lng)) {
      setMarker([val.lat, val.lng])
    } else if (!val && marker) {
      map.removeLayer(marker)
      marker = null
    }
  },
)
</script>

<template>
  <div class="space-y-2">
    <div class="flex flex-wrap items-center gap-2">
      <button
        type="button"
        :disabled="isLocating"
        class="inline-flex items-center gap-1.5 rounded-md border border-brand/40 bg-brand/5 text-brand px-3 py-1.5 text-xs font-bold hover:bg-brand/10 disabled:opacity-50"
        @click="locateMe"
      >
        <Crosshair class="w-3.5 h-3.5" :class="isLocating && 'animate-spin'" aria-hidden="true" />
        {{ isLocating ? 'جارٍ التحديد...' : 'حدّد موقعي تلقائياً' }}
      </button>
      <button
        v-if="modelValue"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-md border border-border-default text-text-secondary px-3 py-1.5 text-xs font-bold hover:bg-surface-muted"
        @click="clearLocation"
      >
        <X class="w-3.5 h-3.5" aria-hidden="true" />
        مسح
      </button>
      <span v-if="modelValue" class="inline-flex items-center gap-1 text-[11px] text-text-tertiary">
        <MapPin class="w-3 h-3" aria-hidden="true" />
        {{ modelValue.lat.toFixed(5) }}، {{ modelValue.lng.toFixed(5) }}
      </span>
    </div>

    <p class="text-xs text-text-tertiary">
      اضغط على الخريطة لتحديد موقع الزيارة، أو اسحب الدبّوس لتعديله.
    </p>

    <div
      ref="mapEl"
      data-testid="location-picker-map"
      class="h-64 w-full rounded-xl border border-border-default overflow-hidden"
      aria-label="خريطة لتحديد الموقع"
      role="application"
    />

    <p v-if="status" class="text-xs text-warning">{{ status }}</p>
  </div>
</template>

<style scoped>
:deep(.leaflet-container) {
  font-family: inherit;
}
:deep(.jannah-location-pin) {
  background: transparent;
  border: none;
}
</style>
