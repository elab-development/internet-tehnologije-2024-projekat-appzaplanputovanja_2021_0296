export default function FormInput({
  label,
  id,
  type = "text",
  error,
  disabled,
  value,
  defaultValue,
  onChange,
  ...rest
}) {
  // Ako je polje zaključano → koristi defaultValue + disabled
  const inputProps = disabled
    ? { defaultValue: defaultValue ?? value, disabled: true }
    : { value, onChange, ...rest };
  return (
    <div className="mb-3">
      {label && (
        <label htmlFor={id} className="form-label">
          {label}
        </label>
      )}
      <input id={id} type={type} className="form-control" {...inputProps} />
      {error ? <div className="invalid-feedback d-block">{error}</div> : null}
    </div>
  );
}
