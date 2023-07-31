import { useState } from 'react'

import Collapse from '../components/Collapse'
import Select from '../components/Select'
import Button from '../components/Button'
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
      <Select changeValue={changeValue} currentValue={current} options={untranslatedLangs.map(lang => ({ label: lang, value: lang }))}/>
      <a className='button' href={createUrl}>Create</a>
      <Button disabled={isLoading} onClick={onSubmitCopy}>Copy</Button>
      {isError && 'ERROR!!!!'}
      {data && JSON.stringify(data)}
    </Collapse>
  )
}

export default withLangContext(CreateTranslations)
