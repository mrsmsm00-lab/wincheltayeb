import Link from "next/link";
import Icon from "./Icon";
import MobileNav from "./MobileNav";
import { NAV } from "./nav";
import { telHref } from "@/lib/site";

export default function Header({ settings }) {
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
          <img src="/logo.svg" alt="" width="44" height="44" />
          <span className="brand-text">
            <strong>ونش إنقاذ الطيب</strong>
            <small>ونش إنقاذ السويس</small>
          </span>
        </Link>
        <nav className="main-nav" aria-label="القائمة الرئيسية">
          <ul>
            {NAV.slice(1).map((n) => (
              <li key={n.href}><Link href={n.href}>{n.label}</Link></li>
            ))}
          </ul>
        </nav>
        <MobileNav items={NAV} />
      </div>
    </header>
  );
}
