import CreateTranslations from './CreateTranslations'
import useEditPost from '../hooks/useEditPost'
import { getQueryVar } from '../services/searchQuery'
import getUBBSetting from '../services/settings'
import LangContext from './contexts/LangContext'
import ListTranslations from './ListTranslations'
import UnlinkTranslations from './UnlinkTranslations'
import LinkTranslations from './LinkTranslations'

const Language = () => {
  const { data, refetch, isLoading, isError } = useEditPost(getQueryVar('post'))

  if (isLoading) {
    return 'Loading...' // TODO: Add spinner
  }

  if (isError) {
    return 'Error fetching post language data.'
  }

  if (!data) {
    return 'Post has no language data.'
  }

  const { language, translations } = data
  const languages = getUBBSetting('languages', {})
  const translatedLangs = Object.entries(translations)
    .map(
      ([name, data]) => ({ name, ...data})
    )
  const untranslatedLangs = languages
    .filter(lang => lang !== language && ! Object.keys(translations).includes(lang))

  return (
    <LangContext.Provider value={{
      currentLang: language,
      postId: data.postId,
      languages,
      translatedLangs,
      untranslatedLangs,
      refetchLangs: refetch,
    }}>
      <ListTranslations/>
      <hr/>
      <CreateTranslations/>
      <hr/>
      <UnlinkTranslations/>
      <hr/>
      <LinkTranslations/>
    </LangContext.Provider>
  )
}

export default Language
