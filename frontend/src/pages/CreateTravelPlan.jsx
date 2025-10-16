// src/pages/CreateTravelPlan.jsx
import React from "react";
import { useNavigate } from "react-router-dom";
import NavBar from "../components/NavBar";
import { api } from "../api/client";
import { useAuth } from "../context/AuthContext";
import TravelPlanForm from "../components/ui/TravelPlanForm";
import useActivityOptions from "../hooks/useActivityOptions";

const humanize = (s) =>
  String(s)
    .replace(/_/g, " ")
    .replace(/\b[a-z]/g, (c) => c.toUpperCase());

export default function CreateTravelPlan() {
  const nav = useNavigate();
  const { isAuth } = useAuth();

  // sve opcije sa backenda (destinacije, start lokacije, modovi, klase, preferencije)
  const {
    loading: optsLoading,
    error: optsError,
    destinations,
    startLocations,
    transportModes,
    accommodationClasses,
    preferences,
  } = useActivityOptions();

  // form state
  const [form, setForm] = React.useState({
    start_location: "",
    destination: "",
    start_date: "",
    end_date: "",
    passenger_count: 1,
    budget: "",
    preferences: [],
    transport_mode: "",
    accommodation_class: "",
  });

  // ui state
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState("");
  const [fieldErrors, setFieldErrors] = React.useState({});

  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((f) => ({ ...f, [name]: value }));
  };
  const onChangeNumber = (e) => {
    const { name, value } = e.target;
    const num = value === "" ? "" : Number(value);
    setForm((f) => ({ ...f, [name]: num }));
  };
  const onChangePrefs = (vals) => {
    setForm((f) => ({ ...f, preferences: vals }));
  };

  const handleSubmitWith = async (vals) => {
    try {
      setBusy(true);
      setError("");
      setFieldErrors({});

      const payload = {
        start_location: vals.start_location,
        destination: vals.destination,
        start_date: vals.start_date,
        end_date: vals.end_date,
        passenger_count: Number(vals.passenger_count),
        budget: Number(vals.budget),
        transport_mode: vals.transport_mode,
        accommodation_class: vals.accommodation_class,
        preferences: vals.preferences,
      };

      const { data } = await api.post("/travel-plans", payload);
      const plan = data?.data ?? data;
      nav(`/dashboard/plans/${plan.id}`);
    } catch (err) {
      if (err?.response?.status === 422) {
        const errs = err.response.data.errors || {};
        setFieldErrors(
          Object.fromEntries(
            Object.entries(errs).map(([k, v]) => [k, v?.[0] ?? "Invalid value"])
          )
        );
      } else {
        setError("Create failed. Please check the entered values.");
      }
    } finally {
      setBusy(false);
    }
  };

  // UI helpers
  const ACCOMM_OPTS = React.useMemo(
    () =>
      (accommodationClasses || []).map((v) => ({
        value: v,
        label: humanize(v),
      })),
    [accommodationClasses]
  );

  return (
    <div className="create-plan">
      <NavBar variant="dashboard-simple" />
      <div className="container my-4">
        <h2 className="mb-1">Create a new travel plan</h2>
        <p className="text-muted mb-4">
          Fill out the form and generate a complete itinerary within your
          budget.
        </p>

        {optsLoading && !optsError ? (
          <div className="alert alert-info">Loading activity options…</div>
        ) : optsError ? (
          <div className="alert alert-warning">
            Failed to load options. You can still try to submit the form.
          </div>
        ) : null}

        <TravelPlanForm
          mode="create"
          initialValues={form}
          lockedFields={[]}
          lists={{
            startLocations,
            destinations,
            transportModes,
            accommodationOptions: ACCOMM_OPTS,
            preferencesList: preferences,
          }}
          busy={busy || optsLoading}
          fieldErrors={fieldErrors}
          error={error}
          onSubmit={handleSubmitWith}
          onCancel={() => nav("/dashboard")}
          // (opciono) ako TravelPlanForm prima ova 3 handlera:
          onChange={onChange}
          onChangeNumber={onChangeNumber}
          onChangePrefs={onChangePrefs}
        />
      </div>
    </div>
  );
}
