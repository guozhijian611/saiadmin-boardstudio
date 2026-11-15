import { clone, descendants, parentOf, validateDocument } from './model'
import { createHistory } from '../core/history'
import type {
  BoardDocument,
  DocumentNode,
  LayoutMode,
  ContainerLayout,
  DatasetBinding
} from './types'

export type DocumentCommand =
  | { type: 'batch'; commands: DocumentCommand[] }
  | { type: 'replace'; document: BoardDocument }
  | { type: 'insert'; parentId: string; node: DocumentNode; index?: number }
  | { type: 'insertTree'; parentId: string; rootId: string; nodes: Record<string, DocumentNode> }
  | { type: 'remove'; id: string }
  | { type: 'move'; id: string; parentId: string; index?: number }
  | {
      type: 'patch'
      id: string
      patch: Partial<
        Pick<
          DocumentNode,
          'name' | 'hidden' | 'locked' | 'placement' | 'appearance' | 'widget' | 'binding'
        >
      >
    }
  | { type: 'layout'; id: string; mode: LayoutMode }
  | { type: 'container'; id: string; patch: Partial<ContainerLayout> }
  | { type: 'duplicate'; id: string; ids: Record<string, string> }
  | {
      type: 'bind'
      id: string
      binding: DatasetBinding
      mapping: NonNullable<DocumentNode['binding']>['mapping']
    }
  | { type: 'unbind'; id: string }
  | { type: 'parameters'; parameters: BoardDocument['parameters'] }
  | { type: 'page'; patch: Partial<BoardDocument['page']> }

