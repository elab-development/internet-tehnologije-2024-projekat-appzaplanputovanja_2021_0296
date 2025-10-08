// src/pages/Dashboard.jsx
import React, { useEffect, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { api } from "../api/client";
import NavBar from "../components/NavBar";
import TravelPlanCard from "../components/TravelPlanCard";
import { useAuth } from "../context/AuthContext";

export default function Dashboard() {
  const { isAuth } = useAuth();
  const navigate = useNavigate();

  const [searchParams, setSearchParams] = useSearchParams(); // da čitamo query parametre iz URL-a

  // data & ui state
  const [plans, setPlans] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [alert, setAlert] = useState({ type: null, msg: "" }); // 'warning' | 'danger' | null

  // helper: normalize raznih JSON oblika iz backenda
  const normalize = (d) =>
    Array.isArray(d)
      ? d
      : Array.isArray(d?.data)
      ? d.data
      : d?.data?.data ?? [];

  // GLAVNA FUNKCIJA: pretraga/listanje planova
  async function fetchPlans(params = {}) {
    setLoading(true);
    setError("");
    setAlert({ type: null, msg: "" });

    try {
      // hasSearch: samo destinacija (q) ili samo datumi (oba), ili kombinacija
      const hasSearch = !!(params.q || (params.date_from && params.date_to));

      // ako je pretraga -> /search, inače lista svih mojih planova
      const url = hasSearch ? "/travel-plans/search" : "/travel-plans";
      const resp = await api.get(url, { params });

      // pokušaj da pročitaš meta.destination_exists iz travel plan controllera
      const meta =
        resp?.data?.meta ||
        resp?.data?.data?.meta ||
        resp?.data?.additional ||
        {};
      const destinationExists = meta?.destination_exists;

      const list = normalize(resp.data);
      setPlans(list);

      // ALERTI ZA PRAZNE REZULTATE (BEZ GREŠKE)
      if (hasSearch && list.length === 0) {
        if (params.q && !params.date_from && !params.date_to) {
          // samo destinacija, nema poklapanja
          setAlert({
            type: "warning",
            msg:
              destinationExists === false
                ? "Entered destination does not exist."
                : "You don't have travel plans for the entered parameters yet.",
          });
        } else if (!params.q && params.date_from && params.date_to) {
          // samo datumi, nema poklapanja
          setAlert({
            type: "warning",
            msg: "You don't have plans within the selected dates.",
          });
        } else if (params.q && params.date_from && params.date_to) {
          // destinacija + datumi, nema poklapanja
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
      // VALIDACIJA DATUMA (422)
      if (e?.response?.status === 422) {
        const first =
          Object.values(e?.response?.data?.errors || {})[0]?.[0] ||
          e?.response?.data?.message ||
          "Invalid date input.";
        setAlert({ type: "danger", msg: first });
        setPlans([]); // ne prikazuj listu kad je greška
        setLoading(false);
        return;
      }

      // TOKEN istekao
      if (e?.response?.status === 401) {
        navigate("/login", { replace: true });
        return;
      }

      // ostale greške
      const msg =
        e?.response?.data?.message ||
        e?.response?.data?.error ||
        e?.message ||
        "Failed to load your travel plans.";
      setError(msg);

      setPlans([]); // sakrij listu
    } finally {
      setLoading(false);
    }
  }

  // kad se promene URL parametri – ponovo fetch
  useEffect(() => {
    if (!isAuth) return;
    const params = Object.fromEntries(searchParams.entries());
    fetchPlans(params);
  }, [searchParams, isAuth]);

  const userName = localStorage.getItem("tp_user_name") || "Traveler";

  return (
    <div className="dashboard">
      {/* DASHBOARD varijanta navbar-a(Search + Create + Logout) koja cuva pretragu*/}
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
