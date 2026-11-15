export const getCssVar = (name: string) =>
  getComputedStyle(document.documentElement).getPropertyValue(name).trim()
export const hexToRgba = (hex: string, alpha = 1) => {
  const h = hex.replace('#', '')
  const value =
    h.length === 3
      ? h
          .split('')
          .map((c) => c + c)
          .join('')
      : h
  return {
    rgba: /^([a-f0-9]{6})$/i.test(value)
      ? `rgba(${parseInt(value.slice(0, 2), 16)}, ${parseInt(value.slice(2, 4), 16)}, ${parseInt(value.slice(4, 6), 16)}, ${alpha})`
      : hex
  }
}
