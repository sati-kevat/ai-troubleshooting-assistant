import { useEffect, useId, useRef, useState } from 'react';
import { api } from '../api/client.js';
import { FictionalBadge } from '../components/common/Badges.jsx';
import { EmptyState, ErrorMessage, LoadingState } from '../components/common/States.jsx';
import { useApi } from '../hooks/useApi.js';
import { navigate } from '../hooks/useHashRoute.js';

function ArticleSection({ title, items, ordered = false }) {
  const List = ordered ? 'ol' : 'ul';
  return (
    <section className="article__section">
      <h4 className="article__heading">{title}</h4>
      <List className={ordered ? 'numbered-list' : 'bullet-list'}>
        {items.map((item) => <li key={item}>{item}</li>)}
      </List>
    </section>
  );
}

function ArticleCard({ article, expanded, onToggle }) {
  const ref = useRef(null);
  useEffect(() => {
    if (expanded) ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [expanded]);

  return (
    <article ref={ref} className={`card article ${expanded ? 'is-expanded' : ''}`}>
      <button type="button" className="article__toggle" aria-expanded={expanded} onClick={onToggle}>
        <span className="source__code">{article.code}</span>
        <span className="article__title">{article.title}</span>
        <span className="article__category">{article.category.name}</span>
      </button>
      {expanded && (
        <div className="article__body">
          <div className="badge-row">
            {article.is_fictional && <FictionalBadge />}
            <span className="small muted">Escalation team: {article.escalate_to}</span>
          </div>
          <div className="article__grid">
            <ArticleSection title="Symptoms" items={article.symptoms} />
            <ArticleSection title="Possible causes" items={article.causes} />
          </div>
          <ArticleSection title="Troubleshooting steps" items={article.steps} ordered />
          <ArticleSection title="Escalate when" items={article.escalation_conditions} />
          <p className="small muted">Keywords: {article.keywords.join(', ')}</p>
        </div>
      )}
    </article>
  );
}

export default function KnowledgeBasePage({ categories, articleCode }) {
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const searchId = useId();
  const categoryId = useId();

  useEffect(() => {
    const timer = setTimeout(() => setSearch(searchInput.trim()), 300);
    return () => clearTimeout(timer);
  }, [searchInput]);

  const { data, error, loading, reload } = useApi((options) => api.articles({ search, category }, options), [search, category]);
  const selected = articleCode ? articleCode.toUpperCase() : null;

  return (
    <div className="page">
      <header className="page__header">
        <div>
          <h1 className="page__title">Knowledge base</h1>
          <p className="page__subtitle">The articles the assistant retrieves from. Every answer cites the article codes shown here.</p>
        </div>
      </header>

      <div className="alert alert--info">
        <div className="alert__body">
          <p className="alert__title">Fictional sample knowledge base</p>
          <p>
            These articles were written for this demonstration. They are not official Pine Labs documentation; menus, response codes,
            timelines and team names are illustrative. Add articles in <code>database/02_seed_knowledge_base.sql</code> or directly in the
            <code> kb_articles</code> table.
          </p>
        </div>
      </div>

      <div className="card filters" role="search">
        <div className="field field--inline">
          <label htmlFor={searchId} className="field__label field__label--small">Search articles</label>
          <input id={searchId} type="search" className="input" placeholder="Title, symptom, keyword or KB code" value={searchInput} onChange={(event) => setSearchInput(event.target.value)} />
        </div>
        <div className="field field--inline">
          <label htmlFor={categoryId} className="field__label field__label--small">Category</label>
          <select id={categoryId} className="select" value={category} onChange={(event) => setCategory(event.target.value)}>
            <option value="">All categories</option>
            {(categories || []).map((item) => <option key={item.slug} value={item.slug}>{item.name} ({item.article_count})</option>)}
          </select>
        </div>
      </div>

      <ErrorMessage error={error} onRetry={reload} title="Could not load the knowledge base" />
      {loading && !data && <LoadingState label="Loading articles…" />}
      {data && data.items.length === 0 && (
        <EmptyState title="No articles match your search">Try a different keyword or clear the category filter.</EmptyState>
      )}
      {data && data.items.length > 0 && (
        <>
          <p className="small muted">{data.total} article{data.total === 1 ? '' : 's'}</p>
          <div className="article-list">
            {data.items.map((article) => (
              <ArticleCard
                key={article.code}
                article={article}
                expanded={selected === article.code}
                onToggle={() => (selected === article.code ? navigate('knowledge-base') : navigate('knowledge-base', article.code))}
              />
            ))}
          </div>
        </>
      )}
    </div>
  );
}
