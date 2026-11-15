import type { BoardDataset } from '../widgets/types'

export type LayoutMode = 'free' | 'flex' | 'grid'
export interface NodePlacement {
  x: number
  y: number
  width: number
  height: number
  zIndex: number
  grow: number
  shrink: number
  basis: number | 'auto'
  columnSpan: number
  rowSpan: number
}
export interface ContainerLayout {
  mode: LayoutMode
  direction: 'row' | 'column'
  gap: number
  padding: number
  align: 'start' | 'center' | 'end' | 'stretch'
  justify: 'start' | 'center' | 'end' | 'space-between'
  wrap: boolean
  columns: number
  rowHeight: number
}
export interface NodeAppearance {
  background: string
  color: string
  borderColor: string
  borderWidth: number
  borderRadius: number
  opacity: number
}
export interface DocumentNode {
  id: string
  name: string
  kind: 'container' | 'widget'
  hidden: boolean
  locked: boolean
  placement: NodePlacement
  appearance: NodeAppearance
  // Only containers have layout/children; widgets reference a registered type.
  layout?: ContainerLayout
  children?: string[]
  widget?: { type: string; version: number; props: Record<string, unknown> }
  binding?: { instanceId: string; mapping: NonNullable<BoardDataset['mapping']> }
}
export interface DatasetBinding {
  id: string
  datasetId: number
  datasetVersion?: number
  params: Record<string, unknown>
  refresh: { mode: 'manual' | 'interval'; seconds: number }
}
export interface BoardDocument {
  schemaVersion: 2
  page: {
    width: number
    height: number
    sizing: 'fixed' | 'responsive'
    background: string
  }
  rootId: string
  nodes: Record<string, DocumentNode>
  bindings: Record<string, DatasetBinding>
  parameters: Record<string, { type: 'string' | 'number' | 'boolean'; default: unknown }>
}
