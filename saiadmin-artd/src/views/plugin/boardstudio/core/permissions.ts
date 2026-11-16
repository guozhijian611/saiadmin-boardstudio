import { useUserStore } from '@/store/modules/user'

/** Read the same reactive host button list used by v-permission. */
export function useBoardPermissions() {
  const user = useUserStore()
  const can = (permission: string) => {
    const buttons: string[] = user.getUserInfo.buttons || []
    return buttons.includes('*') || buttons.includes(permission)
  }
  return { can }
}
