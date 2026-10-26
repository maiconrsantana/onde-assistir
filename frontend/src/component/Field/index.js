function Field(props) {
  const {
    text = '',
    type = 'text',
    label = text,
    placeholder = '',
    value,
    defaultValue,
    name,
    onChange,
    options = [],
    required = props.isrequired ?? false,
  } = props;

  if (type === 'select') {
    return (
      <label>
        {text}
        <select
          aria-label={label}
          name={name}
          value={value}
          defaultValue={value === undefined ? defaultValue : undefined}
          onChange={onChange}
          required={required}
        >
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </label>
    );
  }

  return (
    <label>
      {text}
      <input
        type={type}
        aria-label={label}
        name={name}
        placeholder={placeholder}
        value={value}
        defaultValue={value === undefined ? defaultValue : undefined}
        onChange={onChange}
        required={required}
      />
    </label>
  );
}

export default Field;
