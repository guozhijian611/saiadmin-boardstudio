// Import once from the host's src/main.ts, before app.mount(), for menu translations.
import i18n from '@/locales'
import { registerBoardMessages } from './index'
registerBoardMessages(i18n.global as any)
