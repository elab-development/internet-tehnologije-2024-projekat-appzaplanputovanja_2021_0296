import React from "react";

export default function NavBar({ onLoginClick, onDestinationsClick }) {
  return (
    <nav className="app-navbar navbar navbar-expand-lg navbar-light bg-light shadow-sm sticky-top">
      <div className="container">
        <a
          className="navbar-brand fw-bold"
          href="#"
          onClick={(e) => e.preventDefault()}
        >
          Travel Planner
        </a>

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
              <a
                className="nav-link"
                href="#"
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
            <a
              href="#"
              className="btn btn-outline-primary"
              onClick={(e) => {
                e.preventDefault();
                onLoginClick?.();
              }}
            >
              Login / Sign up
            </a>
          </div>
        </div>
      </div>
    </nav>
  );
}
