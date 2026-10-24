# Requirements Checklist - PROVISIONAL

> ⚠️ **This checklist is provisional.**
> I did **not** have the original Pine Labs assignment document (PDF, text or screenshots) for "Practical AI Assignment 3: AI Troubleshooting Assistant for L1/L2 Support" when building this project. The requirements below come from the **written project brief I worked from**, not from the official assignment text.
> The official document may contain mandatory deliverables, evaluation criteria, formats or deadlines that are not listed here. **Before submission, compare this checklist with the original document and add anything that's missing.**

**Status key:** ✅ Done and tested · 🟡 Done, partly tested or with limitations · ⚪ Not done · 🔬 Implemented but untested in the real environment

## A. Troubleshooting assistant

| # | Requirement (from the brief) | Implementation | How it was tested | Status |
|---|---|---|---|---|
| A1 | Interface to describe a technical issue in natural language | `frontend/src/components/assistant/IssueForm.jsx` | UI test: example chip and typed input; API test | ✅ |
| A2 | Identify relevant knowledge-base articles | `backend/src/Services/KnowledgeRetriever.php` (weighted keywords + IDF) | `retrieval_test.php`: 27 cases | ✅ |
| A3 | Concise issue summary | Template summary (demo) / AI summary (AI mode) | API test; AI mock test | 🟡 the demo summary is a template, not a paraphrase |
| A4 | Possible causes and actionable steps | Copied from the best article; ordered steps that can be ticked off | API test (steps cite sources); UI test | ✅ |
| A5 | Show the source article / KB reference | Source cards + `KB-xxx` tag on every cause, step and condition; "Open article" link | UI test, API test | ✅ |
| A6 | Recommend the next action | `next_action` in every answer | API test | ✅ |
| A7 | Clearly indicate uncertain or unsupported answers | Confidence levels high/medium/low/unsupported with a warning banner | Retrieval test; API test (weak + out-of-scope); UI test | ✅ |
| A8 | Recommend escalation when information is unreliable | Low/none → "Escalation recommended", team, info to collect, copyable note | API + UI tests | ✅ |
| A9 | Don't fabricate solutions or claim resolution without evidence | No steps on weak matches; AI can only reference KB items; status starts `open` and only the agent changes it | API test (status `open`, weak match has no steps); AI mock test (invalid references dropped) | ✅ |

## B. Knowledge base

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| B1 | Small, useful sample KB | 15 articles in 8 categories (`database/02_seed_knowledge_base.sql`) | Import twice: no duplicates | ✅ |
| B2 | Symptoms, causes, steps, categories, escalation conditions | Columns in `kb_articles` + `escalate_to` and `keywords` | Schema review; KB page | ✅ |
| B3 | Retrieve articles **before** generating a grounded response | Retrieval runs first; the AI only receives the retrieved articles | AI mock test inspects the request | ✅ (AI part 🔬 with a real Ollama model) |
| B4 | Display sources for each answer | Source list per answer, stored with the query | UI + API tests | ✅ |
| B5 | Easy to extend | Insert a row; no code change. Documented in README §8 | Manual review | ✅ |
| B6 | Sample data clearly labelled fictional | SQL headers, `is_fictional` flag, "Fictional sample" badges, KB page banner | UI review | ✅ |

## C. Query history

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| C1 | Record queries and outcomes in MySQL | `support_queries` + `query_sources`, saved in one transaction | API test reads back stored data | ✅ |
| C2 | Display previous queries and statuses | History page with detail view | UI test | ✅ |
| C3 | Timestamps and issue categories | `created_at`, `status_updated_at`, category (user-selected or detected) | UI + API tests | ✅ |
| C4 | Validate input | Server: `Validator.php` (length, words, enums, IDs); client: form checks | API test: 9 invalid-input cases | ✅ |
| C5 | Handle database errors safely | Generic 503 message; details only in `storage/logs/app.log` | API tested with a wrong database port (503 + generic message, details only in the log). The UI was not tested with MySQL stopped (manual check in README §12) | 🟡 |

## D. Analytics dashboard

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| D1 | Total support queries | Stat tile | API test: compared with SQL `COUNT(*)` | ✅ |
| D2 | Queries by category | Bar chart + data table | API test: category totals add up | ✅ |
| D3 | Resolved and unresolved | Stat tiles + outcome breakdown | API test vs SQL | ✅ |
| D4 | Escalated | Stat tile + "assistant recommended escalation" count | API test vs SQL | ✅ |
| D5 | Recent activity | 14-day column chart + latest queries list | API test: daily sum vs SQL | ✅ |
| D6 | Database-backed values; demo data labelled; no invented results | Live SQL on every load; `is_sample` banner + toggle | API test with and without samples | ✅ |

