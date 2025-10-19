import React from "react";
import NavBar from "../components/NavBar";
import DestinationsFeed from "../components/DestinationsFeed";
import { useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function Home() {
  const navigate = useNavigate();
  const { isAuth } = useAuth();

  const scrollToFeed = React.useCallback(() => {
    document
      .getElementById("feed")
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  }, []);

  const [forcePage, setForcePage] = React.useState(1);
  const [resetSignal, setResetSignal] = React.useState(0);

  const handleBrandClick = React.useCallback(() => {
    // vrati na prvu destinaciju i skroluj do feed-a
    setForcePage(1);
    setResetSignal((n) => n + 1); // retrigger i kad je već 1
    scrollToFeed();
  }, [scrollToFeed]);

  return (
    <>
      <NavBar
        onDestinationsClick={scrollToFeed}
        onBrandClick={handleBrandClick}
      />
      <header className="hero py-5">
        <div className="container">
          <p className="hero-subtitle lead mb-0">
            Explore destinations and activities. Plan your perfect trip!
          </p>
        </div>
      </header>
      <main className="container py-4" id="feed">
        <DestinationsFeed
          perPage={1} // broj destinacija po strani
          forcePage={forcePage}
          resetSignal={resetSignal}
        />
      </main>
      <footer className="app-footer text-center small">
        {" "}
        © {new Date().getFullYear()} Travel Planner{" "}
      </footer>
    </>
  );
}
