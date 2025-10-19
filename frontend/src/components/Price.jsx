import React from "react";
import { useCurrency } from "../context/CurrencyContext";

export default function Price({ amount, from, className }) {
  const { format, baseCurrency } = useCurrency();
  const src = from || baseCurrency; // ako nema prosledjenu 'from', uzmi baznu iz konteksta (EUR)
  return <span className={className}>{format(Number(amount) || 0, src)}</span>;
}
