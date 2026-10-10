import Link from "next/link";

export default function Breadcrumbs({ items }) {
  return (
    <nav aria-label="مسار الصفحة" className="crumbs">
      <ol>
        {items.map((it, i) => (
          <li key={it.href}>
            {i < items.length - 1 ? <Link href={it.href}>{it.name}</Link> : <span aria-current="page">{it.name}</span>}
          </li>
        ))}
      </ol>
    </nav>
  );
}
