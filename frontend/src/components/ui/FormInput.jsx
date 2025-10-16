// src/components/ui/FormInput.jsx
import React from "react";

let __autoId = 0;

export default function FormInput({
  label,
  id,
  type = "text",
  error,
  disabled = false,
  value,
  defaultValue,
  onChange,
  className = "",
  ...rest
}) {
  // stabilan id (ako nije prosleđen)
  const inputId = React.useMemo(() => id || `fi_${++__autoId}`, [id]);
  const errorId = `${inputId}__error`;

  // Pravilo:
  // - Ako koristimo controlled input -> koristimo `value` uvek (i kad je disabled).
  // - Ako autor *namerno* želi uncontrolled, neka ne šalje `value` već `defaultValue`.
  const controlled = value !== undefined;

  const commonProps = {
    id: inputId,
    type,
    disabled,
    "aria-invalid": !!error,
    "aria-describedby": error ? errorId : undefined,
    className: `form-control${error ? " is-invalid" : ""} ${className}`.trim(),
    ...rest,
  };

  return (
    <div className="mb-3">
      {label && (
        <label htmlFor={inputId} className="form-label">
          {label}
        </label>
      )}

      {controlled ? (
        <input {...commonProps} value={value} onChange={onChange} />
      ) : (
        <input
          {...commonProps}
          defaultValue={defaultValue}
          onChange={onChange}
        />
      )}

      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : null}
    </div>
  );
}
