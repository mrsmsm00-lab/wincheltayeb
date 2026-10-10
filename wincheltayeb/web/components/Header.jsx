import Link from "next/link";
import Icon from "./Icon";
import MobileNav from "./MobileNav";
import { telHref } from "@/lib/site";

const isExternal = (href) => /^(https?:|tel:|mailto:)/i.test(href);

export function NavLink({ item, className }) {
  const cls = [className, item.style === "button" ? "nav-cta" : ""].filter(Boolean).join(" ") || undefined;
  const extra = item.new_tab ? { target: "_blank", rel: "noopener" } : {};
  if (isExternal(item.href) || item.href.startsWith("#")) return <a href={item.href} className={cls} {...extra}>{item.label}</a>;
  return <Link href={item.href} className={cls} {...extra}>{item.label}</Link>;
}

export default function Header({ settings }) {
  const menu = (settings.nav_menu || []).filter((m) => m.enabled !== false);
  const mobile = menu.some((m) => m.href === "/") ? menu : [{ label: "الرئيسية", href: "/" }, ...menu];
  return (
    <header className="site-header">
      <div className="topline">
        <div className="wrap topline-inner">
          <span><Icon name="clock" size={16} /> شغالين 24 ساعة في كل محافظة السويس</span>
          <a href={telHref(settings)} className="topline-phone"><Icon name="phone" size={16} /> <bdi dir="ltr">{settings.phone}</bdi></a>
        </div>
      </div>
      <div className="wrap header-inner">
        <Link href="/" className="brand" aria-label="ونش إنقاذ الطيب، الصفحة الرئيسية">
          <img src={/^(https?:\/\/|\/)[^\s"<>]*$/.test(settings.site_logo || "") ? settings.site_logo : "/logo.svg"} alt="" height="44" style={{ width: "auto", maxWidth: 160 }} />
          <span className="brand-text">
            <strong>{settings.site_name || "ونش إنقاذ الطيب"}</strong>
            <small>{settings.tagline || "ونش إنقاذ السويس"}</small>
          </span>
        </Link>
        <nav className="main-nav" aria-label="القائمة الرئيسية">
          <ul>
            {menu.map((n, i) => <li key={i}><NavLink item={n} /></li>)}
          </ul>
        </nav>
        <MobileNav items={mobile} />
      </div>
    </header>
  );
}
