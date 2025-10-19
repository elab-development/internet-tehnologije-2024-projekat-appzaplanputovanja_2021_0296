// src/components/CostByDayChart.jsx
import React from "react";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  ResponsiveContainer,
  CartesianGrid,
} from "recharts";
import { useCurrency } from "../context/CurrencyContext";

// robustno uzmi YYYY-MM-DD (radi i kad je "2025-12-22 11:51:00")
function toDateKey(s) {
  if (!s) return "n/a";
  const str = String(s).trim();
  const iso = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
  // fallback
  return str.slice(0, 10);
}

/**
 * Prikaz troškova po danu, izbacuje transport i smeštaj.
 * @param {Array} items - stavke plana (sa poljima amount, time_from, activity.type)
 * @param {Array} excludeTypes - niz tipova koje isključujemo (case-insensitive)
 */
export default function CostByDayChart({
  items,
  excludeTypes = ["Transport", "Accommodation"],
  height = 280,
}) {
  const { format } = useCurrency();
  const skip = new Set(excludeTypes.map((t) => String(t).toLowerCase()));
  const map = new Map();

  (items || []).forEach((it) => {
    const type = String(it?.activity?.type || "").toLowerCase();
    if (skip.has(type)) return; // preskoči transport & accommodation

    const amount = Number(it?.amount) || 0;
    if (amount <= 0) return;

    const day = toDateKey(it?.time_from) || "n/a";
    map.set(day, (map.get(day) || 0) + amount);
  });

  const data = Array.from(map.entries()).map(([name, value]) => ({
    name,
    value,
  }));

  const yTick = (v) => format(v); // format sa tekućom valutom iz aplikacije
  const tooltipFmt = (value) => [format(value), "Total"];

  return (
    <div className="rounded border p-2 bg-white" style={{ height }}>
      <div className="d-flex justify-content-between align-items-center mb-2">
        <strong className="small mb-0">
          Cost by day (excluding transport & accommodation)
        </strong>
      </div>
      <ResponsiveContainer width="100%" height="90%">
        <BarChart data={data}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey="name" tick={{ fontSize: 12 }} />
          <YAxis tick={{ fontSize: 12 }} tickFormatter={yTick} />
          <Tooltip formatter={tooltipFmt} />
          <Bar dataKey="value" />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
}