## E. User experience

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| E1 | Modern, professional, consistent UI | Design tokens in `styles/index.css` | Screenshot review | ✅ |
| E2 | Loading, empty, success, error states | `components/common/States.jsx`, used on every page | UI test (empty search, 404, success) | ✅ |
| E3 | Responsive desktop/mobile | Breakpoints at 1080/800/640 px; table becomes cards | Headless Edge at 390 px: no horizontal overflow | 🟡 emulated only, no real device |
| E4 | Accessible | Labels, ARIA states, focus outlines, skip link, keyboard-focusable chart bars, data tables, status shown with icon + text | Manual review only; no automated a11y audit | 🟡 |
| E5 | Avoid unnecessary animation/decoration | Only a loading spinner (slowed under reduced-motion) | Review | ✅ |
| E6 | All visible controls work | Every button wired to an action | UI test covers the main controls | ✅ |

## Backend and security

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| S1 | Sensible PHP REST structure | Front controller + router + repositories + services | Review; API test | ✅ |
| S2 | Prepared statements | PDO with native prepares everywhere | Code review; SQL-like input test | ✅ |
| S3 | Validate and sanitise input | `Validator.php`; React escapes output | API test | ✅ |
| S4 | Handle API and DB errors; hide sensitive errors | Global exception handler; `display_errors` off; generic messages | DB-down test; 404/405/415/422 tests | ✅ |
| S5 | CORS for local development | Allow-list from `CORS_ALLOWED_ORIGINS` | API test: allowed vs unknown origin | ✅ |
| S6 | Secrets outside source; example config | `.env` (git-ignored, web-blocked) + `.env.example` | `.env` returns 403 over HTTP; packaging script scan | ✅ |
| S7 | API keys never in the frontend or source | AI mode uses a local Ollama model, so no API key is needed at all; the frontend only knows the backend URL | Code review | ✅ |

## AI integration

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| AI1 | Integrate an AI model through a secure backend API | `OllamaAnswerGenerator.php`: PHP calls a local Ollama model (`/api/chat`, default `llama3.2:3b`) | Mock Ollama server: 33 checks | 🔬 **not yet run with a real Ollama install** |
| AI2 | Clearly labelled demo mode when no AI is available | Default `AI_PROVIDER=demo`; badges and notices | API + UI tests | ✅ |
| AI3 | Never pretend an AI call happened | Separate labels for "Demo mode · no AI call", "Template answer · AI unavailable" and "AI-assisted" | AI mock test + UI check | ✅ |

## Database

| # | Requirement | Implementation | How it was tested | Status |
|---|---|---|---|---|
| DB1 | Appropriate schema | 4 InnoDB tables with foreign keys and indexes | Import + API tests | ✅ |
| DB2 | SQL scripts to create DB and tables | `database/01_schema.sql` | Imported twice | ✅ |
| DB3 | Sample data | `02_seed_knowledge_base.sql`, optional `03_sample_queries.sql` | Imported twice | ✅ |
| DB4 | phpMyAdmin import instructions | README §8 | Written; **the import ran through the MySQL command line, not the phpMyAdmin UI** | 🟡 |
| DB5 | Backend connects to local MySQL | `Database.php` | Health endpoint | ✅ |

## Testing, documentation and submission

| # | Requirement | Where | Status |
|---|---|---|---|
| T1 | Dependencies checked; app run locally | `TEST_REPORT.md` | ✅ |
| T2 | Main flow, retrieval, escalation, persistence, analytics, validation, errors tested | `backend/tests/*`, `TEST_REPORT.md` | ✅ |
| T3 | Remaining issues reported honestly | `TEST_REPORT.md`, README §14 | ✅ |
| D-1 | README with all requested sections | `README.md` | ✅ |
| D-2 | Requirements checklist | this file (**provisional**) | 🟡 provisional |
| D-3 | Completed / incomplete features | `FEATURE_STATUS.md` | ✅ |
| D-4 | Key technical decisions | `TECHNICAL_DECISIONS.md` | ✅ |
| D-5 | 10-minute presentation outline | `PRESENTATION_OUTLINE.md` | ✅ |
| D-6 | Likely evaluation questions with answers | `EVALUATION_QA.md` | ✅ |
| D-7 | Explanation of AI-assisted development | `AI_ASSISTANCE.md` (**needs the candidate's own review**) | 🟡 |
| D-8 | Screenshots | List in README §15; **not yet captured** | ⚪ |
| D-9 | Submission package | `scripts/package-submission.ps1` | ✅ |

## Still to do before submission
1. Get the original assignment document and reconcile this checklist with it.
2. Capture the screenshots (README §15).
3. Review and personalise `AI_ASSISTANCE.md`.
4. Optional (free): install Ollama and `llama3.2:3b` (README §10), try AI mode, and update the 🔬 rows only if it was actually tested.
