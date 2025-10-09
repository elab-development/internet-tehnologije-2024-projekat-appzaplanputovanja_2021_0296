import React from "react";
import { Routes, Route } from "react-router-dom";
import Home from "./pages/Home";
import Login from "./pages/Login";
import Register from "./pages/Register";
import Dashboard from "./pages/Dashboard";
import ProtectedRoute from "./components/ProtectedRoute";
import CreateTravelPlan from "./pages/CreateTravelPlan";
import ShowTravelPlan from "./pages/ShowTravelPlan";
import EditTravelPlan from "./pages/EditTravelPlan";

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<Home />} />
      <Route path="/login" element={<Login />} />
      <Route path="/register" element={<Register />} />
      <Route
        path="/dashboard"
        element={
          <ProtectedRoute>
            <Dashboard />
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/create"
        element={
          <ProtectedRoute>
            <CreateTravelPlan />
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/plans/:id"
        element={
          <ProtectedRoute>
            <ShowTravelPlan />
          </ProtectedRoute>
        }
      />
      <Route path="/dashboard/plans/:id/edit" element={<EditTravelPlan />} />
      <Route path="*" element={<Home />} />
    </Routes>
  );
}
