import { useState } from 'react'

import Button from "../components/Button"
import Modal from "../components/Modal"

const LinkTranslations = () => {
  const [ isModalOpen, setIsModalOpen ] = useState(false)

  const openModal = () => setIsModalOpen(true)
  const closeModal = () => setIsModalOpen(false)

  return (<>
    <Modal isOpen={isModalOpen} close={closeModal}>
      stuff
    </Modal>
    <Button onClick={openModal}>Link translations</Button>
  </>)
}

export default LinkTranslations
