import { useEffect, useState } from 'react';

// Tiny hash router: "#/history/12" -> { page: 'history', param: '12' }.
// Hash routing works on any static host and needs no server rewrite rules.

export const PAGES = ['assistant', 'history', 'knowledge-base', 'dashboard'];

function parseHash() {
  const [page, param] = window.location.hash.replace(/^#\/?/, '').split('/');
  return {
    page: PAGES.includes(page) ? page : 'assistant',
    param: param ? decodeURIComponent(param) : null,
  };
}

export function navigate(page, param) {
  window.location.hash = param ? `/${page}/${encodeURIComponent(param)}` : `/${page}`;
}

export function useHashRoute() {
  const [route, setRoute] = useState(parseHash);
  useEffect(() => {
    const onChange = () => setRoute(parseHash());
    window.addEventListener('hashchange', onChange);
    return () => window.removeEventListener('hashchange', onChange);
  }, []);
  return route;
}
