// src/components/Layout.jsx
import React from "react";
import Breadcrumbs from "./Breadcrumbs";

export default function Layout({ children }) {
  return (
    <div className="d-flex flex-column min-vh-100">
      {/* Glavni sadržaj – stranica sama dodaje svoj NavBar */}
      <main className="flex-grow-1">{children}</main>

      {/* Footer sa breadcrumbs na dnu svake stranice */}
      <footer className="bg-light border-top py-2 mt-auto">
        <div className="container">
          <Breadcrumbs />
        </div>
      </footer>
    </div>
  );
}
