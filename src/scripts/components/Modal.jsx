import { createPortal } from 'react-dom'

const Modal = ({ isOpen, close }) => {
  if (!isOpen) {
    return null
  }

  return createPortal(
    <div style={{ position: 'relative' }}>
      <div className="media-modal wp-core-ui" role="dialog">
        <button type="button" onClick={close} class="media-modal-close"><span class="media-modal-icon"><span class="screen-reader-text">Fechar janela</span></span></button>
        <div className="media-modal-content" role="document">
          <div className="media-frame mode-select wp-core-ui wpmf-treeview wpmf_hide_media_menu">
            yoooo
          </div>
        </div>
      </div>
      <div className="media-modal-backdrop"></div>
    </div>,
    document.body,
  )
}

export default Modal
