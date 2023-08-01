import { useState } from 'react'

import Button from "../components/Button"
import Modal from "../components/Modal"
import useLinkablePosts from '../hooks/useLinkablePosts'
import { withLangContext } from './contexts/LangContext'
import useLinkPost from '../hooks/useLinkPost'

const LinkOption = ({ postId, refetchLangs, posts, source }) => {
  const { mutate, isLoading, isSuccess, isError } = useLinkPost(postId, source)

  const onLink = () => mutate()
    .then(() => refetchLangs())

  return (<div style={{ display: 'flex', border: '1px solid #e0e0e0', padding: '8px'}}>
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', alignContent: 'center' }}>
      <b style={{ gridColumn: '1 / 4' }}>{source}</b>
      {posts.map(
        ({ title, ID, lang }) => (<>
          <span>{title}</span>
          <span>{ID}</span>
          <span>{lang}</span>
        </>)
      )}
    </div>
    <Button onClick={onLink} disabled={isLoading || isSuccess} style={{ height: 'min-content', margin: 'auto 0 auto 32px'}}>
      {!isSuccess && 'Link'}
      {isSuccess && 'Linked'}
    </Button>
  </div>)
}

const LinkTranslations = ({ postId, refetchLangs }) => {
  const [ isModalOpen, setIsModalOpen ] = useState(false)
  const { data, isLoading, isError } = useLinkablePosts(postId)

  const openModal = () => setIsModalOpen(true)
  const closeModal = () => setIsModalOpen(false)

  return (<>
    <Modal isOpen={isModalOpen} close={closeModal}>
      <div style={{ display: 'flex', flexWrap: 'wrap', padding: '52px', gap: '8px' }}>
        {isLoading && 'Loading...'}
        {isError && 'ERROR!!!'}
        {data?.options && data.options.map((option) => <LinkOption {...option} postId={postId} refetchLangs={refetchLangs}/>)}
      </div>
    </Modal>
    <Button onClick={openModal}>Link translations</Button>
  </>)
}

export default withLangContext(LinkTranslations)
