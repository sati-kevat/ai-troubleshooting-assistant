import { useState } from 'react';
import { buildEscalationNote, formatDateTime } from '../../utils/format.js';
import { ConfidenceBadge, ModeBadge, StatusBadge } from '../common/Badges.jsx';
import Icon from '../common/Icon.jsx';
import OutcomeActions from './OutcomeActions.jsx';
import SourceList from './SourceList.jsx';

function SourceTag({ code }) {
  return code ? <span className="source-tag" title={`From knowledge-base article ${code}`}>{code}</span> : null;
}

/** Shows one troubleshooting answer: summary, causes, steps, next action, escalation and sources. */
export default function AnswerPanel({ result, onStatusChange, showOutcome = true }) {
  const answer = result.answer || {};
  const [doneSteps, setDoneSteps] = useState(() => new Set());
  const [copied, setCopied] = useState('');
  const uncertain = result.confidence === 'low' || result.confidence === 'none';
  const escalation = answer.escalation || {};
  const steps = answer.steps || [];

  function toggleStep(index) {
    setDoneSteps((previous) => {
      const next = new Set(previous);
      next.has(index) ? next.delete(index) : next.add(index);
      return next;
    });
  }

  async function copyEscalationNote() {
    try {
      await navigator.clipboard.writeText(buildEscalationNote(result));
      setCopied('Escalation note copied to clipboard.');
    } catch {
      setCopied('Copy failed - your browser blocked clipboard access.');
    }
    setTimeout(() => setCopied(''), 4000);
  }

  const completedNote = doneSteps.size
    ? `Completed steps ${[...doneSteps].sort((a, b) => a - b).map((i) => i + 1).join(', ')} of ${steps.length}.`
    : '';

  return (
    <article className="card answer" aria-labelledby={`answer-${result.query_id}`}>
      <header className="answer__header">
        <div>
          <p className="eyebrow">Query #{result.query_id} · {formatDateTime(result.created_at)}</p>
          <h2 id={`answer-${result.query_id}`} className="answer__title">Troubleshooting guidance</h2>
        </div>
        <div className="badge-row">
          <ConfidenceBadge level={result.confidence} />
          <ModeBadge mode={result.mode} model={result.model} aiConfigured={result.ai_configured} aiFailed={Boolean(result.ai_fallback_reason)} />
          <StatusBadge status={result.status} />
        </div>
      </header>

      {result.notice && (
        <p className={`notice ${result.ai_fallback_reason ? 'notice--fallback' : result.mode === 'ai' ? 'notice--ai' : 'notice--demo'}`}>
          <Icon name={result.ai_fallback_reason ? 'alert' : 'info'} size={16} /> {result.notice}
        </p>
      )}

      <div className={`alert ${uncertain ? 'alert--warning' : 'alert--info'}`} role={uncertain ? 'alert' : undefined}>
        <Icon name={uncertain ? 'alert' : 'info'} size={18} />
        <div className="alert__body">
          <p className="alert__title">{uncertain ? 'Answer is uncertain' : 'How reliable is this?'}</p>
          <p>{answer.uncertainty?.message}</p>
        </div>
      </div>

      <section className="answer__section">
        <h3 className="section-title">Issue summary</h3>
        <p>{answer.summary}</p>
      </section>

      <div className="answer__grid">
        <section className="answer__section">
          <h3 className="section-title">Possible causes</h3>
          {answer.possible_causes?.length ? (
            <ul className="bullet-list">
              {answer.possible_causes.map((cause) => (
                <li key={cause.text}>{cause.text} <SourceTag code={cause.source} /></li>
              ))}
            </ul>
          ) : <p className="muted">No supported causes - the knowledge base does not cover this issue.</p>}
        </section>

        <section className="answer__section">
          <h3 className="section-title">
            Troubleshooting steps
            {steps.length > 0 && <span className="section-title__meta">{doneSteps.size} of {steps.length} done</span>}
          </h3>
          {steps.length ? (
            <ol className="step-list">
              {steps.map((step, index) => (
                <li key={step.text} className={doneSteps.has(index) ? 'is-done' : ''}>
                  <label className="step">
                    <input type="checkbox" checked={doneSteps.has(index)} onChange={() => toggleStep(index)} />
                    <span className="step__number" aria-hidden="true">{index + 1}</span>
                    <span className="step__text">{step.text} <SourceTag code={step.source} /></span>
                  </label>
                </li>
              ))}
            </ol>
          ) : <p className="muted">No verified steps are available. Do not attempt an unverified fix.</p>}
        </section>
      </div>

      <section className="answer__section next-action">
        <h3 className="section-title">Recommended next action</h3>
        <p>{answer.next_action}</p>
      </section>

      <section className={`answer__section escalation ${escalation.recommended ? 'escalation--recommended' : ''}`}>
        <div className="escalation__header">
          <h3 className="section-title">
            {escalation.recommended ? 'Escalation recommended' : 'When to escalate'}
          </h3>
          <span className="escalation__team">Escalate to: <strong>{escalation.escalate_to || 'L2 Support'}</strong></span>
        </div>
        <p>{escalation.reason}</p>
        {escalation.conditions?.length > 0 && (
          <ul className="bullet-list">
            {escalation.conditions.map((condition) => (
              <li key={condition.text}>{condition.text} <SourceTag code={condition.source} /></li>
            ))}
          </ul>
        )}
        <details className="collect" open={escalation.recommended}>
          <summary>Information to collect before escalating</summary>
          <ul className="bullet-list">
            {(answer.information_to_collect || []).map((item) => <li key={item}>{item}</li>)}
          </ul>
        </details>
        <div className="escalation__actions">
          <button type="button" className="button button--secondary button--small" onClick={copyEscalationNote}>
            <Icon name="copy" size={14} /> Copy escalation note
          </button>
          <span className="small muted" role="status" aria-live="polite">{copied}</span>
        </div>
      </section>

      <section className="answer__section">
        <h3 className="section-title">Knowledge-base sources used</h3>
        <SourceList sources={result.sources} confidence={result.confidence} />
      </section>

      {showOutcome && (
        <OutcomeActions
          queryId={result.query_id}
          status={result.status}
          defaultNote={completedNote}
          onUpdated={onStatusChange}
        />
      )}
    </article>
  );
}
