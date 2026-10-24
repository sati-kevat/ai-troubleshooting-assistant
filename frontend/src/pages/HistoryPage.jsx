import { useEffect, useId, useState } from 'react';
import { api } from '../api/client.js';
import AnswerPanel from '../components/assistant/AnswerPanel.jsx';
import OutcomeActions from '../components/assistant/OutcomeActions.jsx';
import { ConfidenceBadge, SampleBadge, StatusBadge } from '../components/common/Badges.jsx';
import Pagination from '../components/common/Pagination.jsx';
import { EmptyState, ErrorMessage, LoadingState } from '../components/common/States.jsx';
import { useApi } from '../hooks/useApi.js';
import { navigate } from '../hooks/useHashRoute.js';
import { formatDateTime, formatRelative, STATUS_LABELS, truncate } from '../utils/format.js';

function QueryDetail({ id }) {
  const { data, error, loading, reload, setData } = useApi((options) => api.query(id, options), [id]);

  if (loading && !data) return <LoadingState label="Loading query…" />;
  if (error) return <ErrorMessage error={error} onRetry={error.status === 404 ? undefined : reload} title="Could not load this query" />;
  if (!data) return null;

  const onUpdated = (updated) => setData((previous) => ({ ...previous, ...updated, response: previous.response }));
  const response = data.response;

  return (
    <div className="detail">
      <div className="card detail__meta">
        <div className="detail__meta-row">
          <h2 className="answer__title">Query #{data.id}</h2>
          {data.is_sample && <SampleBadge />}
        </div>
        <dl className="meta-list">
          <div><dt>Submitted</dt><dd>{formatDateTime(data.created_at)}</dd></div>
          <div><dt>Category</dt><dd>{data.category?.name || 'Uncategorised'}</dd></div>
          <div><dt>Status</dt><dd><StatusBadge status={data.status} /></dd></div>
          <div><dt>Last outcome update</dt><dd>{formatDateTime(data.status_updated_at)}</dd></div>
        </dl>
        {data.status_note && <p className="small"><strong>Outcome note:</strong> {data.status_note}</p>}
        <p className="detail__issue"><strong>Reported issue:</strong> {data.issue_text}</p>
      </div>

      {response && !response.sample && response.answer ? (
        <AnswerPanel
          result={{ ...response, query_id: data.id, status: data.status, created_at: data.created_at }}
          onStatusChange={onUpdated}
        />
      ) : (
        <div className="card">
          <p className="muted">{response?.note || 'No stored answer is available for this record.'}</p>
          <OutcomeActions queryId={data.id} status={data.status} onUpdated={onUpdated} />
        </div>
      )}
    </div>
  );
}

