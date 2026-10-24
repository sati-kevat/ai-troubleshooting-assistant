export const STATUS_LABELS = {
  open: 'Open',
  resolved: 'Resolved',
  unresolved: 'Unresolved',
  escalated: 'Escalated',
};

export const CONFIDENCE_LABELS = {
  high: 'High confidence',
  medium: 'Medium confidence',
  low: 'Low confidence',
  none: 'Unsupported',
};

const dateTimeFormat = new Intl.DateTimeFormat('en-IN', {
  day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
});
const shortDateFormat = new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short' });

export function formatDateTime(value) {
  if (!value) return '-';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '-' : dateTimeFormat.format(date);
}

export function formatShortDate(value) {
  // "2026-10-24" is parsed as local midnight to avoid off-by-one-day shifts.
  const date = new Date(`${value}T00:00:00`);
  return Number.isNaN(date.getTime()) ? value : shortDateFormat.format(date);
}

export function formatRelative(value) {
  if (!value) return '';
  const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000);
  if (seconds < 60) return 'just now';
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.round(hours / 24);
  return `${days} day${days === 1 ? '' : 's'} ago`;
}

export function formatNumber(value) {
  return new Intl.NumberFormat('en-IN').format(value ?? 0);
}

export function truncate(text, length = 120) {
  if (!text) return '';
  return text.length > length ? `${text.slice(0, length - 1).trimEnd()}…` : text;
}

/** Plain-text escalation note an agent can paste into a ticket or chat. */
export function buildEscalationNote(result) {
  const answer = result.answer || {};
  const sources = (result.sources || []).map((source) => `${source.code} - ${source.title}`).join('; ') || 'None (not covered by knowledge base)';
  const lines = [
    `Escalation - support query #${result.query_id}`,
    `Escalate to: ${answer.escalation?.escalate_to || 'L2 Support'}`,
    `Reported issue: ${result.issue_text}`,
    `Assistant confidence: ${CONFIDENCE_LABELS[result.confidence] || result.confidence}`,
    `Knowledge-base references: ${sources}`,
    `Reason: ${answer.escalation?.reason || ''}`,
    '',
    'Details collected (fill in):',
    ...(answer.information_to_collect || []).map((item) => `- ${item}: `),
  ];
  return lines.join('\n');
}
