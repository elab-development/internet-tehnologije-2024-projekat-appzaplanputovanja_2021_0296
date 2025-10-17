import React from "react";
import DestinationCard from "./DestinationCard";
import useDestinationsFeed from "../hooks/useDestinationsFeed";

export default function DestinationsFeed({
  onOpenDestination,
  onAddActivityToPlan,
}) {
  // 12 lokacija po strani (po želji promeni broj)
  const { loading, error, currentPage, lastPage, getPage, loadPage } =
    useDestinationsFeed(12);

  React.useEffect(() => {
    loadPage(1); // učitaj prvu stranu pri mountu
  }, [loadPage]);

  if (loading && !getPage(1)) {
    return (
      <div className="loading-box">
        <div className="loading-text">Loading destinations…</div>
      </div>
    );
  }

  if (error && !getPage(currentPage)) {
    return (
      <div className="alert alert-warning my-4 retry-alert" role="alert">
        <span>{error}</span>
        <button
          className="btn btn-sm btn-outline-secondary ms-3"
          onClick={() => loadPage(currentPage || 1)}
        >
          Try again
        </button>
      </div>
    );
  }

  const pageData = getPage(currentPage) || { locations: [], cards: {} };
  const locations = pageData.locations || [];

  if (!locations.length) {
    return (
      <div className="text-muted py-4">
        There are currently no available destinations.
      </div>
    );
  }

  return (
    <>
      <div className="row g-3">
        {locations.map((loc) => (
          <DestinationCard
            key={loc}
            name={loc}
            activities={pageData.cards?.[loc] || []}
            onOpen={() => onOpenDestination?.(loc)}
            onAddToPlan={(activity) => onAddActivityToPlan?.(loc, activity)}
          />
        ))}
      </div>
    </>
  );
}
