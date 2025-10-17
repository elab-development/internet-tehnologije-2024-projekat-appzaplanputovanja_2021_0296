import React from "react";
import api from "../api/client";

const TTL = 10 * 60 * 1000; // 10 min
const LS_KEY = "dest_feed_cache_v1";

export default function useDestinationsFeed(perPage = 12) {
  const [state, setState] = React.useState({
    loading: true,
    error: "",
    pages: {}, // page -> { locations, cards }
    current: 1,
    last: 1,
  });

  const loadPage = React.useCallback(
    async (page = 1) => {
      setState((s) => ({ ...s, loading: true, error: "" }));
      try {
        // proba LS
        const cache = JSON.parse(localStorage.getItem(LS_KEY) || "null");
        if (cache && Date.now() - cache.savedAt < TTL && cache.pages?.[page]) {
          setState({
            loading: false,
            error: "",
            pages: cache.pages,
            current: page,
            last: cache.last,
          });
          // tihi revalidate niže
        }

        const { data } = await api.get(
          `/destinations-feed?page=${page}&perPage=${perPage}`
        );
        const pageData = {
          locations: data.data.locations,
          cards: data.data.cards,
        };
        const nextPages = { ...(cache?.pages || {}), [page]: pageData };

        const next = {
          pages: nextPages,
          current: data.meta.current_page,
          last: data.meta.last_page,
        };
        setState({ loading: false, error: "", ...next });
        localStorage.setItem(
          LS_KEY,
          JSON.stringify({ savedAt: Date.now(), ...next })
        );
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
