import React from "react";
import { useNavigate, useParams } from "react-router-dom";
import NavBar from "../components/NavBar";
import api from "../api/client";
import { useAuth } from "../context/AuthContext";
import TravelPlanForm from "../components/ui/TravelPlanForm";

export default function EditTravelPlan() {
  const { id } = useParams();
  const { isAuth } = useAuth();
  const navigate = useNavigate();

  const [loading, setLoading] = React.useState(true);
  const [saving, setSaving] = React.useState(false);

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

  const hasChanges = React.useMemo(() => {
    if (!plan) return false;
    const payload = buildPayload(form, plan);
    return Object.keys(payload).length > 0;
  }, [plan, form]);

  const onSubmit = async (vals) => {
    const v = validateVals(vals);
    if (v) {
      // lokalna UX validacija pre slanja (interceptor ne zna za nju)
      window.scrollTo({ top: 0, behavior: "smooth" });
      return;
    }
    if (!plan) return;

    const payload = buildPayload(vals, plan);
    if (Object.keys(payload).length === 0) {
      // nema promena
      return;
    }

    setSaving(true);
    try {
      await api.patch(`/travel-plans/${id}`, payload);
      navigate(`/dashboard/plans/${id}`);
    } catch (err) {
      // vec obradjeno globalno u api clientu
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
                ) : !plan ? (
                  <div className="alert alert-danger mb-3">
                    Failed to load the travel plan.
                  </div>
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
                    fieldErrors={{}} /* interceptor pokazuje poruke */
                    error="" /* interceptor pokazuje poruke */
                    onSubmit={onSubmit}
                    onCancel={onCancel}
                    disableSubmit={saving || !hasChanges}
                    onFormChange={(vals) => setForm(vals)}
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
