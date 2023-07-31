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

  return (<table>
    <tbody>
      <tr><b>{source}</b></tr>
      {posts.map(
        ({ title, ID, lang }) => (
          <tr>
            <td>{title}</td>
            <td>{ID}</td>
            <td>{lang}</td>
          </tr>
        )
      )}
      {!isSuccess && <tr><Button onClick={onLink} disabled={isLoading}>Link</Button></tr>}
      {isSuccess && <tr>Success!!!</tr>}
    </tbody>
  </table>)
}

const LinkTranslations = ({ postId, refetchLangs }) => {
  const [ isModalOpen, setIsModalOpen ] = useState(false)
  const { data, isLoading, isError } = useLinkablePosts(postId)

  const openModal = () => setIsModalOpen(true)
  const closeModal = () => setIsModalOpen(false)

  return (<>
    <Modal isOpen={isModalOpen} close={closeModal}>
      {isLoading && 'Loading...'}
      {isError && 'ERROR!!!'}
      {data?.options && data.options.map((option) => <LinkOption {...option} postId={postId} refetchLangs={refetchLangs}/>)}
    </Modal>
    <Button onClick={openModal}>Link translations</Button>
  </>)
}

export default withLangContext(LinkTranslations)
