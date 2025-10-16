// src/pages/EditTravelPlan.jsx
import React from "react";
import { useNavigate, useParams } from "react-router-dom";
import NavBar from "../components/NavBar";
import { api } from "../api/client";
import { useAuth } from "../context/AuthContext";
import TravelPlanForm from "../components/ui/TravelPlanForm";

export default function EditTravelPlan() {
  const { id } = useParams();
  const { isAuth } = useAuth();
  const navigate = useNavigate();

  const [loading, setLoading] = React.useState(true);
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState("");
  const [fieldErrors, setFieldErrors] = React.useState({});

  const [plan, setPlan] = React.useState(null);
  const [form, setForm] = React.useState({
    start_date: "",
    end_date: "",
    passenger_count: 1,
    budget: 0,
  });

  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        setLoading(true);
        setError("");
        setFieldErrors({});
        const { data } = await api.get(`/travel-plans/${id}`);
        if (!mounted) return;
        const p = data?.data ?? data;
        setPlan(p);
        setForm({
          start_date: p?.start_date ?? "",
          end_date: p?.end_date ?? "",
          passenger_count: p?.passenger_count ?? 1,
          budget: p?.budget ?? 0,
        });
      } catch (err) {
        setError("Failed to load the travel plan.");
      } finally {
        setLoading(false);
      }
    })();
    return () => {
      mounted = false;
    };
  }, [id]);

  const validateVals = (vals) => {
    const { start_date, end_date, passenger_count, budget } = vals;
    if (!start_date || !end_date)
      return "Start date and end date are required.";
    if (new Date(start_date) >= new Date(end_date))
      return "End date must be after the start date.";
    if (Number(passenger_count) <= 0)
      return "Passenger count must be greater than 0.";
    if (Number(budget) <= 0) return "Budget must be greater than 0.";
    return "";
  };

  const toIso = (s) => {
    if (!s) return s;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    const m = String(s).match(/^(\d{2})\.(\d{2})\.(\d{4})\.?$/);
    if (m) return `${m[3]}-${m[2]}-${m[1]}`;
    return s;
  };
  const buildPayload = (vals, plan) => {
    const payload = {};
    const startIso = toIso(vals.start_date);
    const endIso = toIso(vals.end_date);

    if (startIso && startIso !== plan.start_date) payload.start_date = startIso;
    if (endIso && endIso !== plan.end_date) payload.end_date = endIso;

    const pc = Number(vals.passenger_count);
    const bd = Number(vals.budget);

    if (!Number.isNaN(pc) && pc !== Number(plan.passenger_count))
      payload.passenger_count = pc;
    if (!Number.isNaN(bd) && bd !== Number(plan.budget)) payload.budget = bd;

    return payload;
  };

  // izračunaj da li postoji stvarna promena (za disable submit-a)
  const hasChanges = React.useMemo(() => {
    if (!plan) return false;
    const payload = buildPayload(form, plan);
    return Object.keys(payload).length > 0;
  }, [plan, form]);

  const onSubmit = async (vals) => {
    const v = validateVals(vals);
    if (v) {
      setError(v);
      return;
    }

    if (!plan) return;

    const payload = buildPayload(vals, plan);
    if (Object.keys(payload).length === 0) {
      setError("No changes to save.");
      return;
    }

    try {
      setSaving(true);
      setError("");
      setFieldErrors({});
      await api.patch(`/travel-plans/${id}`, payload);
      navigate(`/dashboard/plans/${id}`);
    } catch (err) {
      const status = err?.response?.status;
      const data = err?.response?.data;
      if (status === 422) {
        const errs = data?.errors || {};
        setFieldErrors(
          Object.fromEntries(
            Object.entries(errs).map(([k, v]) => [k, v?.[0] ?? "Invalid value"])
          )
        );
        setError(data?.message || "Validation failed.");
      } else {
        setError(
          data?.message ||
            data?.error ||
            "Update failed. Please check the entered values."
        );
      }
    } finally {
      setSaving(false);
    }
  };

  const onCancel = () => navigate(`/dashboard/plans/${id}`);

  if (!isAuth) {
    return (
      <>
        <NavBar variant="dashboard-simple" />
        <div className="container py-4">
          <div className="alert alert-warning">You must be logged in.</div>
        </div>
      </>
    );
  }

  return (
    <>
      <NavBar variant="dashboard-simple" />

      <div className="container py-4 edit-plan-container">
        <div className="row justify-content-center">
          <div className="col-lg-8">
            <div className="card shadow-sm">
              <div className="card-header bg-light">
                <h5 className="mb-0">Edit travel plan</h5>
              </div>
              <div className="card-body">
                {loading ? (
                  <div className="alert alert-info mb-0">Loading...</div>
                ) : error ? (
                  <div className="alert alert-danger mb-3">{error}</div>
                ) : (
                  <TravelPlanForm
                    mode="edit"
                    initialValues={{
                      start_location: plan.start_location,
                      destination: plan.destination,
                      transport_mode: plan.transport_mode,
                      accommodation_class: plan.accommodation_class,
                      preferences: Array.isArray(plan.preferences)
                        ? plan.preferences
                        : plan.preferences ?? [],
                      start_date: form.start_date,
                      end_date: form.end_date,
                      passenger_count: form.passenger_count,
                      budget: form.budget,
                    }}
                    lockedFields={[
                      "start_location",
                      "destination",
                      "transport_mode",
                      "accommodation_class",
                      "preferences",
                    ]}
                    lists={{}}
                    busy={saving}
                    fieldErrors={fieldErrors} // <= NOVO
                    error={error}
                    onSubmit={onSubmit}
                    onCancel={onCancel}
                    // ako TravelPlanForm podržava: onChange, disableSubmit
                    disableSubmit={saving || !hasChanges}
                  />
                )}
              </div>
            </div>

            <div className="alert alert-light mt-3 small text-muted">
              Changes to dates, passenger count, or budget may trigger automatic
              updates to schedule and total cost based on backend rules.
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
