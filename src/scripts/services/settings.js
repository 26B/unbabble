import { has, get } from 'lodash'

const getUBBSetting = (name, defaultSetting = '') => {
  if (!has(window, `UBB.${name}`)) {
    return defaultSetting
  }
  return get(window.UBB, name)
}

export default getUBBSetting
