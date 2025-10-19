// src/hooks/useDestinationsFeed.js
import React from "react";
import api from "../api/client";

const TTL = 24 * 60 * 60 * 1000; // 24h cache
export default function useDestinationsFeed(perPage = 12) {
  const LS_KEY = `dest_feed_cache_v2_pp${perPage}`;

  const [state, setState] = React.useState({
    loading: true,
    error: "",
    pages: {}, // { [page]: { locations, cards } }
    current: 1,
    last: 1,
  });

  // mali in-memory cache za tekuću sesiju
  const mem = React.useRef({ savedAt: 0, pages: {}, last: 1 });

  const readLS = () => {
    try {
      return JSON.parse(localStorage.getItem(LS_KEY) || "null");
    } catch {
      return null;
    }
  };

  const writeLS = (payload) => {
    try {
      localStorage.setItem(
        LS_KEY,
        JSON.stringify({ savedAt: Date.now(), ...payload })
      );
    } catch {}
  };

  const setFromCache = (page, cache) => {
    setState((s) => ({
      ...s,
      loading: false,
      error: "",
      pages: cache.pages,
      current: page,
      last: cache.last,
    }));
  };

  const fetchPage = async (page, { silent = false } = {}) => {
    if (!silent) setState((s) => ({ ...s, loading: true, error: "" }));

    const { data } = await api.get(
      `/destinations-feed?page=${page}&perPage=${perPage}`
    );

    const pageData = {
      locations: data.data.locations,
      cards: data.data.cards,
    };

    // spoji sa postojećim
    const nextPages = { ...(mem.current.pages || {}), [page]: pageData };
    const next = {
      pages: nextPages,
      current: data.meta.current_page,
      last: data.meta.last_page,
    };

    // update mem i LS
    mem.current = { savedAt: Date.now(), pages: nextPages, last: next.last };
    writeLS({ pages: nextPages, last: next.last });

    if (!silent)
      setState((s) => ({ ...s, loading: false, error: "", ...next }));

    return next;
  };

  // prefetch sledeće strane (tiho, bez menjanja current state-a)
  const prefetchNext = async (current, last) => {
    const nextPage = current + 1;
    if (nextPage <= last && !mem.current.pages[nextPage]) {
      try {
        await fetchPage(nextPage, { silent: true });
      } catch {}
    }
  };

  const loadPage = React.useCallback(
    async (page = 1) => {
      // 1) probaj mem cache
      const memValid =
        mem.current.savedAt &&
        Date.now() - mem.current.savedAt < TTL &&
        mem.current.pages?.[page];

      if (memValid) {
        setFromCache(page, {
          pages: mem.current.pages,
          last: mem.current.last,
        });
      } else {
        // 2) probaj LS cache
        const ls = readLS();
        const lsValid = ls && Date.now() - ls.savedAt < TTL && ls.pages?.[page];

        if (lsValid) {
          mem.current = { savedAt: ls.savedAt, pages: ls.pages, last: ls.last };
          setFromCache(page, ls);
        } else {
          setState((s) => ({ ...s, loading: true, error: "" }));
        }
      }

      // 3) uvek revalidiraj sa servera (SWR)
      try {
        const next = await fetchPage(page, { silent: memValid || !!readLS() });
        // 4) čim imamo aktuelne meta podatke, prefetch-uj sledeću
        prefetchNext(next.current, next.last);
      } catch (e) {
        setState((s) => ({
          ...s,
          loading: false,
          error: "Failed to load destinations.",
        }));
      }
    },
    [perPage]
  );

  return {
    loading: state.loading,
    error: state.error,
    currentPage: state.current,
    lastPage: state.last,
    getPage: (p) => state.pages[p],
    loadPage,
  };
}
