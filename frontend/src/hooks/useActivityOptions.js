// src/hooks/useActivityOptions.js
import React from "react";
import { api } from "../api/client";

const LS_KEY = "activity_options_v1";
const TTL_MS = 24 * 60 * 60 * 1000; // 24h

export default function useActivityOptions() {
  const [state, setState] = React.useState({
    loading: true,
    error: "",
    destinations: [],
    startLocations: [],
    transportModes: [],
    accommodationClasses: [],
    preferences: [],
  });

  React.useEffect(() => {
    let aborted = false;

    const setData = (data) => {
      if (aborted) return;
      setState({
        loading: false,
        error: "",
        destinations: data.destinations || [],
        startLocations: data.startLocations || [],
        transportModes: data.transportModes || [],
        accommodationClasses:
          data.accommodationCls || data.accommodationClasses || [],
        preferences: data.preferences || [],
      });
    };

    // 1) proba iz LS (instant UI)
    try {
      const cached = JSON.parse(localStorage.getItem(LS_KEY) || "null");
      if (cached && Date.now() - cached.savedAt < TTL_MS) {
        setData(cached.data);
      }
    } catch {}

    // 2) revalidate/fetch
    (async () => {
      try {
        const { data } = await api.get("/activity-options");
        if (!aborted) {
          setData(data);
          localStorage.setItem(
            LS_KEY,
            JSON.stringify({ savedAt: Date.now(), data })
          );
        }
      } catch (e) {
        if (!aborted) {
          setState((s) => ({
            ...s,
            loading: false,
            error: "Failed to load options.",
          }));
        }
      }
    })();

    return () => {
      aborted = true;
    };
  }, []);

  return state;
}
