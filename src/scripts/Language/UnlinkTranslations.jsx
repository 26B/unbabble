import { useState } from 'react'
import { Button, Modal } from '@wordpress/components';
import { withLangContext } from "./contexts/LangContext"
import useUnlinkPost from '../hooks/useUnlinkPost'

const UnlinkTranslations = ({ postId, refetchLangs }) => {
  const { mutate, isLoading } = useUnlinkPost(postId)    // TODO: Loading and error
  const [ isOpen, setOpen ]   = useState( false );

  const openModal  = () => setOpen( true );
  const closeModal = () => setOpen( false );

  const onClick = () => mutate()
    .then(() => refetchLangs())

  return (<>
    <Button style={{ boxSizing: 'border-box' }} variant='secondary' isDestructive onClick={openModal} disabled={isLoading}>Unlink from translations</Button>
    { isOpen &&
      <Modal title='Unlink from translations' onRequestClose={closeModal} >
        <div>
          Unlinking will detach this post from all of its translations.
          <br/>
          The rest of the translations will continue linked to each other.
        </div>
        <div style={{ display: 'flex', justifyContent: 'end', gap: '10px', paddingTop: '20px' }} >
          <Button variant="secondary" onClick={ closeModal }>
            Cancel
          </Button>
          <Button variant="primary" isDestructive onClick={onClick}>
              Unlink
          </Button>
        </div>
      </Modal>
    }
  </>)

}

export default withLangContext(UnlinkTranslations)
