import React from "react";
import { useNavigate } from "react-router-dom";

export default function TravelPlanCard({ plan }) {
  const navigate = useNavigate();

  return (
    <div
      className="tp-card card shadow-sm mb-3"
      onClick={() => navigate(`/dashboard/plans/${plan.id}`)}
      style={{ cursor: "pointer" }}
    >
      <div className="card-body">
        <h5 className="card-title mb-3">{plan.destination}</h5>
        <p className="mb-1">
          <strong>From:</strong> {plan.start_date}
        </p>
        <p className="mb-1">
          <strong>To:</strong> {plan.end_date}
        </p>
        <p className="mb-0 text-muted">
          <strong>Total cost:</strong> ${Number(plan.total_cost).toFixed(2)}
        </p>
      </div>
    </div>
  );
}
