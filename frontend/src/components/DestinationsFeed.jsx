import React from "react";
import { api } from "../api/client";
import DestinationCard from "./DestinationCard";

export default function DestinationsFeed({
  onOpenDestination,
  onAddActivityToPlan,
}) {
  const [loading, setLoading] = React.useState(false);
  const [error, setError] = React.useState("");
  const [byLocation, setByLocation] = React.useState({});

  const load = React.useCallback(async () => {
    setLoading(true);
    setError("");
    try {
      const unique = new Map();
      let page = 1;
      let keepGoing = true;

      while (keepGoing && page <= 5) {
        const { data } = await api.get(`/activities?page=${page}`);
        const items = data?.data ?? [];
        items.forEach((a) => {
          const key = a.location || "Unknown";
          const arr = unique.get(key) || [];
          arr.push(a);
          unique.set(key, arr);
        });
        const lastPage = data?.meta?.last_page ?? page;
        keepGoing = page < lastPage;
        page += 1;
      }

      // sortiraj po lokaciji
      const obj = {};
      Array.from(unique.keys())
        .sort((a, b) => a.localeCompare(b))
        .forEach((k) => (obj[k] = unique.get(k)));

      setByLocation(obj);
    } catch (e) {
      console.error(e);
      setError(
        "Something went wrong while loading activities. Please try again later."
      );
    } finally {
      setLoading(false);
    }
  }, []);

  React.useEffect(() => {
    load();
  }, [load]);

  if (loading)
    return (
      <div className="loading-box">
        <div className="loading-text">Loading destinations…</div>
      </div>
    );

  if (error)
    return (
      <div className="alert alert-warning my-4 retry-alert" role="alert">
        <span>{error}</span>
        <button
          className="btn btn-sm btn-outline-secondary ms-3"
          onClick={load}
        >
          Try again
        </button>
      </div>
    );

  const locations = Object.keys(byLocation);
  if (!locations.length)
    return (
      <div className="text-muted py-4">
        There are currently no available destinations.
      </div>
    );

  return (
    <div className="row g-3">
      {locations.map((loc) => (
        <DestinationCard
          key={loc}
          name={loc}
          activities={byLocation[loc]}
          onOpen={() => onOpenDestination?.(loc)}
          onAddToPlan={(activity) => onAddActivityToPlan?.(loc, activity)}
        />
      ))}
    </div>
  );
}
