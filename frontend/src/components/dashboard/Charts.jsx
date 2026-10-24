import { useEffect, useRef, useState } from 'react';
import { formatNumber, formatShortDate, STATUS_LABELS } from '../../utils/format.js';

const STATUS_ORDER = ['resolved', 'unresolved', 'escalated', 'open'];

/** Rounds an axis maximum up to a clean value (1, 2, 5, 10, 20, 50 …). */
function niceMax(value) {
  if (value <= 5) return Math.max(1, Math.ceil(value));
  const magnitude = 10 ** Math.floor(Math.log10(value));
  const step = [1, 2, 5, 10].find((candidate) => candidate * magnitude >= value) * magnitude;
  return step;
}

function percent(value, total) {
  return total > 0 ? Math.round((value / total) * 100) : 0;
}

export function StatTile({ label, value, detail, tone }) {
  return (
    <div className={`stat ${tone ? `stat--${tone}` : ''}`}>
      <p className="stat__label">{label}</p>
      <p className="stat__value">{value}</p>
      {detail && <p className="stat__detail">{detail}</p>}
    </div>
  );
}

function DataTable({ caption, columns, rows }) {
  return (
    <details className="data-table">
      <summary>Show data table</summary>
      <table className="table table--compact">
        <caption className="visually-hidden">{caption}</caption>
        <thead><tr>{columns.map((column) => <th key={column} scope="col">{column}</th>)}</tr></thead>
        <tbody>{rows.map((row) => <tr key={row[0]}>{row.map((cell, index) => <td key={index}>{cell}</td>)}</tr>)}</tbody>
      </table>
    </details>
  );
}

/** Horizontal bars: queries per category. Hover or focus a row for its status breakdown. */
export function CategoryBarChart({ rows }) {
  const visible = rows.filter((row) => row.total > 0);
  if (!visible.length) return <p className="muted">No queries recorded yet.</p>;
  const max = Math.max(...visible.map((row) => row.total));

  return (
    <>
      <ul className="hbar-chart">
        {visible.map((row) => (
          <li key={row.slug} className="hbar" tabIndex={0} aria-label={`${row.name}: ${row.total} queries`}>
            <span className="hbar__label">{row.name}</span>
            <span className="hbar__track">
              <span className="hbar__bar" style={{ width: `${(row.total / max) * 100}%` }} />
              <span className="hbar__value">{formatNumber(row.total)}</span>
            </span>
            <span className="tooltip" role="tooltip">
              <strong>{row.name}</strong>
              {STATUS_ORDER.map((status) => (
                <span key={status} className="tooltip__row">
                  <span className={`swatch swatch--${status}`} aria-hidden="true" />{STATUS_LABELS[status]}<b>{row[status]}</b>
                </span>
              ))}
            </span>
          </li>
        ))}
      </ul>
      <DataTable
        caption="Queries by category"
        columns={['Category', 'Total', 'Resolved', 'Unresolved', 'Escalated', 'Open']}
        rows={visible.map((row) => [row.name, row.total, row.resolved, row.unresolved, row.escalated, row.open])}
      />
    </>
  );
}

