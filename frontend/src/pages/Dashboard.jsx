import React from "react";
import { useAuth } from "../context/AuthContext";

export default function Dashboard() {
  const { user } = useAuth();
  return (
    <div className="container py-4">
      <h1 className="h4 mb-3">Welcome{user?.name ? `, ${user.name}` : ""}!</h1>
      <p className="text-muted">
        Here you will see your generated plan, list of plans, etc.
      </p>
    </div>
  );
}
