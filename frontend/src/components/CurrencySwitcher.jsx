import React from "react";
import { useCurrency } from "../context/CurrencyContext";

export default function CurrencySwitcher({ size = "sm" }) {
  const { currency, setCurrency, supported, loading, error, reload } =
    useCurrency();

  return (
    <div className="d-inline-flex align-items-center gap-2">
      <label className="text-muted small m-0">Currency</label>
      <select
        className={`form-select form-select-${size}`}
        style={{ width: 110 }}
        value={currency}
        onChange={(e) => setCurrency(e.target.value)}
        disabled={loading}
        aria-label="Currency selector"
      >
        {supported.map((c) => (
          <option key={c} value={c}>
            {c}
          </option>
        ))}
      </select>
      {error ? (
        <button
          className="btn btn-link p-0 small text-danger"
          onClick={reload}
          title={error}
        >
          offline rates — retry
        </button>
      ) : null}
    </div>
  );
}
