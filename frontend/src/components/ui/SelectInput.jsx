import React from "react";

let __autoId = 0;

export default function SelectInput({
  id,
  label,
  value = "",
  onChange,
  options = [],
  placeholder = "Select...",
  error,
  className = "",
  ...props
}) {
  const selectId = React.useMemo(() => id || `si_${++__autoId}`, [id]);
  const errorId = `${selectId}__error`;

  const cls = `form-select${error ? " is-invalid" : ""} ${className}`.trim();

  return (
    <div className="mb-3">
      {label && (
        <label htmlFor={selectId} className="form-label">
          {label}
        </label>
      )}

      <select
        id={selectId}
        className={cls}
        value={value}
        onChange={onChange}
        aria-invalid={!!error}
        aria-describedby={error ? errorId : undefined}
        {...props}
      >
        {/* Placeholder ima smisla samo kad value === "" */}
        {value === "" && (
          <option value="" disabled>
            {placeholder}
          </option>
        )}
        {options.map((opt, idx) => {
          const val = typeof opt === "string" ? opt : opt.value;
          const txt =
            typeof opt === "string" ? opt : opt.label ?? humanize(opt.value);
          return (
            <option key={`${val}-${idx}`} value={val}>
              {txt}
            </option>
          );
        })}
      </select>

      {error ? (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      ) : null}
    </div>
  );
}

function humanize(s) {
  return String(s)
    .replace(/_/g, " ")
    .replace(/\b[a-z]/g, (c) => c.toUpperCase());
}
