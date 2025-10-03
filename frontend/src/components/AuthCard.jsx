export default function AuthCard({ title, children, footer }) {
  return (
    <div className="auth-wrap">
      <div className="card shadow-sm border-0 auth-card">
        <div className="card-body p-4 p-md-5">
          <h1 className="h4 mb-4">{title}</h1>
          {children}
          {footer ? (
            <div className="mt-3 small text-center">{footer}</div>
          ) : null}
        </div>
      </div>
    </div>
  );
}
