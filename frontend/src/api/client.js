// src/api/client.js
import axios from "axios";

// Baza za API – ako nema vrednosti u .env, koristi localhost
const API_BASE_URL =
  process.env.REACT_APP_API_BASE_URL || "http://localhost:8000/api";

// Kreiramo axios instancu
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
// ================= Interceptor =================
// Dodaj Authorization header ako postoji token
api.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// ================= Auth API pozivi =================
export const AuthApi = {
  async login({ email, password }) {
    const { data } = await api.post("/auth/login", { email, password });
    if (data?.token) setToken(data.token);
    return data;
  },

  async register(payload) {
    const { data } = await api.post("/auth/register", payload);
    if (data?.token) setToken(data.token);
    return data;
  },

  async logout() {
    try {
      await api.post("/auth/logout");
    } finally {
      clearToken();
    }
  },
};

export { api };
