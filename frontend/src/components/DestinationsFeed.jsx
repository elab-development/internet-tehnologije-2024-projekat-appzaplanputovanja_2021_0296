// src/components/DestinationsFeed.jsx
import React from "react";
import DestinationCard from "./DestinationCard";
import useDestinationsFeed from "../hooks/useDestinationsFeed";

export default function DestinationsFeed({
  onAddActivityToPlan,
  perPage = 1, // podrazumevano 1 kartica po strani
  forcePage, // npr. 1
  resetSignal, // broj koji se menja da bi retrigerovao isti forcePage
}) {
  // HOOKOVI UVEK NA VRHU
  const { loading, error, currentPage, lastPage, getPage, loadPage } =
    useDestinationsFeed(perPage);

  // refs niz za sekcije destinacija
  const sectionRefs = React.useRef([]);

  // resetuj refs kada se promeni strana
  React.useEffect(() => {
    sectionRefs.current = [];
  }, [currentPage]);

  React.useEffect(() => {
    loadPage(1); // učitaj prvu stranu pri mountu
  }, [loadPage]);

  // spoljašnji "reset na stranu"
  React.useEffect(() => {
    if (typeof forcePage === "number") {
      loadPage(forcePage);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [forcePage, resetSignal]);

  // pomeri pogled na vrh + početak destinacije kad se promeni stranica
  React.useEffect(() => {
    // skrol na vrh
    window.scrollTo({ top: 0, behavior: "smooth" });
    // osiguraj i početak prve sekcije
    requestAnimationFrame(() => {
      const first = sectionRefs.current?.[0];
      if (first) first.scrollIntoView({ behavior: "smooth", block: "start" });
    });
  }, [currentPage]);

  // --- rani return-ovi ---
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

  // sigurno skrolovanje — čeka dok se elementi ne pojave
  /* const scrollToIndex = (idx) => {
    if (idx < 0) idx = 0;
    if (idx >= locations.length) return;

    let tries = 0;
    const maxTries = 20; // pokušaj 2 sekunde max (20 × 100ms)

    const tryScroll = () => {
      const el = sectionRefs.current[idx];
      if (el) {
        el.scrollIntoView({ behavior: "smooth", block: "start" });
      } else if (tries < maxTries) {
        tries++;
        setTimeout(tryScroll, 100);
      }
    };

    tryScroll();
  };*/

  if (!locations.length) {
    return (
      <div className="text-muted py-4">
        There are currently no available destinations.
      </div>
    );
  }

  return (
    <div className="row g-3">
      {locations.map((loc, i) => (
        <div
          key={`${currentPage}-${i}-${loc}`} // jedinstven ključ po strani
          ref={(el) => (sectionRefs.current[i] = el)}
          className="col-12 dest-section"
        >
          <DestinationCard
            name={loc}
            activities={pageData.cards?.[loc] || []}
            onAddToPlan={(activity) => onAddActivityToPlan?.(loc, activity)}
          />
        </div>
      ))}

      {/* Pager samo ako ima više strana */}
      {lastPage > 1 && (
        <div className="d-flex justify-content-center align-items-center gap-2 mt-3">
          {/* Prev uvek prikaži (disable na prvoj) */}
          <button
            className="btn btn-outline-secondary btn-sm"
            disabled={currentPage <= 1}
            onClick={() => loadPage(currentPage - 1)}
          >
            ‹ Prev destination
          </button>

          <span className="small">{currentPage} / 5 </span>

          {/* Next disable samo na poslednjoj */}
          {currentPage <= 5 && (
            <button
              className="btn btn-outline-secondary btn-sm"
              disabled={currentPage >= 5}
              onClick={() => loadPage(currentPage + 1)}
            >
              Next destination ›
            </button>
          )}
        </div>
      )}
    </div>
  );
}