export function applyCommand(document: BoardDocument, command: DocumentCommand): BoardDocument {
  if (command.type === 'batch') {
    if (
      command.commands.length > 200 ||
      command.commands.some((c) => c.type === 'batch' || c.type === 'replace')
    )
      throw new Error('批量操作不合法')
    return command.commands.reduce((next, item) => applyCommand(next, item), clone(document))
  }
  if (command.type === 'replace') {
    if (Object.values(document.nodes).some((n) => n.locked))
      throw new Error('请先解锁节点再替换文档')
    validateDocument(command.document)
    return clone(command.document)
  }
  const next = clone(document)
  const editable = (id: string) => {
    const node = next.nodes[id]
    if (!node) throw new Error('节点不存在')
    let current: DocumentNode | undefined = node
    while (current) {
      if (current.locked) throw new Error('节点或其父容器已锁定')
      current = parentOf(next, current.id)
    }
    return node
  }
  const container = (id: string) => {
    const node = editable(id)
    if (node.kind !== 'container') throw new Error('只能放入容器')
    return node
  }
  const detach = (id: string) => {
    if (id === next.rootId) throw new Error('不能删除或移动根容器')
    const parent = parentOf(next, id)!
    editable(parent.id)
    parent.children!.splice(parent.children!.indexOf(id), 1)
  }
  switch (command.type) {
    case 'insertTree': {
      const parent = container(command.parentId)
      if (!command.nodes[command.rootId]) throw new Error('缺少子树根节点')
      const reachable = new Set<string>()
      const visit = (id: string) => {
        if (reachable.has(id)) throw new Error('子树包含重复节点或循环')
        const node = command.nodes[id]
        if (!node || node.id !== id || next.nodes[id]) throw new Error('子树节点缺失或 ID 冲突')
        reachable.add(id)
        for (const child of node.children || []) visit(child)
      }
      visit(command.rootId)
      if (reachable.size !== Object.keys(command.nodes).length) throw new Error('子树包含游离节点')
      for (const id of reachable) next.nodes[id] = clone(command.nodes[id])
      parent.children!.push(command.rootId)
      break
    }
    case 'insert': {
      const parent = container(command.parentId)
      if (next.nodes[command.node.id]) throw new Error('节点 ID 已存在')
      next.nodes[command.node.id] = clone(command.node)
      parent.children!.splice(command.index ?? parent.children!.length, 0, command.node.id)
      break
    }
    case 'remove': {
      editable(command.id)
      detach(command.id)
      for (const id of descendants(next, command.id)) delete next.nodes[id]
      break
    }
    case 'move': {
      editable(command.id)
      const parent = container(command.parentId)
      if (descendants(next, command.id).includes(parent.id))
        throw new Error('不能把节点放入自身或子容器')
      const oldParent = parentOf(next, command.id)!
      const oldIndex = oldParent.children!.indexOf(command.id)
      const insertion =
        command.index === undefined
          ? undefined
          : command.index - (oldParent.id === parent.id && oldIndex < command.index ? 1 : 0)
      detach(command.id)
      parent.children!.splice(insertion ?? parent.children!.length, 0, command.id)
      break
    }
    case 'patch': {
      // Unlocking the node itself is permitted, but a locked ancestor still blocks it.
      const node = next.nodes[command.id]
      if (
        node?.locked &&
        command.patch.locked === false &&
        Object.keys(command.patch).length === 1
      ) {
        const parent = parentOf(next, command.id)
        if (parent) editable(parent.id)
      } else editable(command.id)
      Object.assign(node, clone(command.patch))
      break
    }
    case 'container':
      Object.assign(container(command.id).layout!, command.patch)
      break
    case 'layout': {
      const node = container(command.id)
      // Preserve all placement fields. Changing modes is explicit and undoable;
      // free coordinates return when switching back to free layout.
      node.layout!.mode = command.mode
      break
    }
    case 'duplicate': {
      editable(command.id)
      if (command.id === next.rootId) throw new Error('不能复制根容器')
      const parent = container(parentOf(next, command.id)!.id)
      const sourceIds = descendants(next, command.id)
      const freshIds = sourceIds.map((id) => command.ids[id])
      if (
        freshIds.some((id) => !id || next.nodes[id]) ||
        new Set(freshIds).size !== freshIds.length
      )
        throw new Error('复制节点 ID 不合法')
      for (const id of sourceIds) {
        const copy = clone(next.nodes[id])
        copy.id = command.ids[id]
        if (copy.children) copy.children = copy.children.map((child) => command.ids[child])
        next.nodes[copy.id] = copy
      }
      const root = next.nodes[command.ids[command.id]]
      root.name += ' 副本'
      root.placement.x += 24
      root.placement.y += 24
      parent.children!.splice(parent.children!.indexOf(command.id) + 1, 0, root.id)
      break
    }
    case 'bind': {
      const node = editable(command.id)
      if (node.kind !== 'widget') throw new Error('只有组件可以绑定数据')
      next.bindings[command.binding.id] = clone(command.binding)
      node.binding = { instanceId: command.binding.id, mapping: clone(command.mapping) }
      break
    }
    case 'unbind':
      delete editable(command.id).binding
      break
    case 'parameters':
      editable(next.rootId)
      next.parameters = clone(command.parameters)
      break
    case 'page':
      editable(next.rootId)
      Object.assign(next.page, command.patch)
      break
  }
  validateDocument(next)
  return next
}

export function createDocumentSession(initial: BoardDocument) {
  validateDocument(initial)
  let document = clone(initial)
  const state = { past: [] as string[], current: '', future: [] as string[] }
  const history = createHistory(state, 100)
  history.reset(JSON.stringify(document))
  return {
    get document() {
      return clone(document)
    },
    get canUndo() {
      return state.past.length > 0
    },
    get canRedo() {
      return state.future.length > 0
    },
    execute(command: DocumentCommand) {
      const next = applyCommand(document, command)
      history.record(JSON.stringify(next))
      document = next
      return clone(document)
    },
    undo() {
      const snapshot = history.undo(JSON.stringify(document))
      if (snapshot) document = JSON.parse(snapshot)
      return clone(document)
    },
    redo() {
      const snapshot = history.redo(JSON.stringify(document))
      if (snapshot) document = JSON.parse(snapshot)
      return clone(document)
    }
  }
}
