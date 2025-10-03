import React from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function NavBar({ onDestinationsClick }) {
  const { isAuth, logout } = useAuth();
  const nav = useNavigate();
  const handleLogout = async () => {
    await logout();
    nav("/", { replace: true });
  };

  return (
    <nav className="app-navbar navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
      <div className="container">
        <Link className="navbar-brand fw-bold" to="/">
          Travel Planner
        </Link>

        <button
          className="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#mainNavbar"
          aria-controls="mainNavbar"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span className="navbar-toggler-icon"></span>
        </button>

        <div className="collapse navbar-collapse" id="mainNavbar">
          <ul className="navbar-nav me-auto mb-2 mb-lg-0">
            <li className="nav-item">
              <Link className="nav-link" to="/">
                Home
              </Link>
            </li>
            <li className="nav-item">
              <a
                href="#feed"
                className="nav-link"
                onClick={(e) => {
                  e.preventDefault();
                  onDestinationsClick?.();
                }}
              >
                Destinations
              </a>
            </li>
          </ul>
          <div className="d-flex align-items-center gap-2">
            {isAuth ? (
              <>
                <Link to="/dashboard" className="btn btn-outline-secondary">
                  My account
                </Link>
                <button onClick={handleLogout} className="btn btn-primary">
                  Logout
                </button>
              </>
            ) : (
              <Link to="/login" className="btn btn-outline-primary">
                Login / Sign up
              </Link>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
}
