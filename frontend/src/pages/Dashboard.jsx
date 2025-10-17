import React, { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import api from "../api/client"; // <= default import
import NavBar from "../components/NavBar";
import TravelPlanCard from "../components/TravelPlanCard";
import { useAuth } from "../context/AuthContext";

export default function Dashboard() {
  const { isAuth } = useAuth();
  const navigate = useNavigate();

  const [searchParams, setSearchParams] = useSearchParams();

  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);

  // inline UX poruke (ne API greške)
  const [alert, setAlert] = useState({ type: null, msg: "" });

  // normalize helper
  const normalize = (d) =>
    Array.isArray(d)
      ? d
      : Array.isArray(d?.data)
      ? d.data
      : d?.data?.data ?? [];

  // listanje / pretraga
  async function fetchPlans(params = {}) {
    setLoading(true);
    setAlert({ type: null, msg: "" });

    try {
      const hasSearch = !!(params.q || (params.date_from && params.date_to));
      const url = hasSearch ? "/travel-plans/search" : "/travel-plans";

      const resp = await api.get(url, { params });

      const meta =
        resp?.data?.meta ||
        resp?.data?.data?.meta ||
        resp?.data?.additional ||
        {};
      const destinationExists = meta?.destination_exists;

      const list = normalize(resp.data);
      setPlans(list);

      // UX poruke za prazan rezultat
      if (hasSearch && list.length === 0) {
        if (params.q && !params.date_from && !params.date_to) {
          setAlert({
            type: "warning",
            msg:
              destinationExists === false
                ? "Entered destination does not exist."
                : "You don't have travel plans for the entered parameters yet.",
          });
        } else if (!params.q && params.date_from && params.date_to) {
          setAlert({
            type: "warning",
            msg: "You don't have plans within the selected dates.",
          });
        } else if (params.q && params.date_from && params.date_to) {
          setAlert({
            type: "warning",
            msg:
              destinationExists === false
                ? "Entered destination does not exist."
                : "You don't have travel plans for the entered parameters yet.",
          });
        }
      }
    } catch (e) {
      // Sve API greške (422/401/403/500...) prikazuje globalni interceptor.
      // Ovde samo “počistimo” listu da UI ne pokazuje stare podatke.
      setPlans([]);
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    if (!isAuth) return; // ako nije ulogovan, NavBar će ponuditi login
    const params = Object.fromEntries(searchParams.entries());
    fetchPlans(params);
  }, [searchParams, isAuth]);

  const userName = localStorage.getItem("tp_user_name") || "Traveler";

  return (
    <div className="dashboard">
      <NavBar variant="dashboard" onReset={() => setSearchParams({})} />

      <div className="container mt-4">
        <h2 className="mb-2">Welcome, {userName}!</h2>
        <p className="text-muted mb-4">
          Here you can view your generated plans, list of all your trips, and
          everything related to your travel planning.
        </p>

        {alert.msg && (
          <div
            className={`alert alert-${
              alert.type === "danger" ? "danger" : "warning"
            }`}
          >
            {alert.msg}
          </div>
        )}

        {loading ? (
          <p>Loading...</p>
        ) : (
          <div className="plans-grid">
            {plans.map((plan) => (
              <TravelPlanCard key={plan.id} plan={plan} />
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
