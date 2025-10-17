import React, { createContext, useContext, useEffect, useState } from "react";
import { AuthApi, getToken } from "../api/client";

const AuthContext = createContext();
export const useAuth = () => useContext(AuthContext);

export function AuthProvider({ children }) {
  const [isAuth, setIsAuth] = useState(false);
  const [loading, setLoading] = useState(true);

  // 1) na startu aplikacije – učitaj token iz localStorage
  useEffect(() => {
    setIsAuth(!!getToken());
    setLoading(false);
  }, []);

  // 2) login – AuthApi.login već setuje token; ovde ažuriramo state
  const login = async (payload) => {
    const res = await AuthApi.login(payload);
    if (res?.user?.name) {
      localStorage.setItem("tp_user_name", res.user.name);
    }
    setIsAuth(!!getToken());
  };

  // 2b) register – AuthApi.register već setuje token; ovde ažuriramo state
  const register = async (payload) => {
    const res = await AuthApi.register(payload);
    if (res?.user?.name) localStorage.setItem("tp_user_name", res.user.name);
    setIsAuth(!!getToken());
  };

  // 3) logout – AuthApi.logout briše token; ovde čistimo state
  const logout = async () => {
    await AuthApi.logout();
    setIsAuth(false);
  };

  return (
    <AuthContext.Provider value={{ isAuth, loading, login, register, logout }}>
      {children}
    </AuthContext.Provider>
  );
}