function QueryList({ categories }) {
  const [filters, setFilters] = useState({ status: '', category: '', search: '', include_sample: true });
  const [searchInput, setSearchInput] = useState('');
  const [page, setPage] = useState(1);
  const ids = { status: useId(), category: useId(), search: useId() };

  // Debounce typing in the search box.
  useEffect(() => {
    const timer = setTimeout(() => {
      setFilters((previous) => (previous.search === searchInput.trim() ? previous : { ...previous, search: searchInput.trim() }));
      setPage(1);
    }, 350);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const { data, error, loading, reload } = useApi(
    (options) => api.queries({ ...filters, include_sample: filters.include_sample ? 1 : 0, page, per_page: 10 }, options),
    [filters, page],
  );

  function updateFilter(key, value) {
    setFilters((previous) => ({ ...previous, [key]: value }));
    setPage(1);
  }

  const hasFilters = filters.status || filters.category || filters.search;

  return (
    <>
      <div className="card filters" role="search">
        <div className="field field--inline">
          <label htmlFor={ids.search} className="field__label field__label--small">Search</label>
          <input id={ids.search} type="search" className="input" placeholder="Search issue text…" value={searchInput} onChange={(event) => setSearchInput(event.target.value)} />
        </div>
        <div className="field field--inline">
          <label htmlFor={ids.status} className="field__label field__label--small">Status</label>
          <select id={ids.status} className="select" value={filters.status} onChange={(event) => updateFilter('status', event.target.value)}>
            <option value="">All statuses</option>
            {Object.entries(STATUS_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
          </select>
        </div>
        <div className="field field--inline">
          <label htmlFor={ids.category} className="field__label field__label--small">Category</label>
          <select id={ids.category} className="select" value={filters.category} onChange={(event) => updateFilter('category', event.target.value)}>
            <option value="">All categories</option>
            {(categories || []).map((item) => <option key={item.slug} value={item.slug}>{item.name}</option>)}
            <option value="uncategorised">Uncategorised</option>
          </select>
        </div>
        <label className="checkbox">
          <input type="checkbox" checked={filters.include_sample} onChange={(event) => updateFilter('include_sample', event.target.checked)} />
          Include sample records
        </label>
        <button type="button" className="button button--ghost button--small" onClick={reload} aria-label="Refresh list">Refresh</button>
      </div>

      <ErrorMessage error={error} onRetry={reload} title="Could not load query history" />
      {loading && !data && <LoadingState label="Loading query history…" />}

      {data && data.items.length === 0 && (
        <EmptyState
          title={hasFilters ? 'No queries match these filters' : 'No support queries yet'}
          action={!hasFilters && <button type="button" className="button button--primary" onClick={() => navigate('assistant')}>Submit the first issue</button>}
        >
          {hasFilters ? 'Try clearing the search or choosing a different status or category.' : 'Queries you submit in the assistant are saved here with their outcome.'}
        </EmptyState>
      )}

      {data && data.items.length > 0 && (
        <div className={`card table-card ${loading ? 'is-refreshing' : ''}`}>
          <table className="table">
            <caption className="visually-hidden">Support query history</caption>
            <thead>
              <tr>
                <th scope="col">Query</th>
                <th scope="col">Issue</th>
                <th scope="col">Category</th>
                <th scope="col">Confidence</th>
                <th scope="col">Status</th>
                <th scope="col">Submitted</th>
              </tr>
            </thead>
            <tbody>
              {data.items.map((item) => (
                <tr key={item.id}>
                  <td data-label="Query">
                    <button type="button" className="link-button" onClick={() => navigate('history', item.id)}>#{item.id}</button>
                    {item.is_sample && <> <SampleBadge /></>}
                  </td>
                  <td data-label="Issue" className="table__issue">
                    <span title={item.issue_text}>
                      {truncate(item.issue_text, 110)}
                      {item.top_article && <span className="source-tag">{item.top_article}</span>}
                    </span>
                  </td>
                  <td data-label="Category">{item.category?.name || <span className="muted">Uncategorised</span>}</td>
                  <td data-label="Confidence"><ConfidenceBadge level={item.confidence} /></td>
                  <td data-label="Status"><StatusBadge status={item.status} /></td>
                  <td data-label="Submitted"><span title={formatDateTime(item.created_at)}>{formatRelative(item.created_at)}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
          <Pagination pagination={data.pagination} onPageChange={setPage} />
        </div>
      )}
    </>
  );
}

export default function HistoryPage({ categories, queryId }) {
  return (
    <div className="page">
      <header className="page__header">
        <div>
          <h1 className="page__title">{queryId ? 'Query details' : 'Query history'}</h1>
          <p className="page__subtitle">
            {queryId ? 'The stored answer, its sources and the recorded outcome.' : 'Every submitted issue with its category, confidence and current outcome, stored in MySQL.'}
          </p>
        </div>
        {queryId && <button type="button" className="button button--secondary" onClick={() => navigate('history')}>Back to history</button>}
      </header>
      {queryId ? <QueryDetail id={queryId} /> : <QueryList categories={categories} />}
    </div>
  );
}
