// src/hooks/useWeather.js
import React from "react";

/* ---------- simple TTL cache preko localStorage ---------- */
function cacheGet(k) {
  try {
    const r = localStorage.getItem(k);
    if (!r) return null;
    const { v, exp } = JSON.parse(r);
    if (exp && Date.now() > exp) {
      localStorage.removeItem(k);
      return null;
    }
    return v;
  } catch {
    return null;
  }
}
function cacheSet(k, v, ttlMs) {
  localStorage.setItem(
    k,
    JSON.stringify({ v, exp: ttlMs ? Date.now() + ttlMs : null })
  );
}

/* ---------- fallback koordinate za česta mesta ---------- */
const PRESET = {
  Belgrade: { lat: 44.787, lon: 20.457 },
  Beograd: { lat: 44.787, lon: 20.457 },
  Nis: { lat: 43.32, lon: 21.895 },
  Niš: { lat: 43.32, lon: 21.895 },
  Zagreb: { lat: 45.815, lon: 15.982 },
  Ljubljana: { lat: 46.056, lon: 14.505 },
  Sarajevo: { lat: 43.856, lon: 18.413 },
  Amsterdam: { lat: 52.367, lon: 4.904 },
  Lisbon: { lat: 38.722, lon: -9.139 },
  Valencia: { lat: 39.47, lon: -0.376 },
  Prague: { lat: 50.075, lon: 14.437 },
  Budapest: { lat: 47.498, lon: 19.04 },
};

/* ---------- YYYY-MM-DD parser (radi i sa "23.11.2025.") ---------- */
function ymd(input) {
  if (!input) return null;

  if (input instanceof Date && !isNaN(input)) {
    const d = input;
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  const s = String(input).trim();

  // ISO "2025-11-23", "2025-11-23 00:00", "2025-11-23T..."
  const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;

  // Domaći "23.11.2025." ili "23.11.2025"
  const local = s.match(/^(\d{2})\.(\d{2})\.(\d{4})/);
  if (local) return `${local[3]}-${local[2]}-${local[1]}`;

  const d = new Date(s);
  if (!isNaN(d)) {
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }
  return null;
}

/* ---------- geocoding (Open-Meteo) + keš ---------- */
async function geocode(name) {
  const key = `geo:${name}`;
  const hit = cacheGet(key);
  if (hit) return hit;

  if (PRESET[name]) {
    cacheSet(key, PRESET[name], 30 * 24 * 60 * 60 * 1000);
    return PRESET[name];
  }

  const url = `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(
    name
  )}&count=1&language=en&format=json`;
  const r = await fetch(url);
  if (!r.ok) throw new Error("Geocoding failed");
  const j = await r.json();
  if (!j?.results?.length) throw new Error("Destination not found");
  const { latitude: lat, longitude: lon } = j.results[0];
  const coords = { lat, lon };
  cacheSet(key, coords, 30 * 24 * 60 * 60 * 1000);
  return coords;
}

/* ---------- glavni hook ---------- */
export default function useWeather({
  destination,
  startDate,
  endDate,
  ttlHours = 12,
}) {
  const [state, setState] = React.useState({
    loading: true,
    error: null,
    data: null,
  });

  React.useEffect(() => {
    let off = false;

    (async () => {
      try {
        setState({ loading: true, error: null, data: null });

        const start = ymd(startDate);
        const end = ymd(endDate);
        if (!destination || !start || !end) throw new Error("Missing inputs");

        const { lat, lon } = await geocode(destination);
        const ck = `wx:${destination}:${start}->${end}:${lat.toFixed(
          3
        )},${lon.toFixed(3)}`;
        const cached = cacheGet(ck);
        if (cached) {
          setState({ loading: false, error: null, data: cached });
          return;
        }

        // Base deo URL-a – važna je tačna metrika: wind_speed_10m_max
        const urlBase =
          `https://api.open-meteo.com/v1/forecast` +
          `?latitude=${lat}&longitude=${lon}` +
          `&timezone=auto` +
          `&daily=temperature_2m_max,temperature_2m_min,wind_speed_10m_max,precipitation_sum`;

        // 1) pokušaj za traženi raspon datuma
        let url = `${urlBase}&start_date=${start}&end_date=${end}`;
        let r = await fetch(url);

        // 2) ako je van opsega (400), fallback: current + 7 day outlook
        let note = null;
        let mode = "range"; // "range" | "current"
        if (!r.ok) {
          try {
            const errJson = await r.json();
            const reason = (
              errJson &&
              (errJson.reason || errJson.error || "")
            ).toString();
            if (r.status === 400 && /out of allowed range/i.test(reason)) {
              url = `${urlBase}&forecast_days=7&current_weather=true`;
              r = await fetch(url);
              note =
                "Trip dates are too far; showing current conditions and 7-day outlook";
              mode = "current";
            }
          } catch {
            url = `${urlBase}&forecast_days=7&current_weather=true`;
            r = await fetch(url);
            note =
              "Trip dates are too far; showing current conditions and 7-day outlook";
            mode = "current";
          }
        }

        if (!r.ok) throw new Error("Weather fetch failed");

        const j = await r.json();

        // mapiranje dnevnih vrednosti
        const days = (j?.daily?.time || []).map((t, i) => ({
          date: t,
          tmax: j.daily?.temperature_2m_max?.[i] ?? null,
          tmin: j.daily?.temperature_2m_min?.[i] ?? null,
          wind: j.daily?.wind_speed_10m_max?.[i] ?? null,
          rain: j.daily?.precipitation_sum?.[i] ?? null,
        }));

        const avg = (arr) =>
          arr.length
            ? Math.round((arr.reduce((a, b) => a + b, 0) / arr.length) * 10) /
              10
            : null;

        // trenutni uslovi ako ih imamo (samo u fallback modu)
        const current = j.current_weather
          ? {
              temp:
                typeof j.current_weather.temperature === "number"
                  ? j.current_weather.temperature
                  : null,
              wind:
                typeof j.current_weather.windspeed === "number"
                  ? j.current_weather.windspeed
                  : null,
              code: j.current_weather.weathercode ?? null,
              time: j.current_weather.time ?? null,
            }
          : null;

        const summary = {
          coords: { lat, lon },
          avgMax: avg(
            days.map((d) => d.tmax).filter((n) => typeof n === "number")
          ),
          avgMin: avg(
            days.map((d) => d.tmin).filter((n) => typeof n === "number")
          ),
          maxWind: Math.max(
            ...days.map((d) => d.wind).filter((n) => typeof n === "number"),
            0
          ),
          totalRain: avg(
            days.map((d) => d.rain).filter((n) => typeof n === "number")
          ), // prosečna padavina po danu
          note,
          mode,
          current, // { temp, wind, code, time } ili null
        };

        const payload = { days, summary };
        cacheSet(ck, payload, ttlHours * 60 * 60 * 1000);
        if (!off) setState({ loading: false, error: null, data: payload });
      } catch (e) {
        if (!off)
          setState({
            loading: false,
            error: e.message || "Weather error",
            data: null,
          });
      }
    })();

    return () => {
      off = true;
    };
  }, [destination, startDate, endDate, ttlHours]);

  // { loading, error, data: { days[], summary{...} } }
  return state;
}
