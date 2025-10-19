// src/hooks/useFxRates.js
import React from "react";
import { cacheGet, cacheSet } from "../utils/cache";
import { SUPPORTED } from "../utils/currency";

const BASE = "EUR";
const KEY = `fx:${BASE}:${SUPPORTED.sort().join(",")}`;

async function fetchFrankfurter() {
  // Frankfurter - javni API
  const url = `https://api.frankfurter.app/latest?from=${BASE}&to=${SUPPORTED.filter(
    (c) => c !== BASE
  ).join(",")}`;
  const res = await fetch(url);
  if (!res.ok) throw new Error(`frankfurter.app ${res.status}`);
  const data = await res.json();
  const rates = data?.rates || {};
  if (!Object.keys(rates).length) throw new Error("frankfurter empty");
  return { base: BASE, rates: { ...rates, [BASE]: 1 }, date: data.date };
}

export default function useFxRates(ttlHours = 24) {
  const [state, setState] = React.useState({
    loading: true,
    error: null,
    fx: null,
  });

  const load = React.useCallback(async () => {
    setState({ loading: true, error: null, fx: null });

    // 1) valid cache?
    const cached = cacheGet(KEY);
    const valid =
      cached && cached.base && cached.rates && Object.keys(cached.rates).length;
    if (valid) {
      setState({ loading: false, error: null, fx: cached });
      return;
    }

    try {
      // 2) primarni pokušaj — traži sve simbole odjednom
      const fx = await fetchFrankfurter();

      // 3) // sanity: jesu li pokrivene sve SUPPORTED?
      const missing = SUPPORTED.filter((c) => fx.rates[c] == null);
      if (missing.length) throw new Error(`missing: ${missing.join(",")}`);
      cacheSet(KEY, fx, ttlHours * 60 * 60 * 1000);
      setState({ loading: false, error: null, fx });
    } catch (e) {
      setState({ loading: false, error: `FX offline: ${e.message}`, fx: null });
    }
  }, [ttlHours]);

  React.useEffect(() => {
    load();
  }, [load]);

  return { ...state, reload: load, base: BASE, key: KEY };
}
