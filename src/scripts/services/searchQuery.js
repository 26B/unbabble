export const getQueryVar = (varName, defaultValue) => {
  const searchParams = new URLSearchParams(window.location.search)

  if (!searchParams.has(varName)) {
    return defaultValue
  }

  return searchParams.get(varName)
}
