// src/pages/EditTravelPlan.jsx
import React from "react";
import { useNavigate, useParams } from "react-router-dom";
import NavBar from "../components/NavBar"; // koristi varijantu "dashboard-simple"
import { api } from "../api/client";
import { useAuth } from "../context/AuthContext";
import TravelPlanForm from "../components/ui/TravelPlanForm";

export default function EditTravelPlan() {
  //    Učitavamo ID iz rute i kontekst autentikacije
  const { id } = useParams();
  const { isAuth } = useAuth();
  const navigate = useNavigate();

  //    UI state
  const [loading, setLoading] = React.useState(true);
  const [saving, setSaving] = React.useState(false);
  const [error, setError] = React.useState("");

  //    Držimo ceo plan da bismo prikazali read-only polja
  const [plan, setPlan] = React.useState(null);

  //    Editable polja forme
  const [form, setForm] = React.useState({
    start_date: "",
    end_date: "",
    passenger_count: 1,
    budget: 0,
  });

  //    Helper za promenu polja
  const onChange = (e) => {
    const { name, value } = e.target;
    setForm((f) => ({ ...f, [name]: value }));
  };

  //    Učitavanje postojećeg plana
  React.useEffect(() => {
    let mounted = true;
    (async () => {
      try {
        setLoading(true);
        setError("");
        const { data } = await api.get(`/travel-plans/${id}`);
        if (!mounted) return;

        const p = data?.data ?? data; //    fleksibilno, u zavisnosti od API resource-a
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

  const doUpdateWith = async (payload) => {
    try {
      setSaving(true);
      setError("");
      await api.put(`/travel-plans/${id}`, payload);
      navigate(`/dashboard/plans/${id}`); //    vrati korisnika na prikaz plana
    } catch (err) {
      console.error(err);
      setError("Update failed. Please check the entered values.");
    } finally {
      setSaving(false);
    }
  };

  //    Validacija osnovnih pravila (minimalno, backend je izvor istine)
  const validate = () => {
    const { start_date, end_date, passenger_count, budget } = form;

    if (!start_date || !end_date) {
      return "Start date and end date are required.";
    }
    if (new Date(start_date) >= new Date(end_date)) {
      return "End date must be after the start date.";
    }
    if (Number(passenger_count) <= 0) {
      return "Passenger count must be greater than 0.";
    }
    if (Number(budget) <= 0) {
      return "Budget must be greater than 0.";
    }
    return "";
  };

  //    Submit – ažuriranje plana i povratak na detalje
  const onSubmit = async (e) => {
    e.preventDefault();
    const v = validate();
    if (v) {
      setError(v);
      return;
    }

    try {
      setSaving(true);
      setError("");
      //    PATCH/PUT u zavisnosti od rute na backendu; najčešće PUT /travel-plans/{id}
      await api.put(`/travel-plans/${id}`, {
        start_date: form.start_date,
        end_date: form.end_date,
        passenger_count: Number(form.passenger_count),
        budget: Number(form.budget),
      });

      //    Nakon uspeha – vodi korisnika na prikaz tog plana
      navigate(`/dashboard/plans/${id}`);
    } catch (err) {
      //    Backend validacija će vratiti poruke (npr. budžet, datumi, ograničenja)
      setError("Update failed. Please check the entered values.");
    } finally {
      setSaving(false);
    }
  };

  //    Odustajanje – vrati na prikaz plana
  const onCancel = () => {
    navigate(`/dashboard/plans/${id}`);
  };

  if (!isAuth) {
    //    Poželjno je imati zaštitu ruta, ali ovde samo fallback
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
      {/*    Navbar – koristi varijantu dashboard-simple (u samoj NavBar komponenti iskoristi prop) */}
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
                      //    zaključana polja se prikazuju u disabled fieldset-u
                      start_location: plan.start_location,
                      destination: plan.destination,
                      transport_mode: plan.transport_mode,
                      accommodation_class: plan.accommodation_class,
                      preferences: Array.isArray(plan.preferences)
                        ? plan.preferences
                        : plan.preferences ?? [],

                      //    editable polja
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
                    lists={{}} //    na edit ne trebaju liste (sve je read-only osim 4 polja)
                    busy={saving}
                    fieldErrors={{}} //    mapiraj 422 ako želiš detaljna polja
                    error={error}
                    onSubmit={async (vals) => {
                      //    validacija minimalna – backend je autoritet
                      await doUpdateWith({
                        start_date: vals.start_date,
                        end_date: vals.end_date,
                        passenger_count: Number(vals.passenger_count),
                        budget: Number(vals.budget),
                      });
                    }}
                    onCancel={() => navigate(`/dashboard/plans/${id}`)}
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
