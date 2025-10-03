import React from "react";
import { Navigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";

export default function ProtectedRoute({ children }) {
  const { isAuth, loading } = useAuth();
  if (loading) return null; // ili spinner
  return isAuth ? children : <Navigate to="/login" replace />;
}
