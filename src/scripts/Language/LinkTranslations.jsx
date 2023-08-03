import { useState } from 'react'

import Button from "../components/Button"
import Collapse from '../components/Collapse'
import Modal from "../components/Modal"
import useLinkablePosts from '../hooks/useLinkablePosts'
import { withLangContext } from './contexts/LangContext'
import useLinkPost from '../hooks/useLinkPost'

const LinkOption = ({ postId, refetchLangs, posts, source }) => {
  const { mutate, isLoading, isSuccess, isError } = useLinkPost(postId, source)

  const onLink = () => mutate()
    .then(() => refetchLangs())

  const main_post = posts[0]

  return (<div style={{ display: 'flex', justifyContent: 'space-between', border: '1px solid #e0e0e0', padding: '8px'}}>
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(1, 1fr)', alignContent: 'center' }} >
      {posts.length === 1 &&
        <summary>{ '(' + main_post.lang + ') ' + main_post.title }</summary>
      }
      {posts.length > 1 &&
        <Collapse title={ '(' + main_post.lang + ') ' + main_post.title }>
          <div style={{ display: 'flex', flexWrap: 'wrap', marginLeft: '20px' }}>
            <h4 style={{ marginTop: '0px' }}>Other translations in the group:</h4>
            {posts.slice(1).map(
              ({ title, ID, lang }) => (<div style={{ width: '100%', justifyContent: 'space-between' }}>
                  <span>({lang}) </span>
                  <span>{title} </span>
              </div>)
            )}
          </div>
        </Collapse>
      }
    </div>
    <Button onClick={onLink} disabled={isLoading || isSuccess} style={{ height: 'min-content', margin: 'auto 0 auto 32px'}}>
      {!isSuccess && 'Link'}
      {isSuccess && 'Linked'}
    </Button>
  </div>)
}

const LinkTranslations = ({ postId, refetchLangs }) => {
  const [ isModalOpen, setIsModalOpen ] = useState(true) // TODO: set back to false
  const { data, refetch, isLoading, isError } = useLinkablePosts(postId, 1)
  const [ page, setPage ] = useState(1)
  const [ totalPages, setTotalPages ] = useState( data?.pages || 1 )

  const openModal = () => setIsModalOpen(true)
  const closeModal = () => setIsModalOpen(false)
  const previousPage = () => {
    if ( page <= 1 ) {
      return
    }
    setPage( page - 1 )
    refetch( page - 1 )
  }

  const nextPage = () => {
    if ( page >= totalPages ) {
      return
    }
    setPage( page + 1 )
    refetch( page + 1 )
  }

  if ( ! isLoading && totalPages !== ( data?.pages || 1 ) ) {
    setTotalPages( data?.pages || 1 )
  }

  return (<>
    <Modal isOpen={isModalOpen} close={closeModal}>
      <div style={{ display: 'grid', flexWrap: 'wrap', padding: '20px', gap: '8px' }}>
        <h1> Link to existing posts: </h1>
        <h4> You will unlink from the post's current translations if you link to another </h4>
        {isLoading && 'Loading...'}
        {isError && 'ERROR!!!'}
        {data?.options && data.options.map((option) => <LinkOption {...option} postId={postId} refetchLangs={refetchLangs}/>)}
      </div>
      <div style={{ display: 'flex', width: '100%', paddingLeft: '20px' }}>
      { page > 1 && <Button onClick={previousPage}>Previous Page</Button> }
      <b style={{ padding: '10px' }}>{page}</b>
      { page < totalPages && <Button onClick={nextPage}>Next Page</Button> }
      </div>
    </Modal>
    <Button onClick={openModal}>Link translations</Button>
  </>)
}

export default withLangContext(LinkTranslations)
