import Link from "next/link";
import Icon from "./Icon";
import { postUrl } from "@/lib/site";

export function ServiceRows({ services }) {
  return (
    <ul className="service-rows">
      {services.map((s) => (
        <li key={s.slug}>
          <span className="plate-icon"><Icon name={s.icon || "car"} size={28} /></span>
          <div>
            <h3><Link prefetch={false} href={postUrl(s)}>{s.title}</Link></h3>
            <p>{s.excerpt}</p>
          </div>
        </li>
      ))}
    </ul>
  );
}

export function AreaPlates({ areas }) {
  return (
    <ul className="area-plates">
      {areas.map((a) => (
        <li key={a.slug}>
          <Link prefetch={false} href={postUrl(a)}>
            <span>{a.place_name || a.title}</span>
            {a.eta && <small>{a.eta}</small>}
          </Link>
        </li>
      ))}
    </ul>
  );
}

const fmt = (d) => {
  try { return new Intl.DateTimeFormat("ar-EG", { day: "numeric", month: "long", year: "numeric" }).format(new Date(d)); } catch { return d; }
};

export function ArticleList({ posts }) {
  return (
    <ul className="article-list">
      {posts.map((p) => (
        <li key={p.slug}>
          <time dateTime={p.published_at}>{fmt(p.published_at)}</time>
          <h3><Link prefetch={false} href={postUrl(p)}>{p.title}</Link></h3>
          <p>{p.excerpt}</p>
        </li>
      ))}
    </ul>
  );
}
export { fmt as formatDate };
