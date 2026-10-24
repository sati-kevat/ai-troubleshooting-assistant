import { CONFIDENCE_LABELS, STATUS_LABELS } from '../../utils/format.js';
import Icon from './Icon.jsx';

const STATUS_ICONS = { open: 'circle', resolved: 'check', unresolved: 'x', escalated: 'arrowUp' };

export function StatusBadge({ status }) {
  return (
    <span className={`badge badge--status-${status}`}>
      <Icon name={STATUS_ICONS[status] || 'circle'} size={12} />
      {STATUS_LABELS[status] || status}
    </span>
  );
}

const CONFIDENCE_ICONS = { high: 'check', medium: 'info', low: 'alert', none: 'alert' };

export function ConfidenceBadge({ level }) {
  return (
    <span className={`badge badge--confidence-${level}`}>
      <Icon name={CONFIDENCE_ICONS[level] || 'info'} size={12} />
      {CONFIDENCE_LABELS[level] || level}
    </span>
  );
}

export function ModeBadge({ mode, model, aiConfigured = false, aiFailed = false }) {
  if (mode === 'ai') {
    return <span className="badge badge--mode-ai" title="Summary and step selection by a local Ollama model; step text quoted from the knowledge base">AI-assisted{model ? ` · ${model}` : ''}</span>;
  }
  if (aiFailed) {
    return <span className="badge badge--mode-demo" title="The AI call failed; this answer was built from templates">Template answer · AI unavailable</span>;
  }
  return (
    <span className="badge badge--mode-demo" title="No AI model was called for this answer">
      {aiConfigured ? 'Template answer · no AI call' : 'Demo mode · no AI call'}
    </span>
  );
}

export function SampleBadge() {
  return <span className="badge badge--sample" title="Fictional sample record from 03_sample_queries.sql">Sample</span>;
}

export function FictionalBadge() {
  return <span className="badge badge--sample" title="Written for this demo; not official documentation">Fictional sample</span>;
}
