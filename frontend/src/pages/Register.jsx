import React from "react";
import { Link, useNavigate } from "react-router-dom";
import { useAuth } from "../context/AuthContext";
import AuthCard from "../components/AuthCard";
import FormInput from "../components/ui/FormInput";
import PrimaryButton from "../components/ui/PrimaryButton";

export default function Register() {
  const nav = useNavigate();
  const { register } = useAuth();
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState("");
  const [form, setForm] = React.useState({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
  });

  const onChange = (e) =>
    setForm((f) => ({ ...f, [e.target.name]: e.target.value }));

  const onSubmit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError("");
    try {
      await register(form);
      nav("/dashboard", { replace: true });
    } catch (err) {
      const api = err?.response?.data;
      const firstError = api?.errors
        ? Object.values(api.errors).flat()[0]
        : api?.message;
      setError(firstError || "Registration failed.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="container py-5">
      <AuthCard
        title="Registration"
        footer={
          <>
            Already have an account? <Link to="/login">Log in</Link>
          </>
        }
      >
        {error && <div className="alert alert-danger">{error}</div>}
        <form onSubmit={onSubmit} noValidate>
          <FormInput
            id="name"
            name="name"
            label="Name"
            placeholder="Your Name"
            value={form.name}
            onChange={onChange}
            required
          />
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
          <FormInput
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            label="Submit Password"
            placeholder="********"
            value={form.password_confirmation}
            onChange={onChange}
            required
          />
          <PrimaryButton disabled={busy} type="submit" className="w-100">
            {busy ? "Creating account..." : "Sign up"}
          </PrimaryButton>
        </form>
      </AuthCard>
    </div>
  );
}
