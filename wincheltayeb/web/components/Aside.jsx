import Link from "next/link";
import CallActions from "./CallActions";
import { postUrl } from "@/lib/site";

export default function Aside({ settings, title = "محتاج ونش دلوقتي؟", links = [], linksTitle }) {
  return (
    <aside className="aside">
      <div className="aside-call">
        <p className="aside-title">{title}</p>
        <p>شغالين 24 ساعة في كل محافظة السويس. السعر النهائي بيتقالك قبل ما نتحرك.</p>
        <CallActions settings={settings} compact />
      </div>
      {links.length > 0 && (
        <nav className="aside-links" aria-label={linksTitle}>
          <p className="aside-title">{linksTitle}</p>
          <ul>{links.map((p) => <li key={p.slug}><Link href={postUrl(p)}>{p.place_name || p.title}</Link></li>)}</ul>
        </nav>
      )}
    </aside>
  );
}
