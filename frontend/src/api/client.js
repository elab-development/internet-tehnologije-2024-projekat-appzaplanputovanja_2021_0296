import axios from "axios";

// CRA koristi process.env.REACT_APP_* , Vite koristi import.meta.env.VITE_*
const fromCRA =
  typeof process !== "undefined"
    ? process.env?.REACT_APP_API_BASE_URL
    : undefined;
/*const fromVite =
  typeof import.meta !== "undefined"
    ? import.meta.env?.VITE_API_BASE_URL
    : undefined;
*/
const API_BASE_URL =
  fromCRA ||
  //fromVite ||
  "http://localhost:8000/api";

export const api = axios.create({
  baseURL: API_BASE_URL,
  headers: { "Content-Type": "application/json" },
});

// Attach Bearer token (ako postoji u localStorage)
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("token");
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
