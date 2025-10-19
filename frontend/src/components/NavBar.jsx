import React, { useEffect, useState } from "react";
import {
  Link,
  NavLink,
  useLocation,
  useNavigate,
  useSearchParams,
} from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { getToken } from "../api/client";
import CurrencySwitcher from "../components/CurrencySwitcher";

export default function NavBar({
  variant = "auto",
  onDestinationsClick,
  onBrandClick,
}) {
  const { isAuth, logout } = useAuth();
  const nav = useNavigate();
  const { pathname } = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();

  // Ko je ulogovan (stabilno i posle refresh-a)
  const loggedIn = isAuth || !!getToken();

  // Odredi mod prikaza
  // Automatska varijanta po ruti (ako nije eksplicitno prosleđena)
  let mode = variant;
  if (variant === "auto") {
    // ako je korisnik na /dashboard/create
    const isCreatePage = loggedIn && pathname.startsWith("/dashboard/create");
    if (isCreatePage) {
      mode = "dashboard-simple"; // poseban mod za kreiranje plana
    } else if (loggedIn && pathname.startsWith("/dashboard"))
      mode = "dashboard";
    else if (loggedIn) mode = "home";
    else mode = "guest";
  }

  //  DASHBOARD: local state za polja + sync sa URL-om
  const [q, setQ] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  // Popuni inpute iz URL-a kad se promeni (otvaranje linka / refresh)
  useEffect(() => {
    if (mode !== "dashboard") return;
    setQ(searchParams.get("q") || "");
    setDateFrom(searchParams.get("date_from") || "");
    setDateTo(searchParams.get("date_to") || "");
  }, [mode, searchParams]);

  const handleLogout = async () => {
    await logout();
    nav("/", { replace: true });
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    if (mode !== "dashboard") return;

    const params = {};
    if (q?.trim()) params.q = q.trim();
    if (dateFrom && dateTo) {
      params.date_from = dateFrom;
      params.date_to = dateTo;
    }

    setSearchParams(params); // trigger-uje fetch u Dashboard.jsx
  };

  const handleReset = () => {
    if (mode !== "dashboard") return;
    setQ("");
    setDateFrom("");
    setDateTo("");
    setSearchParams({}); // briše filtere iz URL-a => “pre pretrage”
  };

  return (
    <nav className="app-navbar navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
      <div className="container">
        {/*  Brand: home za guest/home, dashboard reset za dashboard */}
        <Link
          className="navbar-brand d-flex align-items-center gap-2"
          to="/"
          onClick={(e) => {
            if (pathname === "/") {
              // već smo na Home → NEMA navigacije, samo reset
              e.preventDefault();
              onBrandClick?.();
            }
          }}
        >
          <img src="/travel-icon.png" alt="Travel Planner" height="22" />
          <span className="fw-bold">Travel Planner</span>
        </Link>
        <button
          className="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNavbar"
        >
          <span className="navbar-toggler-icon"></span>
        </button>

        <div className="collapse navbar-collapse" id="mainNavbar">
          <ul className="navbar-nav me-auto mb-2 mb-lg-0">
            {(mode === "guest" || mode === "home") && (
              <li className="nav-item">
                <a
                  href="#feed"
                  className="nav-link"
                  onClick={(e) => {
                    e.preventDefault();
                    onDestinationsClick?.();
                  }}
                >
                  Destination
                </a>
              </li>
            )}
          </ul>

          {/* Desna strana */}
          {mode === "guest" && (
            <div className="d-flex align-items-center gap-2">
              <CurrencySwitcher size="sm" />
              <Link to="/login" className="btn btn-outline-primary">
                Login / Sign up
              </Link>
            </div>
          )}

          {mode === "home" && (
            <div className="d-flex align-items-center gap-2">
              <CurrencySwitcher size="sm" />
              <Link to="/dashboard" className="btn btn-outline-secondary">
                My account
              </Link>
              <button className="btn btn-primary" onClick={handleLogout}>
                Logout
              </button>
            </div>
          )}
          {mode === "dashboard-simple" && (
            <div className="d-flex align-items-center gap-2">
              <Link to="/dashboard" className="btn btn-outline-secondary">
                My account
              </Link>
              <button className="btn btn-primary" onClick={handleLogout}>
                Logout
              </button>
            </div>
          )}
          {mode === "dashboard" && (
            <div className="d-flex align-items-center gap-2">
              <form
                className="d-flex align-items-center gap-2"
                onSubmit={handleSearchSubmit}
              >
                <input
                  name="q"
                  className="form-control"
                  placeholder="Destination..."
                  value={q}
                  onChange={(e) => setQ(e.target.value)}
                />
                <input
                  name="date_from"
                  type="date"
                  className="form-control"
                  aria-label="From"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                />
                <input
                  name="date_to"
                  type="date"
                  className="form-control"
                  aria-label="To"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                />
                <button className="btn btn-outline-primary" type="submit">
                  Search
                </button>
                <button
                  className="btn btn-outline-secondary"
                  type="button"
                  onClick={handleReset}
                >
                  Reset
                </button>
              </form>
              <button
                className="btn btn-success"
                onClick={() => nav("/dashboard/create")}
              >
                + Create Plan
              </button>
              <button className="btn btn-outline-danger" onClick={handleLogout}>
                Logout
              </button>
            </div>
          )}
        </div>
      </div>
    </nav>
  );
}
