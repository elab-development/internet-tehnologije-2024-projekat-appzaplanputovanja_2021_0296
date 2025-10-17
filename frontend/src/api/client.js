// src/api/client.js
import axios from "axios";
import { showError, toast } from "./notify";

// ================= Friendly backend codes =================
const FRIENDLY = {
  MANDATORY_VARIANTS_MISSING:
    "No available transport or accommodation variants for the selected parameters.",
  BUDGET_TOO_LOW_FOR_MANDATORY:
    "Update not possible: mandatory transport and/or accommodation would exceed the budget.",
  MANDATORY_OUTSIDE_WINDOW: "Mandatory item is outside the travel period.",
  MANDATORY_OVERLAP: "Mandatory item overlaps with existing schedule.",
  OUTSIDE_TRAVEL_PERIOD: "Activity is outside the travel period.",
  TIME_OVERLAP: "Activity time overlaps with another activity in the plan.",
  LOCATION_MISMATCH:
    "The activity location does not match the travel plan destination.",
  PREFERENCE_MISMATCH: "The activity does not match your selected preferences.",
  BUDGET_EXCEEDED:
    "Budget decrease would make the current plan exceed the budget. Reduce optional activities or set a higher budget.",
  BUDGET_TOO_LOW_AFTER_PAX_CHANGE:
    "Increasing the number of passengers exceeds the available budget.",
  ACCOMMODATION_NOT_SPANNING_STAY:
    "Accommodation must span the entire stay according to check-in/out settings.",
  MANDATORY_MISSING:
    "Mandatory transport or accommodation items are missing. Please increase the budget or adjust the travel dates.",
  BUDGET_TOO_LOW_AFTER_REBALANCE:
    "Budget too low to include mandatory items even after rebalancing optional activities.",
  MANDATORY_VARIANT_NOT_FOUND:
    "Mandatory activity variant not found for selected parameters.",
};

// ================= Base URL =================
const API_BASE_URL =
  process.env.REACT_APP_API_BASE_URL || "http://localhost:8000/api";

// Create axios instance
const api = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

// ================= Token handling =================
export function getToken() {
  return localStorage.getItem("tp_token");
}
export function setToken(token) {
  if (token) {
    localStorage.setItem("tp_token", token);
    api.defaults.headers.Authorization = `Bearer ${token}`;
  }
}
export function clearToken() {
  localStorage.removeItem("tp_token");
  delete api.defaults.headers.Authorization;
}
const existing = localStorage.getItem("tp_token");
if (existing) {
  api.defaults.headers.Authorization = `Bearer ${existing}`;
}

// ================= Interceptors =================

// Request → add Authorization header if token exists
api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response → centralized error handling
api.interceptors.response.use(
  (res) => res,
  async (error) => {
    const { response, config } = error;
    const status = response?.status;
    const data = response?.data ?? {};
    const msg =
      data?.message ||
      error?.message ||
      "A server error occurred. Please try again.";

    // Console hint (can be muted later)
    console.info(
      "[API ERROR]",
      status,
      String(config?.method || "").toUpperCase(),
      config?.url,
      data
    );

    // ---------- 1) Known codes from backend ----------
    if (data?.code && FRIENDLY[data.code]) {
      await showError(FRIENDLY[data.code]);
      return Promise.reject(error);
    }

    // ---------- 2) 422 validation ----------
    if (status === 422) {
      const errs = data?.errors;
      if (errs && typeof errs === "object") {
        const lines = Object.values(errs).flat().filter(Boolean);
        if (lines.length > 0) {
          const html =
            "<ul style='text-align:left'>" +
            lines.map((li) => `<li>${li}</li>`).join("") +
            "</ul>";
          await showError("Please check the following:", html);
          return Promise.reject(error);
        }
      }
      if (data?.message) {
        await showError(data.message);
        return Promise.reject(error);
      }
    }

    // ---------- 3) Safety net for DB CHECK raising 500 ----------
    // Some setups bubble MySQL CHECK up as a 500 with the check name in the text.
    const rawText =
      (typeof data === "string" && data) ||
      data?.message ||
      data?.error ||
      error?.message ||
      "";

    if (status === 500 && /check_total_cost_budget/i.test(rawText)) {
      await showError(
        "Budget decrease would make the current plan exceed the budget. Reduce optional activities or increase the budget."
      );
      return Promise.reject(error);
    }

    // (Optional) PATCH /travel-plans/:id generic fallback if backend still returns 500
    if (
      status === 500 &&
      String(config?.method).toLowerCase() === "patch" &&
      /\/travel-plans\/\d+$/i.test(config?.url || "")
    ) {
      await showError(
        "Update not possible: mandatory transport and/or accommodation would exceed the budget. Increase the budget or shorten the travel period."
      );
      return Promise.reject(error);
    }

    // ---------- 4) Common statuses ----------
    switch (status) {
      case 400:
        await showError("Bad request.");
        break;
      case 401:
        await showError("You are not authenticated.");
        break;
      case 403:
        await showError("You do not have permission to perform this action.");
        break;
      case 404:
        await showError("Resource not found.");
        break;
      case 405:
        await showError("HTTP method not allowed.");
        break;
      case 409:
        await showError(
          msg || "Conflict detected. Please review your changes."
        );
        break;
      case 500:
        await showError("A server error occurred. Please try again.");
        break;
      default: {
        // ---------- 5) SQLSTATE / raw DB message fallback ----------
        const raw = data?.error || data?.exception || "";
        const looksLikeSql =
          typeof raw === "string" && raw.includes("SQLSTATE");
        if (looksLikeSql) {
          await showError(
            "A database constraint was violated. Please review your input."
          );
        } else if (data?.message) {
          await showError(data.message);
        } else {
          await showError("A server error occurred. Please try again.");
        }
      }
    }

    return Promise.reject(error);
  }
);

// ================= Auth API calls =================
export const AuthApi = {
  async login({ email, password }) {
    const { data } = await api.post("/auth/login", { email, password });
    if (data?.token) setToken(data.token);
    toast("Logged in successfully!", "success");
    return data;
  },

  async register(payload) {
    const { data } = await api.post("/auth/register", payload);
    if (data?.token) setToken(data.token);
    toast("Account created!", "success");
    return data;
  },

  async logout() {
    try {
      await api.post("/auth/logout");
    } finally {
      clearToken();
      toast("Logged out.", "info");
    }
  },
};

// ================= Helpers =================
export async function apiGet(url, config) {
  const { data } = await api.get(url, config);
  return data;
}
export async function apiPost(url, body, config) {
  const { data } = await api.post(url, body, config);
  return data;
}
export async function apiPut(url, body, config) {
  const { data } = await api.put(url, body, config);
  return data;
}
export async function apiDelete(url, config) {
  const { data } = await api.delete(url, config);
  return data;
}

export default api;
