import Link from "next/link";
import { getData } from "@/lib/content";
import { telHref } from "@/lib/site";
import { faqSchema } from "@/lib/schema";
import CallActions from "@/components/CallActions";
import SuezMap from "@/components/SuezMap";
import Faq from "@/components/Faq";
import JsonLd from "@/components/JsonLd";
import { ServiceRows, AreaPlates, ArticleList } from "@/components/PostList";
import { toMapPoints } from "@/components/mapPoints";

export const revalidate = 600;

const SIGN_ROWS = ["suez-downtown", "arbaeen", "ain-sokhna", "suez-cairo-desert-road"];

export default async function Home() {
  const { settings, posts, districts } = await getData();
  const services = posts.filter((p) => p.type === "service");
  const areas = posts.filter((p) => p.type === "area");
  const articles = posts.filter((p) => p.type === "blog").sort((a, b) => String(b.published_at).localeCompare(String(a.published_at))).slice(0, 3);
  const signRows = SIGN_ROWS.map((s) => areas.find((a) => a.slug === s)).filter(Boolean);

  return (
    <>
      <section className="hero">
        <div className="wrap hero-inner">
          <div className="sign">
            <div className="sign-face">
              <p className="sign-kicker">ونش إنقاذ الطيب</p>
              <h1>ونش إنقاذ السويس شغال 24 ساعة</h1>
              <a className="sign-phone" href={telHref(settings)} aria-label={`اتصل على ${settings.phone}`}>
                <bdi dir="ltr">{settings.phone}</bdi>
              </a>
              <ul className="sign-rows" aria-label="أوقات وصول تقريبية">
                {signRows.map((a) => (
                  <li key={a.slug}>
                    <Link href={`/areas/${a.slug}`}>{a.place_name}</Link>
                    <span>{a.eta}</span>
                  </li>
                ))}
              </ul>
            </div>
            <span className="pole" aria-hidden="true" />
            <span className="pole" aria-hidden="true" />
          </div>
          <div className="hero-copy">
            <p className="hero-lead">
              عربيتك عطلت أو عملت حادثة في أي حتة في محافظة السويس؟ ابعتلنا لوكيشن، هنقولك السعر النهائي ووقت الوصول، والسطحة تتحرك لك على طول.
            </p>
            <CallActions settings={settings} />
            <ul className="hero-facts">
              <li>سعر نهائي في التليفون قبل ما الونش يتحرك</li>
              <li>سطحات هيدروليك للملاكي والأوتوماتيك</li>
              <li>من جنيفة في الشمال للجلالة في الجنوب</li>
            </ul>
          </div>
        </div>
        <div className="road" aria-hidden="true" />
      </section>

      <section className="section">
        <div className="wrap">
          <div className="section-head">
            <h2>خدمات ونش الطيب في السويس</h2>
            <Link href="/services" className="more">كل الخدمات</Link>
          </div>
          <ServiceRows services={services} />
        </div>
      </section>

      <section className="section section-mist" id="map">
        <div className="wrap">
          <div className="section-head">
            <h2>خريطة التغطية في محافظة السويس</h2>
            <Link href="/suez-map" className="more">افتح الخريطة كاملة</Link>
          </div>
          <p className="section-lead">دوس على أي نقطة تعرف وقت الوصول وتقرا عن خدمة الونش في المنطقة دي.</p>
          <SuezMap points={toMapPoints(areas, districts)} districts={districts} height={480} />
        </div>
      </section>

      <section className="section">
        <div className="wrap">
          <div className="section-head"><h2>بنوصل فين في السويس؟</h2></div>
          <AreaPlates areas={areas} />
        </div>
      </section>

      <section className="section section-ink">
        <div className="wrap">
          <h2>تطلب الونش إزاي</h2>
          <ol className="steps">
            <li><strong>اتصل أو ابعت واتساب</strong><span>على <bdi dir="ltr">{settings.phone}</bdi> في أي وقت.</span></li>
            <li><strong>ابعت اللوكيشن</strong><span>من الموبايل، وقولنا رايح فين.</span></li>
            <li><strong>خد السعر النهائي</strong><span>ووقت الوصول قبل ما نتحرك.</span></li>
            <li><strong>الونش يوصل</strong><span>يصوّر العربية قدامك ويحمّلها على السطحة.</span></li>
          </ol>
        </div>
      </section>

      <section className="section">
        <div className="wrap">
          <div className="section-head">
            <h2>نصايح لأصحاب العربيات في السويس</h2>
            <Link href="/blog" className="more">كل المقالات</Link>
          </div>
          <ArticleList posts={articles} />
        </div>
      </section>

      <section className="section section-mist">
        <div className="wrap narrow">
          <Faq items={settings.general_faqs} />
        </div>
      </section>

      <section className="cta-band">
        <div className="wrap cta-inner">
          <p>واقف دلوقتي؟ متستناش.</p>
          <CallActions settings={settings} />
        </div>
      </section>
      <JsonLd data={faqSchema(settings.general_faqs)} />
    </>
  );
}
