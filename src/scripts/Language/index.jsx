import { useState } from 'react'

import Modal from '../components/Modal'
import CreateTranslations from './CreateTranslations'
import Select from '../components/Select'

const Language = () => {
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [current, setCurrent] = useState(1)

  const closeModal = () => setIsModalOpen(false)
  const openModal = () => setIsModalOpen(true)

  const options = [
    { name: '1', value: 1 },
    { name: '2', value: 2 },
    { name: '3', value: 3 },
    { name: '4', value: 4 },
  ]

  const changeValue = (newValue) => setCurrent(newValue)

  return (
    <>
      <hr/>
      <CreateTranslations />
      <Select changeValue={changeValue} currentValue={current} options={options}/>
      <Modal isOpen={isModalOpen} close={closeModal}/>
      <button onClick={openModal}>Open Modal</button>
    </>
  )
}

export default Language
