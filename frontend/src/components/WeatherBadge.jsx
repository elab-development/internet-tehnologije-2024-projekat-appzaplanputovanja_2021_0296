// src/components/WeatherBadge.jsx
import React from "react";

export default function WeatherBadge({ loading, error, summary }) {
  if (loading)
    return (
      <span className="badge bg-secondary-subtle text-secondary-emphasis">
        Weather…
      </span>
    );
  if (error)
    return (
      <span
        className="badge bg-danger-subtle text-danger-emphasis"
        title={error}
      >
        Weather N/A
      </span>
    );
  if (!summary) return null;

  const { avgMax, avgMin, maxWind, note, mode, current } = summary;
  return (
    <div className="d-inline-flex align-items-center gap-2 px-2 py-1 rounded-pill border bg-white">
      <span aria-hidden>🌤️</span>
      {mode === "current" && current?.temp != null ? (
        <>
          <span>
            <strong>{Math.round(current.temp)}°C</strong> now
          </span>
          {current.wind != null ? (
            <span>· 💨 {Math.round(current.wind)} km/h</span>
          ) : null}
        </>
      ) : (
        <>
          {avgMax != null && avgMin != null ? (
            <span>
              <strong>{avgMax}°C</strong> / {avgMin}°C
            </span>
          ) : (
            <span>—</span>
          )}
          {maxWind != null ? (
            <span>· 💨 {Math.round(maxWind)} km/h</span>
          ) : null}
        </>
      )}
      {note ? (
        <span className="text-muted small">
          · Trip dates are too far, showing current conditions
        </span>
      ) : null}
    </div>
  );
}
