import { useEffect, useState } from 'react'

import Collapse from '../components/Collapse'
import CreateTranslations from './CreateTranslations'
import useEditPost from '../hooks/useEditPost'
import { getQueryVar } from '../services/searchQuery'
import getUBBSetting from '../services/settings'
import LangContext from './contexts/LangContext'
import ListTranslations from './ListTranslations'
import UnlinkTranslations from './UnlinkTranslations'
import LinkTranslations from './LinkTranslations'

const Language = () => {
  const [postId, setPostId] = useState(getQueryVar('post'))
  const { data, refetch, isLoading, isError } = useEditPost(postId)
  const [isSavingMetaBoxes, setIsSavingMetaboxes] = useState(wp?.data?.select( 'core/edit-post' )?.isSavingMetaBoxes() || false)

  wp.data.subscribe( () => {
    setIsSavingMetaboxes(
      (prev) => {
        const current = wp.data.select( 'core/edit-post' ).isSavingMetaBoxes()
        if (prev && ! current) {
          setPostId( wp.data.select("core/editor").getCurrentPostId())
        }
        return current
      }
    )
  } )

  if (isLoading) {
    return 'Loading...' // TODO: Add spinner
  }

  if (isError) {
    return 'Error fetching post language data.'
  }

  if (!data || !data.translations) {
    return 'Post has no language data.'
  }

  const { language, translations: translatedLangs } = data
  const languages = getUBBSetting('languages', {})

  const untranslatedLangs = languages
    .filter(lang => lang !== language && ! translatedLangs.map(({ language }) => language ).includes(lang))

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
      {untranslatedLangs.length > 0 && (<>
        <hr/>
        <CreateTranslations/>
      </>)}
      <hr/>
      <Collapse title="Linking">
        <LinkTranslations/>
        {translatedLangs.length > 0 && (<>
          <hr/>
          <UnlinkTranslations/>
        </>)}
      </Collapse>
    </LangContext.Provider>
  )
}

export default Language
