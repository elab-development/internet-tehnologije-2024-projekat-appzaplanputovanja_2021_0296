import React, { useEffect, useState } from "react";
import { useParams, useNavigate } from "react-router-dom";
import api from "../api/client";
import NavBar from "../components/NavBar";
import PrimaryButton from "../components/ui/PrimaryButton";
import { confirmDialog, showError, showSuccess } from "../api/notify";

export default function ShowTravelPlan() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [plan, setPlan] = useState(null);
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(true);
  const [deleting, setDeleting] = useState(false);

  // ===== LOAD plan & items =====
  useEffect(() => {
    (async () => {
      try {
        setLoading(true);

        const { data: planRes } = await api.get(`/travel-plans/${id}`);
        setPlan(planRes.data ?? planRes);

        const { data: itemsRes } = await api.get(
          `/travel-plans/${id}/items?per_page=100`
        );
        setItems(itemsRes.data ?? itemsRes);
      } catch (err) {
        showError("Failed to load travel plan. Please try again later.");
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  // ===== Navigation helpers =====
  const handleBack = () => navigate("/dashboard");
  const handleEdit = () => navigate(`/dashboard/plans/${id}/edit`);

  // ===== DELETE with SweetAlert confirmation =====
  const handleDelete = async () => {
    const ok = await confirmDialog({
      title: "Delete travel plan?",
      text: "This action cannot be undone.",
      confirmText: "Delete",
      icon: "warning",
    });
    if (!ok) return;

    try {
      setDeleting(true);
      await api.delete(`/travel-plans/${id}`);
      showSuccess("Travel plan deleted.");
      navigate("/dashboard", { replace: true });
    } catch (err) {
      // detalji greške već će biti prikazani kroz interceptor
      showError("Failed to delete the travel plan.");
    } finally {
      setDeleting(false);
    }
  };

  // ===== PDF export =====
  const handleExportPdf = async () => {
    try {
      const response = await api.get(`/travel-plans/${id}/export/pdf`, {
        responseType: "blob",
      });

      const blob = new Blob([response.data], { type: "application/pdf" });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", `travel-plan-${id}.pdf`);
      document.body.appendChild(link);
      link.click();
      link.parentNode.removeChild(link);
      window.URL.revokeObjectURL(url);
      showSuccess("PDF successfully exported.");
    } catch (error) {
      showError("PDF export failed. Please try again later.");
    }
  };

  // ====== UI ======
  if (loading)
    return (
      <>
        <NavBar variant="dashboard" />
        <div className="container py-5 text-center">
          <div className="spinner-border text-primary" role="status"></div>
          <p className="mt-3">Loading travel plan…</p>
        </div>
      </>
    );

  if (!plan)
    return (
      <>
        <NavBar variant="dashboard" />
        <div className="container py-5 text-center">
          <div className="alert alert-danger mb-4">
            Travel plan not found or failed to load.
          </div>
          <PrimaryButton onClick={handleBack}>Back to Dashboard</PrimaryButton>
        </div>
      </>
    );

  return (
    <>
      <NavBar variant="dashboard-simple" />
      <div className="container py-4">
        {/* Plan info */}
        <div className="d-flex justify-content-between align-items-center mb-4 flex-wrap">
          <div>
            <h3 className="fw-bold mb-1">{plan.destination}</h3>
            <p className="text-muted mb-0">
              {plan.start_location} → {plan.destination} <br />
              {plan.start_date} — {plan.end_date} <br />
              <strong>Budget:</strong> ${plan.budget} ·{" "}
              <strong>Total cost:</strong> ${plan.total_cost} <br />
              <strong>Passengers:</strong> {plan.passenger_count}
            </p>
          </div>

          <div className="d-flex flex-wrap gap-2 mt-3 mt-md-0">
            <PrimaryButton onClick={handleExportPdf}>Export PDF</PrimaryButton>
            <button className="btn btn-outline-primary" onClick={handleEdit}>
              Edit Plan
            </button>
            <button
              className="btn btn-outline-danger"
              onClick={handleDelete}
              disabled={deleting}
            >
              {deleting ? "Deleting..." : "Delete"}
            </button>
          </div>
        </div>

        {/* Activities list */}
        <h5 className="mb-3">Planned Activities</h5>
        {items.length === 0 ? (
          <p className="text-muted">
            No activities found for this travel plan.
          </p>
        ) : (
          <div className="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3">
            {items.map((item) => {
              const activity = item.activity || {};
              const title = item.name ?? activity.name ?? "Activity";
              const img =
                activity.image_url ||
                "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee";

              return (
                <div className="col" key={item.id}>
                  <div className="card h-100 shadow-sm border-0 plan-item-card">
                    <img
                      src={img}
                      alt={title}
                      className="card-img-top plan-item-img"
                    />
                    <div className="card-body d-flex flex-column">
                      <h6 className="fw-bold mb-1">{title}</h6>
                      {activity.type && (
                        <span className="badge bg-secondary mb-2">
                          {activity.type}
                        </span>
                      )}
                      <p className="small text-muted mb-1">
                        {item.time_from} — {item.time_to}
                      </p>
                      <p className="small text-muted mb-1">
                        ${item.amount.toFixed(2)}
                      </p>
                      {activity.content && (
                        <p className="small text-muted mt-auto">
                          {activity.content.substring(0, 80)}…
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </>
  );
}
