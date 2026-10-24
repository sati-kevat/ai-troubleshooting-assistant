import { useRef, useState } from 'react';
import { api } from '../api/client.js';
import AnswerPanel from '../components/assistant/AnswerPanel.jsx';
import IssueForm from '../components/assistant/IssueForm.jsx';
import { ErrorMessage, LoadingState } from '../components/common/States.jsx';
import { navigate } from '../hooks/useHashRoute.js';

export default function AssistantPage({ categories, aiMode = false }) {
  const [result, setResult] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const resultRef = useRef(null);

  async function handleSubmit(payload) {
    setSubmitting(true);
    setError(null);
    setResult(null);
    try {
      const data = await api.troubleshoot(payload);
      setResult(data);
      requestAnimationFrame(() => resultRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
    } catch (err) {
      setError(err);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="page">
      <header className="page__header">
        <div>
          <h1 className="page__title">Troubleshooting assistant</h1>
          <p className="page__subtitle">
            Describe a merchant or system issue. The assistant searches the knowledge base, cites its sources,
            and recommends escalation when the knowledge base does not cover the issue.
          </p>
        </div>
      </header>

      <IssueForm
        categories={categories}
        submitting={submitting}
        serverErrors={error?.fields}
        onSubmit={handleSubmit}
      />

      <div ref={resultRef} className="result-area" aria-live="polite">
        {submitting && (
          <LoadingState
            label={aiMode
              ? 'Searching the knowledge base and asking the local AI model… this can take up to a minute or two.'
              : 'Searching the knowledge base and preparing guidance…'}
          />
        )}
        {error && !error.fields && <ErrorMessage error={error} title="Could not get troubleshooting help" />}
        {result && (
          <>
            <AnswerPanel
              key={result.query_id}
              result={result}
              onStatusChange={(updated) => setResult((previous) => ({ ...previous, status: updated.status }))}
            />
            <p className="center small muted">
              Saved to query history as #{result.query_id}.{' '}
              <button type="button" className="link-button" onClick={() => navigate('history', result.query_id)}>View in history</button>
            </p>
          </>
        )}
      </div>
    </div>
  );
}
