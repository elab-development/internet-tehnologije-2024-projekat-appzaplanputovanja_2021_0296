import React from "react";

export default function SelectInput({
  id,
  label,
  value,
  onChange,
  options = [],
  placeholder = "Select...",
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
      <select
        id={id}
        className={`form-select${error ? " is-invalid" : ""}`}
        value={value}
        onChange={onChange}
        {...props}
      >
        <option value="">{placeholder}</option>
        {options.map((opt) => {
          const val = typeof opt === "string" ? opt : opt.value;
          const txt = typeof opt === "string" ? opt : opt.label;
          return (
            <option key={val} value={val}>
              {txt}
            </option>
          );
        })}
      </select>
      {error ? <div className="invalid-feedback d-block">{error}</div> : null}
    </div>
  );
}
