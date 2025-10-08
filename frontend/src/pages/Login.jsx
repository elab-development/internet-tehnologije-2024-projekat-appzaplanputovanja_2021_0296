import React from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import { useEffect } from "react";
import AuthCard from "../components/AuthCard";
import FormInput from "../components/ui/FormInput";
import PrimaryButton from "../components/ui/PrimaryButton";

export default function Login() {
  const nav = useNavigate();
  const location = useLocation();
  const { login, isAuth } = useAuth();
  const [form, setForm] = React.useState({ email: "", password: "" });
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState("");

  const onChange = (e) =>
    setForm((f) => ({ ...f, [e.target.name]: e.target.value }));

  const onSubmit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      const from = location.state?.from?.pathname || "/dashboard";
      await login(form);
      nav(from, { replace: true });
    } catch (err) {
      setError(
        err?.response?.data?.message ||
          "Login failed. Please check your data and try again."
      );
    } finally {
      setBusy(false);
    }
  };

  useEffect(() => {
    if (isAuth) nav("/dashboard", { replace: true });
  }, [isAuth, nav]);

  return (
    <div className="container py-5">
      <AuthCard
        title="Login"
        footer={
          <>
            Don't have an account? <Link to="/register">Register</Link>
          </>
        }
      >
        {error && <div className="alert alert-danger">{error}</div>}
        <form onSubmit={onSubmit} noValidate>
          <FormInput
            id="email"
            name="email"
            type="email"
            label="Email"
            placeholder="you@example.com"
            value={form.email}
            onChange={onChange}
            required
          />
          <FormInput
            id="password"
            name="password"
            type="password"
            label="Password"
            placeholder="********"
            value={form.password}
            onChange={onChange}
            required
          />
          <PrimaryButton disabled={busy} type="submit" className="w-100">
            {busy ? "Logging in..." : "Log in"}
          </PrimaryButton>
        </form>
      </AuthCard>
    </div>
  );
}
