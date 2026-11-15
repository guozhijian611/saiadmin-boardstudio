import templates from './templates.json'
import type { BoardLayout, BoardComponent } from '../widgets/types'

export type PresetType = 'screen' | 'component'
export interface Preset {
  id: string
  type: PresetType
  name: string
  category: string
  description: string
  component_count: number
  content: { layout?: BoardLayout; components?: BoardComponent[] }
}

// Presets ship with the plugin. Consumers clone their layouts before editing.
export const presets = templates.map((item, index) => ({
  ...item,
  id: `preset-${index + 1}`
})) as Preset[]

export function filterPresets(type: PresetType, keyword = '', category = '') {
  const query = keyword.trim().toLocaleLowerCase()
  return presets.filter(
    (item) =>
      item.type === type &&
      (!category || item.category === category) &&
      (!query ||
        `${item.name} ${item.description} ${item.category}`.toLocaleLowerCase().includes(query))
  )
}

// Move/scale the whole preset together so borders and their contents stay aligned.
export function placePresetComponents(
  components: BoardComponent[],
  canvas: BoardLayout['canvas'],
  maxZ: number
): BoardComponent[] {
  if (!components.length) return []
  const left = Math.min(...components.map(({ rect }) => rect.x))
  const top = Math.min(...components.map(({ rect }) => rect.y))
  const width = Math.max(...components.map(({ rect }) => rect.x + rect.w)) - left
  const height = Math.max(...components.map(({ rect }) => rect.y + rect.h)) - top
  const scale = Math.min(1, canvas.width / width, canvas.height / height)
  const x = Math.max(0, Math.min(left + 24, canvas.width - width * scale))
  const y = Math.max(0, Math.min(top + 24, canvas.height - height * scale))
  const minZ = Math.min(...components.map(({ rect }) => rect.z || 1))
  return components.map((component) => ({
    ...component,
    rect: {
      x: Math.round(x + (component.rect.x - left) * scale),
      y: Math.round(y + (component.rect.y - top) * scale),
      w: Math.round(component.rect.w * scale),
      h: Math.round(component.rect.h * scale),
      z: maxZ + 1 + (component.rect.z || 1) - minZ
    }
  }))
}
