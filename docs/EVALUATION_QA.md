# Likely Evaluation Questions - with Honest Answers

## Product and approach

**Q: What problem does this solve, and for whom?**
L1/L2 support agents spend time searching for the right fix and sometimes guess or escalate at the wrong moment. The assistant finds the relevant article, presents ordered steps with their source, says clearly when it doesn't know, and makes escalation easy. Outcomes are recorded, so the team can see what gets resolved.

**Q: Where is the "AI" in demo mode?**
In demo mode there is no AI model. The answer is built by retrieval (a scoring algorithm) and templates, and the UI says so ("Demo mode · no AI call"). AI mode adds a **local open-source model run through Ollama** (default `llama3.2:3b`) for summarising and for choosing and ordering steps. I implemented it and tested it against a local mock of the Ollama API. **I haven't yet run it with a real Ollama installation**, so I don't claim more than that.

**Q: Why not just send the question to an LLM and show the answer?**
Because the model could invent a plausible but wrong procedure, which is dangerous in payments support. Here the AI can only point to steps that exist in the knowledge base, and the code copies the wording itself. If the knowledge base doesn't cover the issue, the answer is "escalate" rather than a guess.

**Q: How do you decide when to escalate?**
- *Low* or *unsupported* confidence always recommends escalation.
- For matched articles, the answer lists that article's escalation conditions and the team.
- In AI mode, the model can also recommend escalation, or judge that the articles don't apply.

The agent makes the final decision and records it.

## Retrieval

**Q: How does retrieval work?**
Text is normalised: lower-cased, stop words removed, simple stemming. Each article is scored by summing, for every query word it contains, the weight of the best field it appears in (keywords 3, title 2.5, symptoms 2, causes 1) times an IDF factor, so rare words count more. A bonus is added for multi-word keyword phrases such as "charged twice". The top three above a minimum score become the sources.

**Q: How did you choose the thresholds (6 / 12 / 18)?**
Empirically, with `retrieval_test.php`: 16 clear issues (one per article plus one variant), 5 short or partial ones, and 6 vague or out-of-scope ones. I also probed borderline phrases. Two problems found that way led to changes: generic words now don't count on their own, and "high" needs at least three matched terms. The thresholds are tuned for this knowledge base and would need re-checking as it grows.

**Q: What are the weaknesses of keyword retrieval?**
Synonyms and paraphrases that aren't in the keywords are missed, there is no typo tolerance, and it is English only. Next steps: richer keywords per article, MySQL FULLTEXT, then embeddings for semantic search.

## Data and backend

**Q: Walk me through the database design.**
Four tables:
- `categories`;
- `kb_articles` (multi-line fields hold one item per line, which keeps editing in phpMyAdmin simple);
- `support_queries` (issue, confidence, mode, full answer JSON, status, outcome note, `is_sample`, timestamps);
- `query_sources` (which articles were cited, with rank and score).

Foreign keys and indexes on status, category and created date support the dashboard queries.

**Q: Why store the full answer as JSON?**
So History shows exactly what the agent saw at the time, even if an article is edited later. The columns the dashboard needs (status, confidence, category, mode, escalation flag) are stored as normal columns so they can be counted efficiently.

**Q: How do you prevent SQL injection and XSS?**
Every query uses PDO prepared statements with emulation turned off. The only SQL built from strings is a fixed internal filter, never user input. Inputs are validated (length, enums, numeric IDs, slug format). React escapes all output. The API test sends a SQL-like category value and gets a 422.

**Q: How are errors handled?**
- Validation errors → 422 with per-field messages.
- Database down → 503 with a generic message.
- Unexpected errors → generic 500.
- `display_errors` is off, and details go to `storage/logs/app.log`.

The frontend shows a clear error message with Retry, and the header shows the backend/database status.

**Q: Are the dashboard numbers real?**
Yes. Each one is a live SQL `COUNT`/`SUM`. The API test compares them with independent SQL queries, with and without sample data. Sample records are fictional, flagged in the database, labelled in the UI, and can be excluded.

## AI integration

**Q: Which model and why?**
`llama3.2:3b`, run locally with Ollama; any Ollama model can be set with `OLLAMA_MODEL` in `.env`. I chose a local model so the project has no API key to protect, no usage cost, and no customer issue text leaves the machine, which matters for payments support data. The 3B model is about 2 GB and runs on an ordinary laptop CPU. The trade-off is answer quality and speed: small models are weaker and slower than large cloud models. That's acceptable here because the model only selects and summarises; the step text always comes from the knowledge base. A larger model (`qwen2.5:7b`, `llama3.1:8b`) can be used on a 16 GB machine.

**Q: Why switch from a cloud API to Ollama?**
Privacy (issue text stays local), zero running cost, and no secret key in the project. Evaluators can also run AI mode without an account.

**Q: How do you protect against prompt injection?**
- The issue text is wrapped in `<issue>` tags, and the system prompt says to treat it as data.
- More importantly, the design limits the damage: the model's output can only reference knowledge-base items, which are checked in code. A successful injection could at most produce a bad summary or a wrong selection of real steps, never a new instruction.

**Q: What happens if Ollama is slow, stopped, or the model is missing?**
cURL has a timeout (120 s by default, since local models on a CPU are slow). If Ollama isn't running, the model isn't downloaded, the request times out, or the output is cut off or invalid, the agent gets the template answer with an "AI unavailable: …" notice that says what to do (e.g. "Run: ollama pull llama3.2:3b"). The mock test covers each of these cases.

**Q: Are there any secrets to protect?**
Not for AI mode: Ollama runs locally without a key. The only configuration file is `backend/.env` (database settings), which is git-ignored, blocked over HTTP and excluded from the ZIP. The packaging script also refuses to package a `.env` file or a non-empty database password.

**Q: Did you test it with a real model?**
Not yet: Ollama wasn't installed on the development machine. It is tested end-to-end against a mock Ollama server that checks the request format and returns realistic successes and failures. The README gives the installation steps to run it for real.

## Quality and process

**Q: How did you test?**
Three re-runnable PHP test scripts with 105 checks in total (retrieval 27, API end-to-end 45, AI mode against a mock Ollama server 33), plus a production build. During development I also ran automated browser checks in headless Edge (desktop, 390 px mobile, dark mode) and reviewed screenshots. That script isn't in the repository, so the README has a manual UI checklist. Results are in `TEST_REPORT.md`.

**Q: What bugs did testing find?**
- "QR code not scanning" was rated high confidence because of generic words.
- Weakly matched answers showed unrelated steps (printer steps for a GST question).
- Weak matches assigned a wrong category, which would distort the analytics.
- Several UI layout issues.

All were fixed and covered by tests.

**Q: What would you do with more time?**
- A knowledge-base admin page.
- Authentication and agent identity on queries.
- Evaluate real Ollama models against a labelled test set to choose the best size.
- Better retrieval (FULLTEXT or embeddings).
- Request queueing for AI mode (a local model answers one request at a time).
- Automated browser tests in CI.

**Q: How much of this did you write yourself?**
See `AI_ASSISTANCE.md`. Answer honestly and specifically: which decisions you made, what you reviewed and changed, and what you can explain line by line.

**Q: Does it meet all the assignment requirements?**
I can't claim that yet. I didn't have the original assignment document; the checklist is based on my working brief and is marked provisional.
