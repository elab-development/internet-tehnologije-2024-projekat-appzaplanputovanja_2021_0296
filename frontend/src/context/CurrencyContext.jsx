// src/context/CurrencyContext.jsx
import React from "react";
import useFxRates from "../hooks/useFxRates";
import { SUPPORTED, convert, formatCurrency } from "../utils/currency";

const CurrencyCtx = React.createContext(null);

export function CurrencyProvider({
  children,
  defaultCurrency = "EUR",
  baseCurrency = "EUR",
}) {
  const saved = localStorage.getItem("ui_currency");
  const initial = saved && SUPPORTED.includes(saved) ? saved : defaultCurrency;
  const [currency, setCurrency] = React.useState(initial);

  const { loading, error, fx, reload } = useFxRates(24);

  React.useEffect(() => {
    localStorage.setItem("ui_currency", currency);
  }, [currency]);

  const value = React.useMemo(() => {
    function convertAmount(amount, from = baseCurrency) {
      return convert(amount, from, currency, fx);
    }
    function format(amount, from = baseCurrency) {
      return formatCurrency(convertAmount(amount, from), currency);
    }
    return {
      loading,
      error,
      reload,
      currency,
      setCurrency,
      supported: SUPPORTED,
      baseCurrency,
      fx,
      convertAmount,
      format,
    };
  }, [currency, fx, loading, error, baseCurrency, reload]);

  return <CurrencyCtx.Provider value={value}>{children}</CurrencyCtx.Provider>;
}

export function useCurrency() {
  const ctx = React.useContext(CurrencyCtx);
  if (!ctx)
    throw new Error("useCurrency must be used within <CurrencyProvider>");
  return ctx;
}
