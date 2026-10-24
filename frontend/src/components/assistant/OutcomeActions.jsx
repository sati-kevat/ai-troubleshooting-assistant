import { useId, useState } from 'react';
import { api } from '../../api/client.js';
import { STATUS_LABELS } from '../../utils/format.js';
import { StatusBadge } from '../common/Badges.jsx';
import { ErrorMessage, SuccessMessage } from '../common/States.jsx';

const OUTCOMES = [
  { status: 'resolved', label: 'Resolved', hint: 'Merchant confirmed the issue is fixed' },
  { status: 'unresolved', label: 'Not resolved', hint: 'Steps did not fix the issue' },
  { status: 'escalated', label: 'Escalated', hint: 'Handed over to L2 or another team' },
];

/**
 * Lets the agent record the real outcome. The assistant never marks a
 * query resolved by itself - only this explicit action does.
 */
export default function OutcomeActions({ queryId, status, defaultNote = '', onUpdated }) {
  const [note, setNote] = useState('');
  const [saving, setSaving] = useState(null);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState('');
  const noteId = useId();

  async function record(nextStatus) {
    setSaving(nextStatus);
    setError(null);
    setSuccess('');
    try {
      const updated = await api.updateQueryStatus(queryId, {
        status: nextStatus,
        note: (note.trim() || defaultNote).slice(0, 500) || null,
      });
      setSuccess(`Outcome saved: query #${queryId} is now ${STATUS_LABELS[updated.status].toLowerCase()}.`);
      onUpdated?.(updated);
    } catch (err) {
      setError(err);
    } finally {
      setSaving(null);
    }
  }

  return (
    <div className="outcome">
      <div className="outcome__header">
        <h3 className="section-title">Record the outcome</h3>
        <span className="outcome__current">Current status: <StatusBadge status={status} /></span>
      </div>
      <p className="muted small">Only mark the query resolved after the merchant confirms the fix.</p>
      <label htmlFor={noteId} className="field__label field__label--small">Outcome note (optional)</label>
      <textarea
        id={noteId}
        className="textarea textarea--small"
        rows={2}
        maxLength={500}
        value={note}
        placeholder={defaultNote || 'e.g. Restarted terminal and re-entered Wi-Fi password; test transaction approved.'}
        onChange={(event) => setNote(event.target.value)}
      />
      <div className="outcome__buttons">
        {OUTCOMES.map((outcome) => (
          <button
            key={outcome.status}
            type="button"
            className={`button button--outcome button--outcome-${outcome.status} ${status === outcome.status ? 'is-active' : ''}`}
            title={outcome.hint}
            aria-pressed={status === outcome.status}
            disabled={saving !== null}
            onClick={() => record(outcome.status)}
          >
            {saving === outcome.status ? 'Saving…' : outcome.label}
          </button>
        ))}
      </div>
      <SuccessMessage onDismiss={() => setSuccess('')}>{success}</SuccessMessage>
      <ErrorMessage error={error} title="The outcome could not be saved" />
    </div>
  );
}
