// src/pages/CreateTravelPlan.jsx
import React from "react";
import { useNavigate } from "react-router-dom";
import NavBar from "../components/NavBar";
import { api } from "../api/client";
import { useAuth } from "../context/AuthContext";
import TravelPlanForm from "../components/ui/TravelPlanForm";

// Enumerations aligned with backend migrations
// transport_mode: ['airplane','train','car','bus','ferry','cruise ship']
// accommodation_class: ['hostel','guesthouse','budget_hotel','standard_hotel','boutique_hotel','luxury_hotel','resort','apartment','bed_and_breakfast','villa','mountain_lodge','camping','glamping']
const TRANSPORT_MODES = [
  "airplane",
  "train",
  "car",
  "bus",
  "ferry",
  "cruise ship",
];

const ACCOMMODATION_CLASSES = [
  "hostel",
  "guesthouse",
  "budget_hotel",
  "standard_hotel",
  "boutique_hotel",
  "luxury_hotel",
  "resort",
  "apartment",
  "bed_and_breakfast",
  "villa",
  "mountain_lodge",
  "camping",
  "glamping",
];

// Preferences (match backend Activity::availablePreferenceTypes / your JSON column)
const PREFERENCES = [
  "travel_with_children",
  "enjoy_nature",
  "love_food_and_drink",
  "want_to_relax",
  "want_culture",
  "seek_fun",
  "adventurous",
  "avoid_crowds",
  "comfortable_travel",
  "cafe_sitting",
  "shopping",
  "want_to_learn",
  "active_vacation",
  "research_of_tradition",
];

const humanize = (s) =>
  s.replace(/_/g, " ").replace(/\b[a-z]/g, (c) => c.toUpperCase());

const ACCOMM_OPTS = ACCOMMODATION_CLASSES.map((v) => ({
  value: v,
  label: humanize(v),
}));

export default function CreateTravelPlan() {
  const nav = useNavigate();
  const { isAuth } = useAuth();

  // destinations will be fetched from /activities and grouped by unique location
  const [destinations, setDestinations] = React.useState([]);

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

  // today min for start_date (backend: after:today)
  const todayStr = React.useMemo(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `${y}-${m}-${day}`;
  }, []);

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

  const loadDestinations = React.useCallback(async () => {
    try {
      const unique = new Set();
      let page = 1;
      let keepGoing = true;

      while (keepGoing && page <= 10) {
        const { data } = await api.get(`/activities?page=${page}`);
        const items = data?.data ?? [];
        items.forEach((a) => {
          if (a.location) unique.add(a.location);
        });
        const last = data?.meta?.last_page ?? page;
        keepGoing = page < last;
        page++;
      }
      setDestinations(Array.from(unique).sort((a, b) => a.localeCompare(b)));
    } catch (e) {
      console.error(e);
      setDestinations([]);
    }
  }, []);

  React.useEffect(() => {
    if (!isAuth) return;
    loadDestinations();
  }, [isAuth, loadDestinations]);

  const handleCancel = () => {
    nav("/dashboard");
  };

  // helper za CREATE novog plana
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
        preferences: vals.preferences, // niz stringova
      };

      const { data } = await api.post("/travel-plans", payload);
      const plan = data?.data ?? data;
      nav(`/dashboard/plans/${plan.id}`);
    } catch (err) {
      // primer mapiranja 422 validacije
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

  return (
    <div className="create-plan">
      <NavBar variant="dashboard-simple" />
      <div className="container my-4">
        <h2 className="mb-1">Create a new travel plan</h2>
        <p className="text-muted mb-4">
          Fill out the form and generate a complete itinerary within your
          budget.
        </p>

        <TravelPlanForm
          mode="create"
          initialValues={{
            start_location: form.start_location,
            destination: form.destination,
            start_date: form.start_date,
            end_date: form.end_date,
            passenger_count: form.passenger_count,
            budget: form.budget,
            preferences: form.preferences,
            transport_mode: form.transport_mode,
            accommodation_class: form.accommodation_class,
          }}
          lockedFields={[]} //    na create nema zaključanih polja
          lists={{
            destinations,
            transportModes: TRANSPORT_MODES,
            accommodationOptions: ACCOMM_OPTS,
            preferencesList: PREFERENCES,
          }}
          busy={busy}
          fieldErrors={fieldErrors}
          error={error}
          onSubmit={handleSubmitWith}
          onCancel={() => nav("/dashboard")}
        />
      </div>
    </div>
  );
}
