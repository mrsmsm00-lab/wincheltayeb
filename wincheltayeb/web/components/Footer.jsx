import Link from "next/link";
import { telHref, waHref, postUrl } from "@/lib/site";

export default function Footer({ settings, services, areas }) {
  const year = new Date().getFullYear();
  return (
    <footer className="site-footer">
      <div className="wrap footer-grid">
        <div>
          <p className="footer-brand">ونش إنقاذ الطيب</p>
          <p>ونش إنقاذ سيارات في محافظة السويس، شغال 24 ساعة. ونش ملاكي، نقل تقيل، موتوسيكلات، وخدمات طريق.</p>
          <p className="footer-phone"><a href={telHref(settings)}><bdi dir="ltr">{settings.phone}</bdi></a></p>
          <p><a href={waHref(settings)} target="_blank" rel="noopener">راسلنا على واتساب</a></p>
        </div>
        <div>
          <p className="footer-head">خدماتنا</p>
          <ul>{services.map((s) => <li key={s.slug}><Link prefetch={false} href={postUrl(s)}>{s.title}</Link></li>)}</ul>
        </div>
        <div className="footer-areas">
          <p className="footer-head">مناطق السويس</p>
          <ul>{areas.map((a) => <li key={a.slug}><Link prefetch={false} href={postUrl(a)}>{a.place_name || a.title}</Link></li>)}</ul>
        </div>
        <div>
          <p className="footer-head">روابط</p>
          <ul>
            <li><Link prefetch={false} href="/suez-map">خريطة التغطية</Link></li>
            <li><Link prefetch={false} href="/blog">نصايح ومقالات</Link></li>
            <li><Link prefetch={false} href="/gallery">صور وفيديوهات</Link></li>
            <li><Link prefetch={false} href="/about">مين إحنا</Link></li>
            <li><Link prefetch={false} href="/contact">كلمنا</Link></li>
            <li><a href="/sitemap.xml">خريطة الموقع</a></li>
            <li><a href="/llms.txt">llms.txt</a></li>
          </ul>
        </div>
      </div>
      <div className="wrap footer-bottom">
        <p>© {year} ونش إنقاذ الطيب. كل الحقوق محفوظة. بنخدم محافظة السويس والعين السخنة والجلالة ولحد الزعفرانة.</p>
      </div>
    </footer>
  );
}
