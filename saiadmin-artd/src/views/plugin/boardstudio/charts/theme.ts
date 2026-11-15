import { onMounted, onBeforeUnmount, ref, type Ref } from 'vue'

// Observe host theme and the canvas ancestors without changing user appearance.
export function useChartTheme(target: Ref<HTMLElement | undefined>) {
  const dark = ref(document.documentElement.classList.contains('dark'))
  const revision = ref(0)
  let observer: MutationObserver | undefined
  const update = () => {
    let node: HTMLElement | null = target.value ?? null
    let background = ''
    while (node && !background) {
      const value = getComputedStyle(node).backgroundColor
      if (value !== 'transparent' && value !== 'rgba(0, 0, 0, 0)') background = value
      node = node.parentElement
    }
    const rgb = background.match(/[\d.]+/g)?.map(Number)
    dark.value =
      rgb && rgb.length >= 3
        ? rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722 < 128
        : document.documentElement.classList.contains('dark')
    revision.value++
  }
  onMounted(() => {
    observer = new MutationObserver(update)
    let node: HTMLElement | null = target.value ?? document.documentElement
    while (node) {
      observer.observe(node, { attributes: true, attributeFilter: ['class', 'style'] })
      node = node.parentElement
    }
    update()
  })
  onBeforeUnmount(() => observer?.disconnect())
  return { dark, revision }
}
