import { createPortal } from 'react-dom'

// TODO: remove me.

const Modal = ({ isOpen, close, children }) => {
  if (!isOpen) {
    return null
  }

  return createPortal(
    <div style={{ position: 'relative' }}>
      <div className="media-modal wp-core-ui" role="dialog">
        <button type="button" onClick={close} className="media-modal-close"><span className="media-modal-icon"><span className="screen-reader-text">Fechar janela</span></span></button>
        <div className="media-modal-content" role="document">
          <div className="media-frame mode-select wp-core-ui wpmf-treeview wpmf_hide_media_menu" style={{ overflow: 'scroll' }}>
            {children}
          </div>
        </div>
      </div>
      <div className="media-modal-backdrop"></div>
    </div>,
    document.body,
  )
}

export default Modal
