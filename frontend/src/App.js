import React from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import Home from "./pages/Home";
import Login from "./pages/Login";
import Register from "./pages/Register";
import Dashboard from "./pages/Dashboard";
import ProtectedRoute from "./components/ProtectedRoute";
import CreateTravelPlan from "./pages/CreateTravelPlan";
import ShowTravelPlan from "./pages/ShowTravelPlan";
import EditTravelPlan from "./pages/EditTravelPlan";
import Layout from "./components/Layout";

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
            <Layout>
              <Dashboard />
            </Layout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/create"
        element={
          <ProtectedRoute>
            <Layout>
              <CreateTravelPlan />
            </Layout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/plans/:id"
        element={
          <ProtectedRoute>
            <Layout>
              <ShowTravelPlan />
            </Layout>
          </ProtectedRoute>
        }
      />
      <Route
        path="/dashboard/plans/:id/edit"
        element={
          <ProtectedRoute>
            <Layout>
              <EditTravelPlan />
            </Layout>
          </ProtectedRoute>
        }
      />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
