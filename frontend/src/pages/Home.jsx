import React from "react";
import NavBar from "../components/NavBar";
import DestinationsFeed from "../components/DestinationsFeed";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function Home() {
  //const handleLoginClick = React.useCallback(() => {
  // Privremeno: samo navigacioni hook za kasnije (login stranicu ćemo dodati u sledećem koraku)
  // window.location.href = "/login"; // ili koristimo react-router kasnije
  //   alert("Ovde će ići navigacija ka /login – dodaćemo u sledećem koraku.");
  // }, []);

  const navigate = useNavigate();
  const { isAuth } = useAuth();

  const handleLoginClick = () => {
    if (isAuth) navigate("/dashboard");
    else navigate("/login");
  };

  const handleDestinationsClick = React.useCallback(() => {
    // Scroll na feed
    const el = document.getElementById("feed");
    if (el) el.scrollIntoView({ behavior: "smooth" });
  }, []);

  const handleOpenDestination = React.useCallback((name) => {
    // Navigacija na stranicu destinacije (uskoro ćemo dodati rute)
    alert(`Otvaram stranicu destinacije: ${name}`);
  }, []);

  return (
    <>
      <NavBar
        onLoginClick={handleLoginClick}
        onDestinationsClick={handleDestinationsClick}
      />

      <header className="hero py-5">
        <div className="container">
          <h1 className="hero-title display-6 mb-2">Welcome!</h1>
          <p className="hero-subtitle lead mb-0">
            Explore destinations and activities. Sign in to create your
            personalized trip plan within budget.
          </p>
        </div>
      </header>

      <main className="container py-4" id="feed">
        <h2 className="h4 mb-3">Destinations</h2>
        <DestinationsFeed onOpenDestination={handleOpenDestination} />
      </main>

      <footer className="app-footer text-center small">
        © {new Date().getFullYear()} Travel Planner
      </footer>
    </>
  );
}
