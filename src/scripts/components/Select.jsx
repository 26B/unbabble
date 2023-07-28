const Select = ({ currentValue, changeValue, options }) => {
  const onChange = ({ target }) => { changeValue(target.value) }

  return (
    <select onChange={onChange}>
      {options.map(
        ({ name, value }) => <option value={value}>{name}</option>
      )}
    </select>
  )
}

export default Select
