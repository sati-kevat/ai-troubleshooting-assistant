import Icon from './Icon.jsx';

export function LoadingState({ label = 'Loading…' }) {
  return (
    <div className="state state--loading" role="status" aria-live="polite">
      <span className="spinner" aria-hidden="true" />
      <span>{label}</span>
    </div>
  );
}

export function EmptyState({ title, children, action }) {
  return (
    <div className="state state--empty">
      <p className="state__title">{title}</p>
      {children && <p className="state__text">{children}</p>}
      {action}
    </div>
  );
}

export function ErrorMessage({ error, onRetry, title = 'Something went wrong' }) {
  if (!error) return null;
  return (
    <div className="alert alert--error" role="alert">
      <Icon name="alert" size={18} />
      <div className="alert__body">
        <p className="alert__title">{title}</p>
        <p>{error.message || String(error)}</p>
      </div>
      {onRetry && (
        <button type="button" className="button button--secondary button--small" onClick={onRetry}>
          <Icon name="refresh" size={14} /> Retry
        </button>
      )}
    </div>
  );
}

export function SuccessMessage({ children, onDismiss }) {
  if (!children) return null;
  return (
    <div className="alert alert--success" role="status">
      <Icon name="check" size={18} />
      <div className="alert__body"><p>{children}</p></div>
      {onDismiss && (
        <button type="button" className="icon-button" onClick={onDismiss} aria-label="Dismiss message">
          <Icon name="close" size={16} />
        </button>
      )}
    </div>
  );
}
