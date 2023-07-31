const Button = ({ onClick, children, ...props }) => <button type="button" className="button" onClick={onClick} {...props}>{children}</button>

export default Button
