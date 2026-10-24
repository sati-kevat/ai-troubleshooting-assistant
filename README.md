# AI Troubleshooting Assistant for L1/L2 Support

Take-home assignment for the **Pine Labs Technology Operations Intern** role. I chose **Practical AI Assignment 3: AI Troubleshooting Assistant for L1/L2 Support**.

> **Status at a glance**
> - Works fully in **demo mode** without any AI model. Answers are built only from the knowledge base, and every one is labelled "Demo mode · no AI call".
> - **AI mode uses a local model through [Ollama](https://ollama.com)** (default `llama3.2:3b`). It runs on your own computer: no API key, no cloud service, no cost.
> - The Ollama integration is implemented and tested against a **local mock of the Ollama API only**. **It has not yet been tested with a real Ollama installation and model** (Ollama wasn't installed on the development machine). Setup steps are in [section 10](#10-optional-local-ai-with-ollama).
> - The **original assignment document has not been reviewed**. The requirements checklist in [`docs/REQUIREMENTS_CHECKLIST.md`](docs/REQUIREMENTS_CHECKLIST.md) is **provisional** and is based on the written brief I worked from.
> - All knowledge-base articles and sample history records are **fictional**. They are not official Pine Labs documentation.

---

## Contents

1. [Problem statement](#1-problem-statement)
2. [Features](#2-features)
3. [Technology stack](#3-technology-stack)
4. [Architecture and data flow](#4-architecture-and-data-flow)
5. [Folder structure](#5-folder-structure)
6. [Prerequisites](#6-prerequisites)
7. [Installation on Windows (XAMPP)](#7-installation-on-windows-xampp)
8. [Database import](#8-database-import)
9. [Configuration](#9-configuration)
10. [Optional: local AI with Ollama](#10-optional-local-ai-with-ollama)
11. [Starting the application](#11-starting-the-application)
12. [Demo mode](#12-demo-mode)
13. [Testing](#13-testing)
14. [API reference](#14-api-reference)
15. [Known limitations](#15-known-limitations)
16. [Screenshots to capture](#16-screenshots-to-capture)
17. [Preparing the submission ZIP](#17-preparing-the-submission-zip)
18. [Further documentation](#18-further-documentation)

---

## 1. Problem statement

L1/L2 support agents get many repeated technical issues: terminals that won't connect, declined cards, pending UPI payments, delayed settlements, API errors. The fix is usually in a knowledge base, but finding the right article quickly is hard. Agents may then guess at a fix, skip steps, or escalate too late or too early.

This assistant helps an agent to:

1. describe an issue in plain language;
2. find the relevant knowledge-base articles;
3. get a short summary, likely causes and ordered troubleshooting steps, each tied to its source article;
4. see clearly when the knowledge base does **not** cover the issue, and escalate with the right details;
5. record the real outcome (resolved, unresolved or escalated), so the history and the dashboard reflect what actually happened.

The main design rule: **the assistant never invents a fix and never marks an issue as resolved by itself.**

## 2. Features

**Troubleshooting assistant**
- Natural-language issue form with validation (10–2000 characters), a character counter, an optional category, and clickable example issues.
- Retrieves relevant articles using weighted keyword scoring and rates its confidence as **high, medium, low or unsupported**.
- Each answer contains an issue summary, possible causes, numbered troubleshooting steps (tick them off as you go), the recommended next action, when to escalate and to which team, and the information to collect before escalating.
- Every cause, step and escalation condition shows the code of its source article (for example `KB-005`). Source cards show the relevance score and the matched terms, and link to the article.
- **Weak or no match:** no steps are shown as a solution, the answer is clearly marked uncertain, and escalation is recommended.
- **Copy escalation note:** puts a ready-to-paste summary for an L2 ticket on the clipboard.
- **Record the outcome:** Resolved, Not resolved or Escalated, with an optional note. If steps were ticked, a default note lists which ones were completed.

**Knowledge base**
- 15 fictional sample articles in 8 categories. Each has symptoms, causes, steps, escalation conditions, the team to escalate to, and keywords.
- A browsable, searchable page filterable by category, with links to individual articles.
- Easy to extend: add a row in SQL (see [section 8](#adding-knowledge-base-articles)).

**Query history**
- Every query is stored in MySQL with a timestamp, category, confidence, answer mode, the full answer, the cited sources and the status.
- A filterable list (status, category, text search, include/exclude sample records), pagination, and a detail view of the stored answer where the outcome can be updated.

**Analytics dashboard** (calculated live from MySQL on every load)
- Total queries; resolved, unresolved, escalated and open counts; resolution rate.
- An outcome breakdown bar, queries by category, a daily activity chart for the last 14 days, the confidence distribution, the most-cited articles and the latest queries.
- Fictional sample records are flagged in the data. A banner says when they are included, and a toggle excludes them.
- Charts have hover/focus tooltips and a "Show data table" option.

**User experience**
- Loading, empty, success and error states on every page. A backend/database/AI-mode status indicator sits in the header.
- Responsive layout down to phone width (tested at 390 px). Light and dark themes follow the operating-system setting.
- Keyboard-accessible controls, labelled form fields, a skip link, and a visible focus outline.

**AI mode (optional; local Ollama model; tested with a mock only)**
- The local model picks and orders the relevant steps and causes **by reference**, and writes the summary and next action. The step text itself is always copied verbatim from the knowledge base.
- Any AI failure (Ollama not running, model not downloaded, timeout, invalid output) falls back to the template answer and shows the reason.

## 3. Technology stack

| Layer | Technology |
|---|---|
| Frontend | React 18 (JavaScript/JSX), Vite 5, plain CSS. No other runtime dependencies. |
| Backend | PHP 8.2, a small custom REST router, PDO with prepared statements |
| Database | MySQL / MariaDB 10.4 (XAMPP) |
| Web server | Apache 2.4 (XAMPP) with `mod_rewrite`; the PHP built-in server is an alternative |
| AI (optional) | [Ollama](https://ollama.com) running a local open model (default `llama3.2:3b`), called from PHP with cURL through Ollama's `/api/chat` endpoint |
| Tests | Plain PHP scripts (no test framework); Vite production build |

Versions used during development: Windows 11, XAMPP with PHP 8.2.12, MariaDB 10.4.32 and Apache 2.4.58; Node.js 22.14 with npm 10.9.

## 4. Architecture and data flow

```
 Browser (React, http://localhost:5173)
   │  JSON over HTTP (CORS limited to the Vite origin)
   ▼
 Apache  http://localhost/pinelabs-api/api/...   (backend/.htaccess → public/index.php)
   │
   ├─ Router + Validator          validate and clean the input
   ├─ TroubleshootService
   │    1. KnowledgeRetriever     score every active kb_article → top 3 + confidence
   │    2. Answer generation
   │         • demo mode / low or no confidence → DemoAnswerGenerator (templates)
   │         • AI mode + medium/high confidence → OllamaAnswerGenerator
   │                ├─ POST http://127.0.0.1:11434/api/chat (local Ollama)
   │                └─ on any failure → template answer + visible reason
   │    3. QueryRepository        save query + answer + sources (one transaction)
   ├─ QueryRepository             history list, detail, outcome updates
   └─ AnalyticsRepository         live COUNT/SUM queries for the dashboard
   ▼
 MySQL: categories · kb_articles · support_queries · query_sources
```

**Submitting an issue, step by step**
1. The agent submits a description. The frontend checks the length and sends `POST /api/troubleshoot`.
2. The backend validates and cleans the text (removes control characters, checks the length, requires actual words).
3. **Retrieval:** text is lower-cased, stop words are removed and words are reduced to simple stems ("printing" → "print"). Each article gets a score:
   - for every query word found in a field: the field's weight (keywords 3, title 2.5, symptoms 2, causes and category 1) × an IDF factor, so rare words count more;
   - a bonus when a multi-word keyword phrase such as "charged twice" appears.

   Generic words like "not" or "error" don't count on their own.
4. **Confidence:**

   | Level | Rule |
   |---|---|
   | none | best score < 6 |
   | low | score < 12, or only one matching term |
   | medium | anything above low that isn't high |
   | high | score ≥ 18, at least 3 terms, and no similar score from a different category |
5. **The answer:**
   - High or medium: built from the best article.
   - Low or none: no steps, and escalation is recommended.
   - In AI mode, the local Ollama model is called only for high or medium confidence.
6. **Saving:** the query, the full answer (as JSON) and the cited sources are saved in **one transaction** with status `open`.
7. **Outcome:** later, the agent sets the outcome with `PATCH /api/queries/{id}`. The dashboard reads these statuses.

## 5. Folder structure

```
pinelabs project/
├── README.md                     this file
├── .gitignore
├── backend/                      PHP REST API (linked into XAMPP htdocs)
│   ├── .htaccess                 routes everything to public/index.php; blocks .env/logs
│   ├── .env.example              configuration template (copy to .env)
│   ├── public/index.php          front controller and route definitions
│   ├── src/
│   │   ├── bootstrap.php         loads classes, config, error handling
│   │   ├── Config.php  Database.php  Http.php  Router.php  Validator.php
│   │   ├── ApiException.php  Logger.php
│   │   ├── Repositories/         ArticleRepository, QueryRepository, AnalyticsRepository
│   │   └── Services/             KnowledgeRetriever, DemoAnswerGenerator,
│   │                             OllamaAnswerGenerator, AnswerFormat, TroubleshootService
│   ├── storage/logs/             app.log (server-side errors; git-ignored)
│   └── tests/                    retrieval_test, api_test, ai_mock_test, mock_ollama_server
├── database/
│   ├── 01_schema.sql             creates the database and tables
│   ├── 02_seed_knowledge_base.sql  categories + 15 fictional articles
│   └── 03_sample_queries.sql     OPTIONAL fictional history (flagged is_sample = 1)
├── frontend/                     React + Vite app
│   ├── .env.example              backend URL setting
│   ├── index.html  package.json  package-lock.json  vite.config.js
│   └── src/
│       ├── api/client.js         fetch wrapper with timeout + error mapping
│       ├── hooks/                useApi (loading/error/data), useHashRoute
│       ├── components/           assistant/, common/, dashboard/
│       ├── pages/                Assistant, History, KnowledgeBase, Dashboard
│       ├── utils/format.js
│       └── styles/index.css
├── docs/                         submission documents (see section 18)
└── scripts/package-submission.ps1  builds the clean submission ZIP
```

## 6. Prerequisites

| Software | Version | Needed for |
|---|---|---|
| Windows | 10 or 11 | Instructions are written for Windows |
| [XAMPP](https://www.apachefriends.org/) | with PHP 8.1+ (tested with 8.2.12) | Apache + MySQL/MariaDB + phpMyAdmin |
| [Node.js](https://nodejs.org/) | 18 or newer (tested with 22.14) | Frontend (includes npm) |
| A modern browser | Chrome, Edge or Firefox | |
| [Ollama](https://ollama.com/download/windows) | recent version (0.5 or newer) | **Optional**, only for AI mode. Free; see [section 10](#10-optional-local-ai-with-ollama) |

The PHP extensions `pdo_mysql`, `mbstring` and `curl` are needed (`curl` only for AI mode). XAMPP enables all three by default. In XAMPP's `httpd.conf`, `mod_rewrite` must be loaded and `AllowOverride All` must be set for `htdocs`; both are XAMPP defaults.

## 7. Installation on Windows (XAMPP)

The examples below use the default XAMPP folder `C:\xampp`. If yours is elsewhere (e.g. `C:\xampp_new`), change the paths to match.

**Step 1 - Start Apache and MySQL**
Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**. Both should turn green.

**Step 2 - Place the project**
Unzip or copy the project anywhere, for example `C:\Users\<you>\Desktop\pinelabs project`.

**Step 3 - Make the backend available to Apache**
Apache serves files from `C:\xampp\htdocs`. Pick one option.

- *Option A (recommended): a directory junction.* Apache then serves the project folder directly and nothing is copied. Open **Command Prompt** and run (use your own project path):
  ```bat
  mklink /J C:\xampp\htdocs\pinelabs-api "C:\Users\<you>\Desktop\pinelabs project\backend"
  ```
  To remove it later, run `rmdir C:\xampp\htdocs\pinelabs-api`. This deletes only the link, not your files.
- *Option B: copy.* Copy the `backend` folder to `C:\xampp\htdocs\pinelabs-api`. You'll have to copy it again after any backend change.

**Step 4 - Create the backend configuration**
```bat
cd "C:\Users\<you>\Desktop\pinelabs project\backend"
copy .env.example .env
```
The defaults suit a standard XAMPP install (user `root`, empty password, demo mode). See [section 9](#9-configuration).

**Step 5 - Import the database.** See [section 8](#8-database-import).

**Step 6 - Check the backend**
Open <http://localhost/pinelabs-api/api/health>. You should see something like:
```json
{"status":"ok","database":"connected","kb_articles":15,"mode":"demo", ...}
```

**Step 7 - Install the frontend**
```bat
cd "C:\Users\<you>\Desktop\pinelabs project\frontend"
npm install
```

**Step 8 - Start it.** See [section 11](#11-starting-the-application). AI mode with Ollama is optional ([section 10](#10-optional-local-ai-with-ollama)); the app works without it.

## 8. Database import

### Using phpMyAdmin
1. Open <http://localhost/phpmyadmin>.
2. Don't select a database. Click the **Import** tab at the top; the scripts create and select the database themselves.
3. Click **Choose File** → select `database/01_schema.sql` → click **Import** (or **Go**) at the bottom. It creates the `troubleshoot_assistant` database and its 4 tables.
4. Repeat with `database/02_seed_knowledge_base.sql`. This adds 8 categories and 15 articles.
5. *(Optional)* Repeat with `database/03_sample_queries.sql`. This adds 10 **fictional** history records so the dashboard isn't empty. They are flagged `is_sample = 1`, labelled "Sample" in the UI, and can be excluded on the History and Dashboard pages.
6. In the left panel, open `troubleshoot_assistant` → `kb_articles` and check that there are 15 rows.

All three scripts are safe to import again:
- schema: `CREATE ... IF NOT EXISTS`;
- seed: articles are updated by `article_code`, not duplicated;
- sample script: replaces only rows with `is_sample = 1`.

### Using the command line (alternative)
```bat
cd "C:\Users\<you>\Desktop\pinelabs project"
C:\xampp\mysql\bin\mysql.exe -u root < database\01_schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < database\02_seed_knowledge_base.sql
C:\xampp\mysql\bin\mysql.exe -u root < database\03_sample_queries.sql
```
(Run these in **Command Prompt**. PowerShell doesn't support `<` redirection. If your MySQL root user has a password, add `-p`.)

### Schema overview
| Table | Purpose |
|---|---|
| `categories` | Issue categories (slug, name, description) |
| `kb_articles` | Articles. `symptoms`, `causes`, `steps` and `escalation_conditions` hold one item per line; `keywords` is a comma-separated list; `is_fictional`, `is_active` |
| `support_queries` | Each submitted issue: text, category, confidence, `answer_mode` (demo/ai), `ai_model`, full answer JSON, `escalation_recommended`, `status` (open/resolved/unresolved/escalated), outcome note, `is_sample`, timestamps |
| `query_sources` | The articles cited by each query, with rank and score |

### Adding knowledge-base articles
- **Quick:** in phpMyAdmin open `kb_articles` → **Insert**. Use a new `article_code` (e.g. `KB-016`), choose a `category_id` from `categories`, and write each symptom, cause, step and condition **on its own line**.
- **Repeatable:** copy one row in the `INSERT INTO kb_seed VALUES` block of `02_seed_knowledge_base.sql`, edit it, and import the file again.
- New articles are used straight away, with no restart. Good keywords matter most, because they carry the highest weight in retrieval.

## 9. Configuration

### Backend (`backend/.env`)
| Variable | Default | Meaning |
|---|---|---|
| `APP_TIMEZONE` | `Asia/Kolkata` | Timezone for timestamps (PHP and the MySQL session) |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:5173,http://127.0.0.1:5173` | Frontend origins the API accepts |
| `DB_HOST` / `DB_PORT` / `DB_NAME` | `127.0.0.1` / `3306` / `troubleshoot_assistant` | Database connection |
| `DB_USER` / `DB_PASS` | `root` / *(empty)* | XAMPP defaults. Change them if your MySQL has a password |
| `AI_PROVIDER` | `demo` | `demo` = no AI model; `ollama` = local AI mode |
| `OLLAMA_BASE_URL` | `http://127.0.0.1:11434` | Where Ollama is listening (its default address) |
| `OLLAMA_MODEL` | `llama3.2:3b` | Model name; must be downloaded first with `ollama pull` |
| `OLLAMA_NUM_CTX` | `8192` | Context window in tokens (the prompt with 3 articles needs about 2,500) |
| `OLLAMA_NUM_PREDICT` | `1024` | Maximum tokens the model may generate per answer |
| `AI_TIMEOUT_SECONDS` | `120` | After this, the template answer is used instead |

Real environment variables take priority over `.env`. `.env` is git-ignored and is blocked from web access by `backend/.htaccess` (requests get **403 Forbidden**). Changes take effect on the next request; Apache doesn't need a restart.

### Frontend (`frontend/.env`, optional)
If the backend URL isn't the default `http://localhost/pinelabs-api/api`, copy `frontend/.env.example` to `frontend/.env` and set `VITE_API_BASE_URL`. Don't put secrets in frontend files, because anything in the frontend is visible in the browser.

## 10. Optional: local AI with Ollama

> ⚠️ **Not yet tested with a real Ollama installation.** The integration was verified against `backend/tests/mock_ollama_server.php`, a local imitation of Ollama's `/api/chat` endpoint. Real answer quality and speed depend on the model and your computer, and haven't been measured.

Ollama runs open-source language models on your own PC. Nothing is sent to the internet when you ask questions, and there is no API key or usage cost. You only download the model once.

### 10.1 Check your computer
| Model | Download size | Recommended RAM | Notes |
|---|---|---|---|
| `llama3.2:1b` | about 1.3 GB | 8 GB | Fastest; weakest answers |
| **`llama3.2:3b`** (default) | about 2.0 GB | 8 GB or more | Good balance for most laptops |
| `qwen2.5:7b` or `llama3.1:8b` | about 4.7–4.9 GB | 16 GB | Better answers, slower on a CPU |

No graphics card is needed; Ollama runs on the CPU. On a typical laptop CPU, expect **roughly 15–90 seconds per answer**, and longer for the first question while the model loads. Close other heavy programs if memory is tight.

### 10.2 Install Ollama
1. Go to <https://ollama.com/download/windows> and download **OllamaSetup.exe**.
2. Run it and follow the installer. Ollama starts automatically in the background (look for the llama icon in the system tray) and listens on `http://127.0.0.1:11434`.
3. Open a **new** Command Prompt and check:
   ```bat
   ollama --version
   ```
   Then open <http://127.0.0.1:11434> in your browser. It should say **"Ollama is running"**.

### 10.3 Download the model
```bat
ollama pull llama3.2:3b
```
This downloads about 2 GB once. Check that it's installed:
```bat
ollama list
```
Optional quick test (type `/bye` to exit):
```bat
ollama run llama3.2:3b "Reply with one word: ready"
```

### 10.4 Switch the app to AI mode
1. Open `backend\.env` in a text editor and change:
   ```
   AI_PROVIDER=ollama
   OLLAMA_MODEL=llama3.2:3b
   ```
   (If you downloaded a different model, put its exact name from `ollama list`.)
2. Save the file. No restart is needed.
3. Check <http://localhost/pinelabs-api/api/health>. It should show `"mode":"ai","ai_provider":"ollama","ai_model":"llama3.2:3b"`.
4. Reload the frontend. The header badge should read **"AI mode · Ollama llama3.2:3b"**.
5. Click the **UPI** example and submit. While it works, the page says it is asking the local AI model. The answer should carry an **"AI-assisted · llama3.2:3b"** badge.

To go back to demo mode, set `AI_PROVIDER=demo` again.

### 10.5 Troubleshooting
If the answer shows **"Template answer · AI unavailable"**, the yellow notice explains why. More detail is in `backend\storage\logs\app.log`.

| Message | What to do |
|---|---|
| "Ollama is not running…" | Start **Ollama** from the Start menu (or run `ollama serve`), then try again |
| "The model "…" is not installed in Ollama. Run: ollama pull …" | Run the shown `ollama pull` command, or fix `OLLAMA_MODEL` to match `ollama list` |
| "The local AI model did not answer in time." | Increase `AI_TIMEOUT_SECONDS` (e.g. `240`), use a smaller model, or close other programs |
| "…not valid JSON" / "…did not reference any valid knowledge-base steps" | The model's answer couldn't be used safely. Try again, or use a larger model (e.g. `qwen2.5:7b`) |
| "The AI response was cut off…" | Increase `OLLAMA_NUM_PREDICT` (e.g. `2048`) |

### 10.6 How AI mode stays grounded
- The model is only called when retrieval has **medium or high** confidence. Weak or unknown issues get the escalation answer without any AI call.
- The model receives the issue (marked as data, not instructions) and the retrieved articles with numbered items. Through Ollama's structured-output `format` option, it must answer in a fixed JSON format and select steps and causes **by article code and item number**.
- The backend checks every reference, discards unknown ones, and copies the item text from the database. The model writes only the summary, the next action and an optional note for the agent.
- `temperature` is set to 0 for consistent answers.
- Any problem (Ollama not running, missing model, timeout, cut-off or invalid output, invalid references) falls back to the template answer with the reason shown. A failed call is never shown as an AI answer.

## 11. Starting the application

1. **XAMPP Control Panel:** start **Apache** and **MySQL**.
2. *(AI mode only)* Make sure **Ollama** is running (tray icon, or <http://127.0.0.1:11434> says "Ollama is running").
3. **Frontend dev server:**
   ```bat
   cd "C:\Users\<you>\Desktop\pinelabs project\frontend"
   npm run dev
   ```
4. Open **<http://localhost:5173>**. Pages: Assistant, History, Knowledge base, Dashboard.

To stop, press `Ctrl+C` in the terminal, and stop Apache and MySQL in XAMPP.

**Without Apache (alternative):** run the backend on PHP's built-in server and point the frontend at it:
```bat
C:\xampp\php\php.exe -S localhost:8000 "backend\public\index.php"
```
Then create `frontend/.env` with `VITE_API_BASE_URL=http://localhost:8000/api` and restart `npm run dev`.

**Production build (optional):** `npm run build` creates `frontend/dist`. If you serve it from another address, for example `npm run preview` on port 4173, add that origin to `CORS_ALLOWED_ORIGINS`.

## 12. Demo mode

Demo mode is the default (`AI_PROVIDER=demo`). In demo mode:
- **No AI model is used.** The header shows **"Demo mode"** and every answer carries a **"Demo mode · no AI call"** badge plus a notice saying the answer was assembled from knowledge-base articles using fixed templates.
- The summary is a template sentence ("The reported issue matches KB-005 … based on the terms …"). It doesn't paraphrase the issue the way an AI would.
- Causes, steps and escalation conditions are copied from the best-matching article.
- Retrieval, confidence, escalation, history, outcomes and analytics work exactly as in AI mode.

Suggested demo script (5 minutes):
1. Click the **UPI** example → high confidence, KB-005 steps, sources. Tick two steps → **Resolved**.
2. Click the **GST** example → low confidence, no steps, "Escalation recommended" → **Copy escalation note**.
3. Type `How do I apply for annual leave?` → **Unsupported**.
4. **History** → filter by Resolved → open a query.
5. **Dashboard** → untick "Include sample records" to see only your real queries.

## 13. Testing

All test scripts are plain PHP and use the XAMPP PHP executable (adjust the path if needed). Run them from the project folder.

| Test | Command | Needs | What it checks |
|---|---|---|---|
| Retrieval | `C:\xampp\php\php.exe backend\tests\retrieval_test.php` | MySQL | 27 issue descriptions → expected article and confidence (in-scope, vague and out-of-scope). Read-only |
| API end-to-end | `C:\xampp\php\php.exe backend\tests\api_test.php` | Apache + MySQL | 45 checks: answers, escalation, validation (empty/short/long/non-text/bad JSON/wrong content type/SQL-like input), 404/405, status updates, filters, analytics vs direct SQL counts, CORS. **Deletes the queries it creates** |
| AI mode (Ollama mock) | `C:\xampp\php\php.exe backend\tests\ai_mock_test.php` | MySQL; ports 8096–8098 free. **Ollama not required** | Starts a mock Ollama API and AI-mode backends, then makes 33 checks: request format, verbatim KB steps, invalid references dropped, "not applicable", 7 failure scenarios plus "Ollama not running" each falling back with a reason, the timeout, no AI call on low confidence. Deletes its queries |
| Frontend build | `cd frontend` then `npm run build` | Node | The React app compiles |

If the API test's backend isn't at the default URL, pass it as an argument: `php backend\tests\api_test.php http://localhost:8000`.

**Manual UI checklist** (do these in the browser):
- [ ] Submitting an empty issue shows "Please describe the issue."
- [ ] The UPI example → High confidence, KB-005 as primary source, 6 steps; ticking steps updates "x of 6 done".
- [ ] **Resolved** → green success message; the status badge changes to Resolved.
- [ ] "Open article" opens KB-005 expanded on the Knowledge base page.
- [ ] The GST example → Low confidence, no steps, "Escalation recommended"; **Copy escalation note** shows "copied".
- [ ] History: newest first; Status filter; search with no match shows the empty state; "Include sample records" toggle.
- [ ] The dashboard total matches the number of queries in History (with the same sample setting); hover a column to see its tooltip.
- [ ] Stop MySQL in XAMPP and reload the page → the header shows "Database offline" and History/Dashboard show "The database is currently unavailable…" with **Retry**. Start MySQL again and click **Retry** → the data loads.
- [ ] Narrow the browser to phone width → no sideways scrolling; history rows become cards.
- [ ] *(AI mode, if Ollama is installed)* The UPI example gives an "AI-assisted" answer. Quit Ollama and submit again → "Template answer · AI unavailable" with "Ollama is not running".

Results from the most recent run are in [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md).

## 14. API reference

Base URL: `http://localhost/pinelabs-api/api`. All responses are JSON. Errors look like `{"error": {"code": "...", "message": "...", "fields": {...}}}`.

| Method | Path | Description |
|---|---|---|
| GET | `/health` | Backend, database and AI-mode status (`mode`, `ai_provider`, `ai_model`) |
| GET | `/categories` | Categories with article counts |
| GET | `/articles?search=&category=` | List or search articles |
| GET | `/articles/{code}` | One article, e.g. `KB-005` |
| POST | `/troubleshoot` | Body `{"issue_text": "...", "category": "optional-slug"}`. Returns the answer, sources, confidence, mode and `query_id` |
| GET | `/queries?status=&category=&search=&include_sample=1&page=1&per_page=10` | Query history (per_page ≤ 50; `category=uncategorised` is supported) |
| GET | `/queries/{id}` | One query with its stored answer |
| PATCH | `/queries/{id}` | Body `{"status": "resolved|unresolved|escalated|open", "note": "optional"}` |
| GET | `/analytics?include_sample=1&days=14` | Dashboard metrics (days 7–90) |

Status codes used: 200, 204 (CORS preflight), 400 (bad JSON), 404, 405, 413, 415, 422 (validation, with per-field messages), 500 (generic message; details only in the log), 503 (database unavailable).

## 15. Known limitations

- **Original assignment document not reviewed.** Compliance with the official brief hasn't been checked; see the provisional checklist.
- **The Ollama integration hasn't been tested with a real Ollama install.** Tested only against a local mock. Answer quality and speed depend on the model and hardware. Small local models (1–3B) may sometimes return unusable output, which is then replaced by the template answer.
- **Retrieval is keyword-based.** There are no embeddings or semantic search, so synonyms not listed in an article's keywords may be missed; short phrasings such as "customer wants refund" can rate lower than they should. Thresholds were tuned on the 15 sample articles. English only.
- **All articles are loaded per request.** That's fine for hundreds of articles. For thousands, use MySQL FULLTEXT search or a search service.
- **No authentication or user accounts.** Agent identity isn't recorded and there's no rate limiting. This is meant for local use only.
- **No admin UI for the knowledge base.** Articles are managed with SQL or phpMyAdmin.
- **Queries can't be deleted from the UI.**
- The dark theme follows the operating-system setting; there's no manual toggle.
- Browser testing: automated checks ran in headless Microsoft Edge (Chromium) on Windows, including a 390 px mobile viewport and dark mode. Firefox, Safari and real mobile devices weren't tested.
- The resolution rate is *resolved ÷ queries with a recorded outcome*. Open queries are left out on purpose.
- The PHP built-in server option handles one request at a time. Use Apache for normal use.

## 16. Screenshots to capture

Save these in a `docs/screenshots/` folder before submitting (light theme, browser at about 1280 px wide unless noted):

1. `01-assistant-high-confidence.png` - UPI example answer: badges, steps, sources.
2. `02-assistant-escalation.png` - GST example: low confidence, "Escalation recommended", information to collect.
3. `03-assistant-unsupported.png` - an out-of-scope question ("annual leave").
4. `04-outcome-recorded.png` - after clicking **Resolved** (success message visible).
5. `05-history.png` - history list with filters.
6. `06-history-detail.png` - a query detail page with its outcome note.
7. `07-knowledge-base.png` - knowledge-base page with one article expanded.
8. `08-dashboard.png` - full dashboard with sample records included (the banner visible).
9. `09-dashboard-real-only.png` - dashboard with sample records excluded.
10. `10-mobile-assistant.png` - assistant at phone width (browser dev tools, 390 px).
11. `11-dark-mode.png` - any page with Windows dark mode enabled.
12. `12-health-endpoint.png` - <http://localhost/pinelabs-api/api/health> in the browser.
13. `13-phpmyadmin-tables.png` - the four tables in phpMyAdmin.
14. `14-tests-passing.png` - terminal output of the three PHP test scripts.
15. *(Only if you set up Ollama)* `15-ai-mode-ollama.png` - an "AI-assisted · llama3.2:3b" answer, and `16-ollama-list.png` - the output of `ollama list`.

## 17. Preparing the submission ZIP

The packaging script builds a clean ZIP and **refuses to finish if it finds secrets**:

```bat
cd "C:\Users\<you>\Desktop\pinelabs project"
powershell -ExecutionPolicy Bypass -File scripts\package-submission.ps1
```

This creates `submission\troubleshooting-assistant.zip` (the `submission` folder is itself excluded from the package). The script:
- **excludes** `node_modules`, `dist`, `.git`, `backend/.env`, `frontend/.env*` (but keeps the `.env.example` files), `*.log`, test runtime state (`backend/tests/.mock_state`) and earlier ZIPs;
- **checks** the staged files and stops if it finds a `.env` file or a non-empty `DB_PASS` value;
- uses forward-slash paths, so the ZIP extracts correctly on Windows, macOS and Linux;
- uses only built-in Windows tools (no downloads).

**Ollama models are not part of the project.** They live in Ollama's own folder (`%USERPROFILE%\.ollama`) and are never added to the ZIP. An evaluator who wants AI mode follows section 10.

Before running it:
1. Fill in `docs/AI_ASSISTANCE.md` with your own contributions, and review the other `docs/` files.
2. Add your screenshots to `docs/screenshots/`.
3. Run the tests (section 13) and update `docs/TEST_REPORT.md` if anything changed.
4. Put the original assignment document in the project if the instructions allow it, and update the checklist against it.

After creating it, open the ZIP and check that `backend/.env` and `node_modules` are not inside. The evaluator runs `npm install` (in `frontend`) and copies `backend/.env.example` to `.env`, as in section 7.

## 18. Further documentation

| Document | Contents |
|---|---|
| [`docs/REQUIREMENTS_CHECKLIST.md`](docs/REQUIREMENTS_CHECKLIST.md) | **Provisional** requirement → implementation → test mapping |
| [`docs/FEATURE_STATUS.md`](docs/FEATURE_STATUS.md) | Completed, partial and not-done features |
| [`docs/TECHNICAL_DECISIONS.md`](docs/TECHNICAL_DECISIONS.md) | Key technical decisions and trade-offs |
| [`docs/TEST_REPORT.md`](docs/TEST_REPORT.md) | Latest test results and what remains untested |
| [`docs/PRESENTATION_OUTLINE.md`](docs/PRESENTATION_OUTLINE.md) | 10-minute presentation plan |
| [`docs/EVALUATION_QA.md`](docs/EVALUATION_QA.md) | Likely evaluation questions with honest answers |
| [`docs/AI_ASSISTANCE.md`](docs/AI_ASSISTANCE.md) | Which development tasks used AI assistance |
