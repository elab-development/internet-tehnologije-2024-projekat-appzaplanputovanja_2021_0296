export default function FormInput({
  label,
  id,
  type = "text",
  error,
  ...props
}) {
  return (
    <div className="mb-3">
      {label && (
        <label htmlFor={id} className="form-label">
          {label}
        </label>
      )}
      <input id={id} type={type} className="form-control" {...props} />
      {error ? <div className="invalid-feedback d-block">{error}</div> : null}
    </div>
  );
}
