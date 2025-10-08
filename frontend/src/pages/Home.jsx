import React from "react";
import NavBar from "../components/NavBar";
import DestinationsFeed from "../components/DestinationsFeed";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function Home() {
  const scrollToFeed = () => {
    document.getElementById("feed")?.scrollIntoView({ behavior: "smooth" });
  };

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
      <NavBar onDestinationsClick={scrollToFeed} />
      <header className="hero py-5">
        <div className="container">
          <p className="hero-subtitle lead mb-0">
            Explore destinations and activities. Plan your perfect trip!
          </p>
        </div>
      </header>
      <main className="container py-4" id="feed">
        <DestinationsFeed onOpenDestination={handleOpenDestination} />
      </main>
      <footer className="app-footer text-center small">
        © {new Date().getFullYear()} Travel Planner
      </footer>
    </>
  );
}
