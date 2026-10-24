export default function Pagination({ pagination, onPageChange }) {
  if (!pagination || pagination.total_pages <= 1) return null;
  const { page, total_pages: totalPages, total } = pagination;
  return (
    <nav className="pagination" aria-label="Pagination">
      <button type="button" className="button button--secondary button--small" disabled={page <= 1} onClick={() => onPageChange(page - 1)}>
        Previous
      </button>
      <span className="pagination__info">Page {page} of {totalPages} · {total} queries</span>
      <button type="button" className="button button--secondary button--small" disabled={page >= totalPages} onClick={() => onPageChange(page + 1)}>
        Next
      </button>
    </nav>
  );
}
