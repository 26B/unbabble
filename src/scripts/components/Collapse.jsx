const Collapse = ({ children, title }) => (
  <details>
    <summary>{title}</summary>
    <div>{children}</div>
  </details>
)

export default Collapse
