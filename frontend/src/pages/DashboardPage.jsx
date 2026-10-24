import { useState } from 'react';
import { api } from '../api/client.js';
import { SampleBadge, StatusBadge } from '../components/common/Badges.jsx';
import Icon from '../components/common/Icon.jsx';
import { EmptyState, ErrorMessage, LoadingState } from '../components/common/States.jsx';
import { CategoryBarChart, DailyActivityChart, StatTile, StatusBreakdown } from '../components/dashboard/Charts.jsx';
import { useApi } from '../hooks/useApi.js';
import { navigate } from '../hooks/useHashRoute.js';
import { CONFIDENCE_LABELS, formatDateTime, formatNumber, formatRelative, truncate } from '../utils/format.js';

export default function DashboardPage() {
  const [includeSample, setIncludeSample] = useState(true);
  const { data, error, loading, reload } = useApi(
    (options) => api.analytics({ include_sample: includeSample ? 1 : 0, days: 14 }, options),
    [includeSample],
  );

  const totals = data?.totals;

  return (
    <div className="page">
      <header className="page__header">
        <div>
          <h1 className="page__title">Analytics dashboard</h1>
          <p className="page__subtitle">Calculated live from the support_queries table in MySQL each time the page loads.</p>
        </div>
        <div className="page__actions">
          <label className="checkbox">
            <input type="checkbox" checked={includeSample} onChange={(event) => setIncludeSample(event.target.checked)} />
            Include sample records
          </label>
          <button type="button" className="button button--secondary button--small" onClick={reload} disabled={loading}>
            <Icon name="refresh" size={14} /> {loading ? 'Refreshing…' : 'Refresh'}
          </button>
        </div>
      </header>

      <ErrorMessage error={error} onRetry={reload} title="Could not load analytics" />
      {loading && !data && <LoadingState label="Calculating metrics…" />}

      {data && totals.sample_records > 0 && (
        <div className="alert alert--warning">
          <Icon name="info" size={18} />
          <div className="alert__body">
            <p>
              <strong>Includes {totals.sample_records} fictional sample record{totals.sample_records === 1 ? '' : 's'}</strong> imported from
              {' '}<code>03_sample_queries.sql</code>. Untick "Include sample records" to see only real queries.
            </p>
          </div>
        </div>
      )}

      {data && totals.total === 0 && (
        <EmptyState
          title="No queries to analyse yet"
          action={<button type="button" className="button button--primary" onClick={() => navigate('assistant')}>Open the assistant</button>}
        >
          Metrics appear here once support queries are submitted{includeSample ? '' : ' (sample records are currently excluded)'}.
        </EmptyState>
      )}

      {data && totals.total > 0 && (
        <div className={loading ? 'is-refreshing' : ''}>
          <section className="stat-grid" aria-label="Key metrics">
            <StatTile label="Total support queries" value={formatNumber(totals.total)} detail={`Updated ${formatDateTime(data.generated_at)}`} tone="hero" />
            <StatTile label="Resolved" value={formatNumber(totals.resolved)} detail="Confirmed by the agent" tone="resolved" />
            <StatTile label="Unresolved" value={formatNumber(totals.unresolved)} detail="Steps did not fix the issue" tone="unresolved" />
            <StatTile label="Escalated" value={formatNumber(totals.escalated)} detail="Handed over to L2 / other teams" tone="escalated" />
            <StatTile label="Awaiting outcome" value={formatNumber(totals.open)} detail="Status still open" tone="open" />
            <StatTile
              label="Resolution rate"
              value={data.resolution_rate === null ? '–' : `${data.resolution_rate}%`}
              detail={data.with_outcome ? `Resolved ÷ ${data.with_outcome} queries with an outcome` : 'No outcomes recorded yet'}
            />
          </section>

          <div className="dashboard-grid">
            <section className="card chart-card">
              <h2 className="card__title">Outcome breakdown</h2>
              <p className="card__subtitle">Current status of all {formatNumber(totals.total)} queries</p>
              <StatusBreakdown totals={totals} />
              <p className="small muted chart-card__footnote">
                The assistant recommended escalation for {formatNumber(totals.escalation_recommended)} quer{totals.escalation_recommended === 1 ? 'y' : 'ies'}
                {' '}(low or no knowledge-base support).
              </p>
            </section>

            <section className="card chart-card">
              <h2 className="card__title">Queries by issue category</h2>
              <p className="card__subtitle">Hover or focus a bar for its outcome breakdown</p>
              <CategoryBarChart rows={data.by_category} />
            </section>

            <section className="card chart-card chart-card--wide">
              <h2 className="card__title">Recent query activity</h2>
              <p className="card__subtitle">Queries submitted per day, last {data.daily.length} days</p>
              <DailyActivityChart series={data.daily} />
            </section>

            <section className="card chart-card">
              <h2 className="card__title">Answer confidence</h2>
              <p className="card__subtitle">How well the knowledge base covered each query</p>
              <ul className="metric-list">
                {Object.entries(data.by_confidence).map(([level, count]) => (
                  <li key={level}><span>{CONFIDENCE_LABELS[level]}</span><b>{formatNumber(count)}</b></li>
                ))}
              </ul>
              <p className="small muted chart-card__footnote">
                Answer mode: {formatNumber(totals.demo_answers)} demo (no AI call), {formatNumber(totals.ai_answers)} AI-generated.
              </p>
            </section>

            <section className="card chart-card">
              <h2 className="card__title">Most-used articles</h2>
              <p className="card__subtitle">Primary source of the answer</p>
              {data.top_articles.length ? (
                <ol className="metric-list metric-list--numbered">
                  {data.top_articles.map((article) => (
                    <li key={article.code}>
                      <button type="button" className="link-button" onClick={() => navigate('knowledge-base', article.code)}>
                        {article.code}
                      </button>
                      <span className="metric-list__text">{truncate(article.title, 48)}</span>
                      <b>{article.total}</b>
                    </li>
                  ))}
                </ol>
              ) : <p className="muted">No article has been cited yet.</p>}
            </section>

            <section className="card chart-card chart-card--wide">
              <h2 className="card__title">Latest queries</h2>
              <ul className="activity-list">
                {data.recent.map((item) => (
                  <li key={item.id} className="activity">
                    <button type="button" className="link-button" onClick={() => navigate('history', item.id)}>#{item.id}</button>
                    <span className="activity__text">
                      {truncate(item.summary, 100)}
                      <span className="small muted"> · {item.category || 'Uncategorised'} · {formatRelative(item.created_at)}</span>
                    </span>
                    {item.is_sample && <SampleBadge />}
                    <StatusBadge status={item.status} />
                  </li>
                ))}
              </ul>
            </section>
          </div>
        </div>
      )}
    </div>
  );
}
