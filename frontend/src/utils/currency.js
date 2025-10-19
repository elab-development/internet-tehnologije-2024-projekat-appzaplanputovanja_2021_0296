// src/utils/currency.js
export const SUPPORTED = ["EUR", "USD"];

// Bezbedna konverzija iz "base" valute u target preko FX mapa
export function convert(amount, base, target, fx) {
  if (amount == null || isNaN(amount)) return 0;
  if (!fx || base === target) return Number(amount);
  // čuvamo kurseve kao: fx.base="EUR", fx.rates={  USD:1.08, EUR:1 }
  const from = base === fx.base ? 1 : fx.rates?.[base];
  const to = target === fx.base ? 1 : fx.rates?.[target];
  if (!from || !to) return Number(amount);
  // amount (base) -> EUR -> target   (ili obrnuto, zavisno od fx.base)
  const inBase = base === fx.base ? Number(amount) : Number(amount) / from;
  return inBase * to;
}

export function formatCurrency(value, currency) {
  try {
    return new Intl.NumberFormat(undefined, {
      style: "currency",
      currency,
      currencyDisplay: "narrowSymbol", // "$", "€";
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value ?? 0);
  } catch {
    // fallback
    return `${(value ?? 0).toFixed(2)} ${currency}`;
  }
}
