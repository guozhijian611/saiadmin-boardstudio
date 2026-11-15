export function playerUrl(code: string) {
  const base = new URL(import.meta.env.VITE_API_URL || '/', window.location.origin)
  base.pathname = base.pathname.replace(/\/$/, '') + '/app/boardstudio/player/'
  base.search = ''
  base.hash = ''
  base.searchParams.set('code', code)
  return base
}
