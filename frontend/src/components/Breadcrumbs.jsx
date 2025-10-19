import { Link, useLocation } from "react-router-dom";

export default function Breadcrumbs() {
  const { pathname } = useLocation();
  const parts = pathname.split("/").filter(Boolean);

  const crumbs = [];
  let i = 0;

  while (i < parts.length) {
    const seg = parts[i];
    const isLast = (idx) => idx === parts.length - 1;

    // 1) Home
    if (i === 0 && seg !== "") {
      crumbs.push({ to: "/", label: "Home", last: false });
    }

    // 2) dashboard ide normalno
    if (seg === "dashboard") {
      crumbs.push({ to: "/dashboard", label: "dashboard", last: isLast(i) });
      i++;
      continue;
    }

    // 3) SPOJ: "plans" + numeric id => "plan {id}"
    if (seg === "plans" && /^\d+$/.test(parts[i + 1] || "")) {
      const id = parts[i + 1];
      const to = `/dashboard/plans/${id}`;
      const last = isLast(i + 1);
      crumbs.push({ to, label: `plan ${id}`, last });
      i += 2; // preskačemo i "plans" i "id"
      continue;
    }

    // 4) ostalo (create, edit, itd.)
    const pathUpToHere = "/" + parts.slice(0, i + 1).join("/");
    crumbs.push({ to: pathUpToHere, label: seg, last: isLast(i) });
    i++;
  }

  // render
  return (
    <nav
      aria-label="breadcrumb"
      className="bg-light px-4 py-2 mb-3 rounded-3 shadow-sm"
    >
      <ol className="breadcrumb mb-0 d-flex align-items-center gap-1">
        {crumbs.map((c, idx) => (
          <li
            key={idx}
            className={`breadcrumb-item ${c.last ? "active" : ""}`}
            aria-current={c.last ? "page" : undefined}
          >
            {c.last ? <span>{c.label}</span> : <Link to={c.to}>{c.label}</Link>}
          </li>
        ))}
      </ol>
    </nav>
  );
}
