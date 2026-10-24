import { navigate } from '../../hooks/useHashRoute.js';
import { FictionalBadge } from '../common/Badges.jsx';

export default function SourceList({ sources, confidence }) {
  if (!sources?.length) {
    return (
      <p className="muted">
        No knowledge-base article matched this issue. Nothing in this answer comes from a source article.
      </p>
    );
  }
  return (
    <ul className="source-list">
      {sources.map((source, index) => (
        <li key={source.code} className="source">
          <div className="source__header">
            <span className="source__code">{source.code}</span>
            {index === 0 && <span className="source__primary">{confidence === 'low' ? 'Closest match' : 'Primary source'}</span>}
            {source.is_fictional && <FictionalBadge />}
          </div>
          <p className="source__title">{source.title}</p>
          <p className="source__meta">
            {source.category?.name} · relevance score {Number(source.score).toFixed(1)}
          </p>
          {source.matched_terms?.length > 0 && (
            <p className="source__terms">
              Matched: {source.matched_terms.slice(0, 8).map((term) => <span key={term} className="term">{term}</span>)}
            </p>
          )}
          <button type="button" className="link-button" onClick={() => navigate('knowledge-base', source.code)}>
            Open article
          </button>
        </li>
      ))}
    </ul>
  );
}
