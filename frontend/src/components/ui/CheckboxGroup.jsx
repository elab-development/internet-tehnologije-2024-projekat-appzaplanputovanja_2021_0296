import React from "react";

export default function CheckboxGroup({
  legend,
  name,
  items = [],
  values = [],
  onChange,
  error,
  titleCase = false,
}) {
  const handleToggle = (val) => {
    const set = new Set(values);
    if (set.has(val)) set.delete(val);
    else set.add(val);
    onChange(Array.from(set));
  };

  //ulepsavanje stringa
  const humanize = (str) =>
    String(str).replace(/_/g, " ").replace(/\s+/g, " ").trim();
  const pretty = (s) => {
    const txt = humanize(s);
    if (!titleCase) return txt;
    return txt.replace(/\b\w/g, (c) => c.toUpperCase());
  };

  return (
    <fieldset className="mb-3">
      {legend && <legend className="form-label mb-2">{legend}</legend>}
      <div className="row row-cols-1 row-cols-sm-2 row-cols-md-3 gy-2">
        {items.map((it) => {
          const val = typeof it === "string" ? it : it.value;
          const label = typeof it === "string" ? it : it.label;
          const checked = values.includes(val);
          return (
            <div className="col" key={val}>
              <div className="form-check">
                <input
                  className="form-check-input"
                  type="checkbox"
                  id={`${name}-${val}`}
                  checked={checked}
                  onChange={() => handleToggle(val)}
                />
                <label className="form-check-label" htmlFor={`${name}-${val}`}>
                  {pretty(label)}
                </label>
              </div>
            </div>
          );
        })}
      </div>
      {error ? <div className="invalid-feedback d-block">{error}</div> : null}
    </fieldset>
  );
}
