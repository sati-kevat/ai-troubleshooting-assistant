# Key Technical Decisions

Each decision is described in plain language: what was chosen, why, and what it costs.

## 1. Retrieval comes first; the AI never acts as the knowledge source
**Decision:** every answer starts from the knowledge-base articles found by retrieval. In AI mode, the local model can only *select* steps and causes from those articles, by article code and item number. The backend copies the wording from the database.
**Why:** in support work, a confident but invented step (for example "factory reset the terminal") can cause real damage, such as losing unsettled transactions. Selecting by reference makes invented steps impossible, rather than just discouraged by the prompt.
**Trade-off:** the AI can't adapt step wording to the specific issue. It only chooses, orders and summarises.

## 2. Weighted keyword retrieval instead of embeddings
**Decision:** a transparent scoring method in PHP: field weights (keywords 3, title 2.5, symptoms 2, causes and category 1) × IDF, plus a bonus for multi-word phrases, with simple stemming and stop words.
**Why:** it needs no extra services, costs nothing per query, behaves the same every time, and is easy to explain and debug ("matched on: upi, debited, pending"). For a 15-article knowledge base it is accurate: 27 of 27 test cases pass.
**Trade-off:** synonyms that aren't in an article's keywords are missed, and the thresholds were tuned on this knowledge base. The next step would be MySQL FULLTEXT, then embeddings.

## 3. Confidence levels that change what the agent sees
**Decision:** four levels with fixed rules (README §4). *Low* and *unsupported* show **no steps** and recommend escalation.
**Why:** testing showed that showing a weakly matched article's steps was misleading. A GST invoice question matched the printer article because of the word "printed". Giving no steps and an honest escalation is safer than a plausible wrong answer. Generic words ("not", "error", "code") are also ignored as evidence after "QR code not scanning" was wrongly rated high.
**Trade-off:** sometimes a useful article is hidden behind a "low" rating. It is still listed as a source the agent can open.

## 4. The agent records outcomes; the system never marks anything resolved
**Decision:** queries are saved as `open`. Only an explicit agent action sets resolved, unresolved or escalated.
**Why:** the brief says not to claim resolution without evidence. It also keeps the dashboard's resolution rate meaningful.

## 5. Demo mode is the default, and labels are always honest
**Decision:** by default (`AI_PROVIDER=demo`), answers come from templates and are labelled "Demo mode · no AI call". In AI mode there are separate labels for an AI answer, a failed AI call ("AI unavailable" with the reason), and cases where no AI call was needed.
**Why:** the brief requires demo mode never to pretend an AI call happened. It also means evaluators can run the project with no account and no cost.

## 6. A local model through Ollama instead of a cloud AI API
**Decision:** AI mode calls a local Ollama server (`POST http://127.0.0.1:11434/api/chat`) running an open model, by default `llama3.2:3b` (about 2 GB). The project originally used the Anthropic Claude API; it was switched to Ollama at the candidate's request.
**Why:**
- **Privacy:** issue text, which in real support work can include merchant details, never leaves the machine.
- **No secrets and no cost:** there is no API key to protect, and no per-request bill.
- **Easy for evaluators:** anyone can enable AI mode by installing Ollama; no account is needed.
- It is still plain HTTP called with PHP cURL, so no Composer packages are needed.

**Trade-off:** small local models are weaker and slower than large cloud models (expect tens of seconds per answer on a laptop CPU). That's acceptable because the model only selects and summarises; step text always comes from the knowledge base, and anything unusable falls back to the template answer. The timeout default is 120 s, and the frontend waits 150 s.

## 7. Structured JSON output from the AI, checked again in PHP
**Decision:** pass a JSON schema in Ollama's `format` option (structured outputs) and use `temperature: 0`, then re-validate every field and reference in PHP. Any problem leads to the template fallback.
**Why:** belt and braces. The schema makes valid JSON very likely even for small models, and the PHP check guarantees the UI never shows unchecked AI output.

## 8. A local mock server for testing AI mode
**Decision:** `backend/tests/mock_ollama_server.php` imitates Ollama's `/api/chat`, with scenarios for success and for each failure type (missing model, server error, cut-off output, invalid JSON, invalid references, slow responses). The test also covers "Ollama not running".
**Why:** Ollama and a model weren't installed on the development machine. The mock lets every code path be tested repeatably, in seconds, without a 2 GB download.
**Limitation:** a mock proves that our code handles responses correctly; it can't prove how well a real model chooses steps. AI mode is therefore marked "not yet run with a real model".

## 9. Plain PHP with a small router, no framework
**Decision:** a front controller, a router of about 50 lines, repositories and services. No Composer dependencies at all.
**Why:** it runs on stock XAMPP and stays easy to read in an interview. The structure (validation → service → repository) mirrors what a framework would provide.
**Trade-off:** no built-in features like migrations or middleware. That's acceptable at this size.

## 10. Apache through a directory junction
**Decision:** link `htdocs/pinelabs-api` to the project's `backend` folder. `backend/.htaccess` routes all requests to `public/index.php` and blocks `.env`, logs and SQL files.
**Why:** one copy of the code, so edits take effect immediately and nothing gets out of sync. Source files, `.env` and logs aren't reachable over HTTP (verified: 403/404).

## 11. Security basics
- PDO with **native** prepared statements (emulation off) for every query.
- Server-side validation of every input (length, required letters, enum values, numeric IDs, slug format) and a 16 KB request-body limit.
- `display_errors` off. Unexpected errors return a generic 500, and details go to `storage/logs/app.log`.
- CORS allow-list, not `*`.
- No API keys are needed: AI mode runs locally through Ollama. The packaging script refuses to package a `.env` file or a non-empty database password.
- React escapes all output, so user text can't inject HTML.

## 12. Frontend: React + Vite with no extra libraries
**Decision:** no router, UI kit or chart library. A tiny hash router, a `useApi` hook, and hand-written SVG/CSS charts.
**Why:** the brief asks to avoid unnecessary dependencies. The whole app is about 190 KB of JavaScript before compression (about 59 KB gzipped). The charts follow common data-visualisation practice: one series colour, status colours always paired with a label, hover tooltips, and a data-table view.

## 13. Analytics computed live from SQL
**Decision:** every dashboard number is a `COUNT`/`SUM` over `support_queries` at request time. Sample rows are flagged and can be excluded.
**Why:** the numbers can't drift from the data, and the API test checks them against direct SQL. Caching or summary tables would only be needed at a much larger scale.

## 14. Hash-based routing (`#/history/12`)
**Why:** it works under any static host or Vite with no server rewrite rules, and deep links (for example to a query or an article) still work.
