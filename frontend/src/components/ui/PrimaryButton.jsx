export default function PrimaryButton({ children, className = "", ...props }) {
  return (
    <button {...props} className={`btn btn-primary ${className}`}>
      {children}
    </button>
  );
}
