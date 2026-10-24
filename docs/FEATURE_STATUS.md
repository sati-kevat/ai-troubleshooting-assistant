# Feature Status

Requirements are based on the working brief. The original assignment document has not been reviewed (see [REQUIREMENTS_CHECKLIST.md](REQUIREMENTS_CHECKLIST.md)).

## Completed and tested

| Area | Feature |
|---|---|
| Assistant | Issue form with client and server validation, optional category, example issues |
| Assistant | Keyword retrieval with IDF weighting, phrase bonus and four confidence levels |
| Assistant | Grounded answer: summary, causes, ordered steps, next action, escalation conditions and team, information to collect |
| Assistant | Source attribution on every item; source cards with score and matched terms; links to articles |
| Assistant | Uncertain/unsupported handling: no steps on weak matches, escalation recommended |
| Assistant | Step checklist; outcome recording (resolved / unresolved / escalated) with note; copyable escalation note |
| Knowledge base | 15 fictional articles, 8 categories; searchable and filterable page; re-runnable seed script |
| History | MySQL persistence in one transaction; list with filters, search, sample toggle, pagination; detail view with stored answer |
| Dashboard | Totals, outcome breakdown, by-category bars, 14-day activity, confidence distribution, top articles, latest queries; sample-data banner and toggle; tooltips and data tables |
| Backend | REST router, prepared statements, validation, CORS allow-list, generic error responses, server log, health endpoint |
| Security | `.env` outside version control and blocked over HTTP; AI mode is local (Ollama), so no API key exists to leak |
| Demo mode | Default mode with no AI model, clearly labelled |
| UX | Loading, empty, success and error states; responsive to 390 px; light and dark themes |
| Packaging | ZIP script that excludes dependencies and secrets and checks for a `.env` file or database password |

## Implemented but not fully verified

| Feature | What's missing |
|---|---|
| **AI mode (local Ollama model, default `llama3.2:3b`)** | Tested only against a local mock of the Ollama API (33 checks). **Not yet run with a real Ollama installation** (Ollama wasn't installed on the development PC). Real answer quality and speed are unverified. |
| phpMyAdmin import | Instructions written; the import itself was done through the MySQL command line, which uses the same SQL files. |
| Accessibility | Built in (labels, ARIA, keyboard focus, text and icons alongside colours), but no automated accessibility audit or screen-reader test was run. |
| Mobile layout | Checked in a 390 px emulated viewport, not on a real phone. |
| Browser coverage | Automated checks ran in Chromium-based Edge only. |
| UI behaviour when MySQL is stopped | The API returns a clean 503 (tested); the UI error state is a manual check. |

## Not implemented (out of scope for this prototype)

| Feature | Reason / suggestion |
|---|---|
| User login, roles, agent identity on queries | Not in the brief; needed before any real deployment |
| Admin UI for editing knowledge-base articles | Articles are managed in SQL or phpMyAdmin; an admin page would be the next feature |
| Semantic (embedding) search | Keyword retrieval is enough for 15 articles; see TECHNICAL_DECISIONS.md |
| Deleting or editing queries from the UI | History is append-only by design; outcomes can be updated |
| Rate limiting / request queueing for AI mode | A local model handles one request at a time; needed if many agents share it |
| Manual dark-mode toggle | The theme follows the OS setting |
| Screenshots | Listed in README §15; the candidate must capture them |
| Automated browser tests in the repository | UI checks were run during development with a temporary script that isn't part of the project; the README has a manual UI checklist |
