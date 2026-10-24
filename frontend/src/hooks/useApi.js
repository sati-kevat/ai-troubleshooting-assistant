import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Runs an API call when `deps` change and tracks loading / error / data.
 * Earlier in-flight requests are aborted so stale responses never win.
 */
export function useApi(fetcher, deps) {
  const [state, setState] = useState({ data: null, error: null, loading: true });
  const [reloadKey, setReloadKey] = useState(0);
  const fetcherRef = useRef(fetcher);
  fetcherRef.current = fetcher;

  useEffect(() => {
    const controller = new AbortController();
    setState((previous) => ({ ...previous, loading: true, error: null }));
    fetcherRef.current({ signal: controller.signal })
      .then((data) => setState({ data, error: null, loading: false }))
      .catch((error) => {
        if (!controller.signal.aborted) setState((previous) => ({ ...previous, error, loading: false }));
      });
    return () => controller.abort();
  }, [...deps, reloadKey]);

  const reload = useCallback(() => setReloadKey((key) => key + 1), []);
  const setData = useCallback((updater) => setState((previous) => ({
    ...previous,
    data: typeof updater === 'function' ? updater(previous.data) : updater,
  })), []);

  return { ...state, reload, setData };
}
