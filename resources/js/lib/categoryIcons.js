// Maps a ServiceCategory slug to a lucide-vue-next icon name.
// Manager can override per-category via service_categories.icon_key.
// This map is the fallback when icon_key is null.

import {
  Sparkles, Heart, Stethoscope, Scissors, Flower, Droplets,
  Sun, Smile, Activity, Pill, Brain, Eye, Baby, Bandage, Hand,
} from 'lucide-vue-next'

export const ICON_LIBRARY = {
  Sparkles, Heart, Stethoscope, Scissors, Flower, Droplets,
  Sun, Smile, Activity, Pill, Brain, Eye, Baby, Bandage, Hand,
}

// Heuristic fallback: match slug against keywords.
const SLUG_KEYWORDS = [
  { keys: ['massage', 'تدليك'], icon: 'Hand' },
  { keys: ['hijama', 'حجامة'], icon: 'Droplets' },
  { keys: ['skin', 'face', 'beauty', 'بشرة', 'تجميل', 'جمال'], icon: 'Sparkles' },
  { keys: ['hair', 'شعر'], icon: 'Scissors' },
  { keys: ['dental', 'tooth', 'أسنان', 'سنّ'], icon: 'Smile' },
  { keys: ['eye', 'عين'], icon: 'Eye' },
  { keys: ['child', 'kid', 'طفل', 'أطفال'], icon: 'Baby' },
  { keys: ['injury', 'wound', 'جرح', 'إصابة'], icon: 'Bandage' },
  { keys: ['pharmacy', 'drug', 'med', 'دواء', 'صيدلية'], icon: 'Pill' },
  { keys: ['mental', 'mind', 'نفس', 'دماغ'], icon: 'Brain' },
  { keys: ['sun', 'شمس'], icon: 'Sun' },
  { keys: ['fitness', 'sport', 'رياضة'], icon: 'Activity' },
]

export function iconForCategory(category) {
  if (category?.icon_key && ICON_LIBRARY[category.icon_key]) {
    return ICON_LIBRARY[category.icon_key]
  }
  const slug = (category?.slug ?? category?.name ?? '').toLowerCase()
  for (const { keys, icon } of SLUG_KEYWORDS) {
    if (keys.some((k) => slug.includes(k))) {
      return ICON_LIBRARY[icon] ?? Sparkles
    }
  }
  return Heart // generic fallback
}

// Tailwind-friendly color tokens by `color_variant` value.
const COLOR_BG = {
  brand: 'bg-brand/10 text-brand',
  success: 'bg-success/10 text-success',
  warning: 'bg-warning/10 text-warning',
  info: 'bg-info/10 text-info',
  danger: 'bg-danger/10 text-danger',
}

export function colorClassForCategory(category) {
  return COLOR_BG[category?.color_variant] ?? COLOR_BG.brand
}
