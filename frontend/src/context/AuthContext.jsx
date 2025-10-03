import React from "react";
import { AuthApi, getToken, clearToken } from "../api/client";

const AuthContext = React.createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = React.useState(null);
  const [loading, setLoading] = React.useState(true);

  // (opciono) ovde biste mogli da pogodite /api/user ako to backend podržava.
  React.useEffect(() => {
    const t = getToken();
    if (!t) {
      setLoading(false);
      return;
    }
    // Minimalno: znamo da postoji token -> user je "pseudo-auth"
    setUser({ email: "current" });
    setLoading(false);
  }, []);

  const login = async (credentials) => {
    const data = await AuthApi.login(credentials);
    setUser(data?.user ?? { email: credentials.email });
    return data;
  };

  const register = async (payload) => {
    const data = await AuthApi.register(payload);
    setUser(data?.user ?? { email: payload.email });
    return data;
  };

  const logout = async () => {
    await AuthApi.logout();
    clearToken();
    setUser(null);
  };

  const value = { user, loading, login, register, logout, isAuth: !!user };
  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  return React.useContext(AuthContext);
}