/** One stacked bar of query outcomes with a legend that carries labels and counts. */
export function StatusBreakdown({ totals }) {
  const total = totals.total;
  if (!total) return <p className="muted">No queries recorded yet.</p>;
  return (
    <div className="status-breakdown">
      <div className="stacked-bar" role="img" aria-label={STATUS_ORDER.map((status) => `${STATUS_LABELS[status]} ${totals[status]}`).join(', ')}>
        {STATUS_ORDER.filter((status) => totals[status] > 0).map((status) => (
          <span
            key={status}
            className={`stacked-bar__segment swatch--${status}`}
            style={{ flexGrow: totals[status] }}
            title={`${STATUS_LABELS[status]}: ${totals[status]} (${percent(totals[status], total)}%)`}
          />
        ))}
      </div>
      <ul className="legend">
        {STATUS_ORDER.map((status) => (
          <li key={status} className="legend__item">
            <span className={`swatch swatch--${status}`} aria-hidden="true" />
            <span className="legend__label">{STATUS_LABELS[status]}</span>
            <span className="legend__value">{formatNumber(totals[status])}</span>
            <span className="legend__percent">{percent(totals[status], total)}%</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

/** Column chart of queries per day with a hover / focus tooltip. */
export function DailyActivityChart({ series }) {
  const [active, setActive] = useState(null);
  const containerRef = useRef(null);
  const [width, setWidth] = useState(640);
  const height = 200;

  // Draw at the real container width so text and bars keep their pixel sizes.
  useEffect(() => {
    const element = containerRef.current;
    if (!element) return undefined;
    const observer = new ResizeObserver(([entry]) => setWidth(Math.max(280, Math.round(entry.contentRect.width))));
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  const padding = { top: 16, right: 8, bottom: 28, left: 32 };
  const plotWidth = width - padding.left - padding.right;
  const plotHeight = height - padding.top - padding.bottom;
  const max = niceMax(Math.max(0, ...series.map((day) => day.total)));
  const band = plotWidth / series.length;
  const barWidth = Math.min(24, band * 0.6);
  const ticks = [0, max / 2, max].filter((tick, index, all) => Number.isInteger(tick) && all.indexOf(tick) === index);
  const y = (value) => padding.top + plotHeight - (value / max) * plotHeight;
  const total = series.reduce((sum, day) => sum + day.total, 0);
  const activeDay = active !== null ? series[active] : null;

  return (
    <>
      <div className="column-chart" ref={containerRef}>
        <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`Queries per day for the last ${series.length} days, ${total} in total`}>
          {ticks.map((tick) => (
            <g key={tick}>
              <line className={tick === 0 ? 'chart-baseline' : 'chart-grid'} x1={padding.left} x2={width - padding.right} y1={y(tick)} y2={y(tick)} />
              <text className="chart-axis" x={padding.left - 8} y={y(tick)} dy="0.32em" textAnchor="end">{tick}</text>
            </g>
          ))}
          {series.map((day, index) => {
            const x = padding.left + index * band + (band - barWidth) / 2;
            const barHeight = (day.total / max) * plotHeight;
            const labelEvery = width < 500 ? 4 : 2;
            const showLabel = index === series.length - 1 || (index % labelEvery === 0 && series.length - 1 - index >= labelEvery / 2);
            return (
              <g
                key={day.date}
                tabIndex={0}
                className="column"
                onMouseEnter={() => setActive(index)}
                onMouseLeave={() => setActive(null)}
                onFocus={() => setActive(index)}
                onBlur={() => setActive(null)}
                aria-label={`${formatShortDate(day.date)}: ${day.total} queries, ${day.escalated} escalated`}
              >
                <rect className="column__hit" x={padding.left + index * band} y={padding.top} width={band} height={plotHeight} />
                {day.total > 0 && (
                  <path
                    className={`column__bar ${active === index ? 'is-active' : ''}`}
                    d={roundedTopBar(x, y(day.total), barWidth, barHeight, 4)}
                  />
                )}
                {showLabel && (
                  <text className="chart-axis" x={x + barWidth / 2} y={height - 8} textAnchor="middle">{formatShortDate(day.date)}</text>
                )}
              </g>
            );
          })}
        </svg>
        {activeDay && (
          <div
            className="tooltip tooltip--floating"
            role="tooltip"
            style={{ left: `${((padding.left + active * band + band / 2) / width) * 100}%` }}
          >
            <strong>{formatShortDate(activeDay.date)}</strong>
            <span className="tooltip__row">Queries<b>{activeDay.total}</b></span>
            <span className="tooltip__row">Escalated<b>{activeDay.escalated}</b></span>
          </div>
        )}
      </div>
      <DataTable
        caption="Queries per day"
        columns={['Date', 'Queries', 'Escalated']}
        rows={series.map((day) => [formatShortDate(day.date), day.total, day.escalated])}
      />
    </>
  );
}

/** SVG path for a column with 4px rounded top corners and a square base. */
function roundedTopBar(x, top, width, height, radius) {
  const r = Math.min(radius, width / 2, height);
  const bottom = top + height;
  return `M${x},${bottom} V${top + r} Q${x},${top} ${x + r},${top} H${x + width - r} Q${x + width},${top} ${x + width},${top + r} V${bottom} Z`;
}
