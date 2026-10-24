import { useEffect } from 'react';
import { api, API_BASE_URL } from './api/client.js';
import Icon from './components/common/Icon.jsx';
import { useApi } from './hooks/useApi.js';
import { navigate, useHashRoute } from './hooks/useHashRoute.js';
import AssistantPage from './pages/AssistantPage.jsx';
import DashboardPage from './pages/DashboardPage.jsx';
import HistoryPage from './pages/HistoryPage.jsx';
import KnowledgeBasePage from './pages/KnowledgeBasePage.jsx';

const NAV_ITEMS = [
  { page: 'assistant', label: 'Assistant', icon: 'chat' },
  { page: 'history', label: 'History', icon: 'history' },
  { page: 'knowledge-base', label: 'Knowledge base', icon: 'book' },
  { page: 'dashboard', label: 'Dashboard', icon: 'chart' },
];

const PAGE_TITLES = {
  assistant: 'Assistant',
  history: 'Query history',
  'knowledge-base': 'Knowledge base',
  dashboard: 'Dashboard',
};

function BackendStatus({ health, error, onRetry }) {
  if (error) {
    return (
      <button type="button" className="backend-status backend-status--down" onClick={onRetry} title={`${error.message} (${API_BASE_URL})`}>
        <span className="dot" aria-hidden="true" /> Backend offline · retry
      </button>
    );
  }
  if (!health) return <span className="backend-status"><span className="dot" aria-hidden="true" /> Connecting…</span>;
  const degraded = health.database !== 'connected';
  return (
    <span className={`backend-status ${degraded ? 'backend-status--down' : 'backend-status--ok'}`} title={degraded ? 'MySQL is not reachable' : `${health.kb_articles} knowledge-base articles`}>
      <span className="dot" aria-hidden="true" />
      {degraded ? 'Database offline' : health.mode === 'ai' ? `AI mode · Ollama ${health.ai_model}` : 'Demo mode'}
    </span>
  );
}

export default function App() {
  const { page, param } = useHashRoute();
  const health = useApi((options) => api.health(options), []);
  const categories = useApi((options) => api.categories(options), []);

  useEffect(() => {
    document.title = `${PAGE_TITLES[page]} · Troubleshooting Assistant`;
  }, [page]);

  const categoryItems = categories.data?.items || [];

  return (
    <div className="app">
      <a className="skip-link" href="#main">Skip to content</a>
      <header className="topbar">
        <div className="topbar__inner">
          <a className="brand" href="#/assistant">
            <span className="brand__mark" aria-hidden="true"><Icon name="check" size={16} /></span>
            <span className="brand__text">
              <span className="brand__name">Troubleshooting Assistant</span>
              <span className="brand__tag">L1/L2 Technical Support</span>
            </span>
          </a>
          <nav className="nav" aria-label="Main">
            {NAV_ITEMS.map((item) => (
              <a
                key={item.page}
                href={`#/${item.page}`}
                className={`nav__link ${page === item.page ? 'is-active' : ''}`}
                aria-current={page === item.page ? 'page' : undefined}
              >
                <Icon name={item.icon} size={16} />
                <span>{item.label}</span>
              </a>
            ))}
          </nav>
          <BackendStatus health={health.data} error={health.error} onRetry={() => { health.reload(); categories.reload(); }} />
        </div>
      </header>

      <main id="main" className="main" tabIndex={-1}>
        {page === 'assistant' && <AssistantPage categories={categoryItems} aiMode={health.data?.mode === 'ai'} />}
        {page === 'history' && <HistoryPage categories={categoryItems} queryId={param} />}
        {page === 'knowledge-base' && <KnowledgeBasePage categories={categoryItems} articleCode={param} />}
        {page === 'dashboard' && <DashboardPage />}
      </main>

      <footer className="footer">
        Assignment prototype · Knowledge-base content is fictional sample data · Outcomes are recorded by support agents, never assumed.
        {' '}<button type="button" className="link-button" onClick={() => navigate('knowledge-base')}>Browse knowledge base</button>
      </footer>
    </div>
  );
}
