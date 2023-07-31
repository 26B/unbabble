const Select = ({ currentValue, changeValue, options }) => {
  const onChange = ({ target }) => { changeValue(target.value) }

  return (
    <select onChange={onChange}>
      {options.map(
        ({ label, value }) => <option value={value}>{label}</option>
      )}
    </select>
  )
}

export default Select
