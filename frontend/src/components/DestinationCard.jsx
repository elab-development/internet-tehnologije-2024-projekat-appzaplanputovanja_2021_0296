// src/components/DestinationCard.jsx
import React from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import Price from "./Price";

function ActivityCard({ activity }) {
  const img =
    activity.image_url ||
    "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee"; // global fallback

  return (
    <div className="card h-100 shadow-sm border-0">
      <img
        src={img}
        alt={activity.name}
        className="card-img-top"
        style={{ objectFit: "cover", height: 140 }} // inline stil
        loading="lazy" // lazy loading
        decoding="async" // ne blokira
        fetchPriority="high" // prioritetno učitavanje
      />
      <div className="card-body d-flex flex-column">
        <div className="d-flex justify-content-between align-items-start mb-1">
          <h6 className="mb-0">{activity.name}</h6>
          {activity.type && (
            <span className="badge bg-secondary">{activity.type}</span>
          )}
        </div>
        <p className="text-muted small mb-0">
          Duration: {activity.duration} min · Price:{" "}
          <Price amount={activity.price} />
        </p>
      </div>
    </div>
  );
}

export default function DestinationCard({
  name,
  activities = [],
  showNext, // prikaži dugme samo ako ima sledeću destinaciju
  onNext, // skrol na sledeću destinaciju
}) {
  const navigate = useNavigate();
  const { isAuth } = useAuth();

  const handleCreatePlan = () => {
    if (isAuth) navigate("/dashboard/create");
    else navigate("/login");
  };

  return (
    <div className="card mb-4 shadow-sm border-0">
      <div className="card-body">
        <div className="d-flex justify-content-between align-items-center mb-3">
          <h5 className="card-title mb-0">{name}</h5>

          <div className="d-flex gap-2">
            <button
              type="button"
              className="btn btn-outline-primary"
              onClick={handleCreatePlan}
            >
              Create plan
            </button>
          </div>
        </div>

        {activities.length ? (
          <div className="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3">
            {activities.map((a) => (
              <div className="col" key={a.id}>
                <ActivityCard activity={a} />
              </div>
            ))}
          </div>
        ) : (
          <div className="text-muted">No activities for this destination.</div>
        )}
      </div>
    </div>
  );
}
