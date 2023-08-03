import { useState } from 'react'

import { Button, SelectControl } from '@wordpress/components';

import Collapse from '../components/Collapse'
import useCopyPost from '../hooks/useCopyPost'
import { withLangContext } from './contexts/LangContext'
import getUBBSetting from '../services/settings'

const CreateTranslations = ({ untranslatedLangs, postId, refetchLangs }) => {
  const [current, setCurrent] = useState(untranslatedLangs[0])
  const { mutate, data, isLoading, isError } = useCopyPost(postId, current)

  const changeValue = (newValue) => setCurrent(newValue)

  const onSubmitCopy = () => mutate()
    .then(() => refetchLangs())

  const createUrlQuery = new URLSearchParams({
    'ubb_source': postId,
    lang: current,
  })
  const createUrl = `${getUBBSetting('admin_url', '')}/post-new.php?${createUrlQuery.toString()}`

  return (
    <Collapse title="Create Translations">
      <SelectControl onChange={changeValue} value={current} options={untranslatedLangs.map(lang => ({ label: lang, value: lang }))}/>
      <Button variant='secondary' isSmall href={createUrl}>Create</Button>
      {/* TODO: Only show if yoast duplicate post is active. */}
      <Button variant='secondary' isSmall disabled={isLoading} onClick={onSubmitCopy}>Copy</Button>
      {isError && 'ERROR!!!!'}
      {data && JSON.stringify(data)}
    </Collapse>
  )
}

export default withLangContext(CreateTranslations)
