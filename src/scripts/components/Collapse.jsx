const Collapse = ({ children, title }) => (
  <details>
    <summary>{title}</summary>
    <div className="components-panel__row" style={{ marginTop: '1em' }}>{children}</div>
  </details>
)

export default Collapse
