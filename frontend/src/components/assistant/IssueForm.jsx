import { useId, useState } from 'react';

const MIN_LENGTH = 10;
const MAX_LENGTH = 2000;

const EXAMPLES = [
  'Customer paid by UPI and money was debited, but the terminal still shows the payment as pending.',
  'Printer prints blank receipts after the paper roll was changed this morning.',
  'Merchant cannot log in to the merchant portal and the OTP is not received.',
  'Our payment API integration returns 401 invalid signature in production only.',
  'Merchant is asking how to update the GST number printed on their monthly invoice.',
];

export function validateIssue(text) {
  const trimmed = text.trim();
  if (!trimmed) return 'Please describe the issue.';
  if (trimmed.length < MIN_LENGTH) return `Please add more detail (at least ${MIN_LENGTH} characters).`;
  if (trimmed.length > MAX_LENGTH) return `The description is too long (maximum ${MAX_LENGTH} characters).`;
  return null;
}

export default function IssueForm({ categories, submitting, serverErrors, onSubmit }) {
  const [issueText, setIssueText] = useState('');
  const [category, setCategory] = useState('');
  const [touched, setTouched] = useState(false);
  const textId = useId();
  const categoryId = useId();
  const errorId = useId();

  const clientError = touched ? validateIssue(issueText) : null;
  const fieldError = clientError || serverErrors?.issue_text || null;
  const length = issueText.trim().length;

  function handleSubmit(event) {
    event.preventDefault();
    setTouched(true);
    if (validateIssue(issueText) || submitting) return;
    onSubmit({ issue_text: issueText.trim(), category: category || null });
  }

  function applyExample(example) {
    setIssueText(example);
    setTouched(false);
  }

  function handleClear() {
    setIssueText('');
    setCategory('');
    setTouched(false);
  }

  return (
    <form className="card issue-form" onSubmit={handleSubmit} noValidate>
      <div className="field">
        <label htmlFor={textId} className="field__label">Describe the technical issue</label>
        <p className="field__hint">Include the error message, what the merchant was doing, and what has already been tried.</p>
        <textarea
          id={textId}
          className={`textarea ${fieldError ? 'is-invalid' : ''}`}
          rows={5}
          maxLength={MAX_LENGTH + 200}
          value={issueText}
          placeholder="e.g. Terminal shows 'Host not reachable' since morning. Wi-Fi works on other devices."
          aria-invalid={Boolean(fieldError)}
          aria-describedby={fieldError ? errorId : undefined}
          onChange={(event) => setIssueText(event.target.value)}
          onBlur={() => issueText && setTouched(true)}
          disabled={submitting}
        />
        <div className="field__meta">
          {fieldError ? <span id={errorId} className="field__error">{fieldError}</span> : <span />}
          <span className={`field__count ${length > MAX_LENGTH ? 'is-over' : ''}`}>{length} / {MAX_LENGTH}</span>
        </div>
      </div>

      <div className="issue-form__row">
        <div className="field field--inline">
          <label htmlFor={categoryId} className="field__label">Category</label>
          <select id={categoryId} className="select" value={category} onChange={(event) => setCategory(event.target.value)} disabled={submitting}>
            <option value="">Auto-detect from description</option>
            {(categories || []).map((item) => (
              <option key={item.slug} value={item.slug}>{item.name}</option>
            ))}
          </select>
          {serverErrors?.category && <span className="field__error">{serverErrors.category}</span>}
        </div>
        <div className="issue-form__actions">
          <button type="button" className="button button--ghost" onClick={handleClear} disabled={submitting || (!issueText && !category)}>
            Clear
          </button>
          <button type="submit" className="button button--primary" disabled={submitting}>
            {submitting ? <><span className="spinner spinner--small" aria-hidden="true" /> Analysing…</> : 'Get troubleshooting help'}
          </button>
        </div>
      </div>

      <div className="examples">
        <span className="examples__label">Try an example:</span>
        {EXAMPLES.map((example) => (
          <button key={example} type="button" className="chip" onClick={() => applyExample(example)} disabled={submitting}>
            {example.length > 48 ? `${example.slice(0, 46)}…` : example}
          </button>
        ))}
      </div>
    </form>
  );
}
