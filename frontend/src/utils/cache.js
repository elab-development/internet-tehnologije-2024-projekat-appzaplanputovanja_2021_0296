// src/utils/cache.js
export function cacheGet(key) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    const { value, exp } = JSON.parse(raw);
    if (exp && Date.now() > exp) {
      localStorage.removeItem(key);
      return null;
    }
    return value;
  } catch {
    return null;
  }
}
export function cacheSet(key, value, ttlMs = 24 * 60 * 60 * 1000) {
  const exp = ttlMs ? Date.now() + ttlMs : null;
  localStorage.setItem(key, JSON.stringify({ value, exp }));
}
