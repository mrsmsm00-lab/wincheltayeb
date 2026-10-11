import Link from "next/link";
import { getData } from "@/lib/content";
import { telHref, postUrl } from "@/lib/site";
import { faqSchema } from "@/lib/schema";
import CallActions from "@/components/CallActions";
import SuezMap from "@/components/SuezMap";
import Faq from "@/components/Faq";
import JsonLd from "@/components/JsonLd";
import Markdown from "@/components/Markdown";
import { ServiceRows, AreaPlates, ArticleList } from "@/components/PostList";
import { toMapPoints } from "@/components/mapPoints";

export const revalidate = 600;

export async function generateMetadata() {
  const { settings } = await getData();
  const title = settings.home_meta_title?.trim();
  const description = settings.home_meta_description?.trim();
  return {
    ...(title ? { title } : {}),
    ...(description ? { description } : {}),
    alternates: { canonical: "/" },
  };
}

// {phone} في أي نص بيتحط مكانه رقم التليفون
const fill = (text, settings) => String(text || "").replaceAll("{phone}", settings.phone || "");
const BG = { white: "section", mist: "section section-mist", ink: "section section-ink", blue: "section section-blue" };

function SectionHead({ title, more_label, more_href, settings }) {
  if (!title && !more_label) return null;
  return (
    <div className="section-head">
      {title && <h2>{fill(title, settings)}</h2>}
      {more_label && more_href && <Link prefetch={false} href={more_href} className="more">{more_label}</Link>}
    </div>
  );
}

function Hero({ s, settings, areas }) {
  const rows = String(s.sign_rows || "").split(",").map((x) => x.trim()).filter(Boolean)
    .map((slug) => areas.find((a) => a.slug === slug)).filter(Boolean);
  const facts = String(s.facts || "").split("\n").map((x) => x.trim()).filter(Boolean);
  return (
    <section className="hero">
      <div className="wrap hero-inner">
        <div className="sign">
          <div className="sign-face">
            {s.kicker && <p className="sign-kicker">{fill(s.kicker, settings)}</p>}
            <h1>{fill(s.title || "ونش إنقاذ السويس", settings)}</h1>
            <a className="sign-phone" href={telHref(settings)} aria-label={`اتصل على ${settings.phone}`}>
              <bdi dir="ltr">{settings.phone}</bdi>
            </a>
            {rows.length > 0 && (
              <ul className="sign-rows" aria-label="أوقات وصول تقريبية">
                {rows.map((a) => (
                  <li key={a.slug}>
                    <Link prefetch={false} href={postUrl(a)}>{a.place_name || a.title}</Link>
                    <span>{a.eta}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
          <span className="pole" aria-hidden="true" />
          <span className="pole" aria-hidden="true" />
        </div>
        <div className="hero-copy">
          {s.lead && <p className="hero-lead">{fill(s.lead, settings)}</p>}
          {s.show_buttons !== false && <CallActions settings={settings} />}
          {facts.length > 0 && <ul className="hero-facts">{facts.map((f, i) => <li key={i}>{fill(f, settings)}</li>)}</ul>}
        </div>
      </div>
      <div className="road" aria-hidden="true" />
    </section>
  );
}

function Steps({ s, settings }) {
  const items = String(s.items || "").split("\n").map((l) => l.split("|").map((x) => x.trim())).filter((x) => x[0]);
  return (
    <>
      {s.title && <h2>{fill(s.title, settings)}</h2>}
      <ol className="steps">
        {items.map(([t, d], i) => <li key={i}><strong>{fill(t, settings)}</strong>{d && <span>{fill(d, settings)}</span>}</li>)}
      </ol>
    </>
  );
}

function GalleryTeaser({ items }) {
  if (!items.length) return null;
  return (
    <ul className="gallery">
      {items.map((g) => (
        <li key={g.id}>
          <Link prefetch={false} href="/gallery" className="gallery-link">
            <img src={g.kind === "image" ? g.url : g.thumb || "/og.png"} alt={g.title} loading="lazy" />
          </Link>
          <p>{g.title}</p>
        </li>
      ))}
    </ul>
  );
}

export default async function Home() {
  const { settings, posts, districts, gallery } = await getData();
  const services = posts.filter((p) => p.type === "service");
  const areas = posts.filter((p) => p.type === "area");
  const articles = posts.filter((p) => p.type === "blog").sort((a, b) => String(b.published_at).localeCompare(String(a.published_at)));
  const sections = (settings.home_sections || []).filter((s) => s && s.enabled);
  let h1Done = false;
  let faqUsed = false;

  const render = (s) => {
    const bg = BG[s.bg] || "section";
    const id = s.type === "custom" && s.anchor ? s.anchor : s.id;
    switch (s.type) {
      case "hero":
        if (h1Done) return null; // H1 واحد بس في الصفحة
        h1Done = true;
        return <Hero key={s.id} s={s} settings={settings} areas={areas} />;
      case "services":
        return (
          <section key={s.id} id={id} className={bg}><div className="wrap">
            <SectionHead {...s} settings={settings} />
            <ServiceRows services={services} />
          </div></section>
        );
      case "map":
        return (
          <section key={s.id} id={id} className={bg}><div className="wrap">
            <SectionHead {...s} settings={settings} />
            {s.lead && <p className="section-lead">{fill(s.lead, settings)}</p>}
            <SuezMap points={toMapPoints(areas, districts)} districts={districts} height={Number(s.height) || 480} />
          </div></section>
        );
      case "areas": {
        const list = Number(s.limit) > 0 ? areas.slice(0, Number(s.limit)) : areas;
        return (
          <section key={s.id} id={id} className={bg}><div className="wrap">
            <SectionHead title={s.title} more_label={Number(s.limit) > 0 ? "كل المناطق" : ""} more_href="/areas" settings={settings} />
            <AreaPlates areas={list} />
          </div></section>
        );
      }
      case "steps":
        return <section key={s.id} id={id} className={bg}><div className="wrap"><Steps s={s} settings={settings} /></div></section>;
      case "articles":
        if (!articles.length) return null;
        return (
          <section key={s.id} id={id} className={bg}><div className="wrap">
            <SectionHead {...s} settings={settings} />
            <ArticleList posts={articles.slice(0, Number(s.count) || 3)} />
          </div></section>
        );
      case "gallery": {
        const items = gallery.slice(0, Number(s.count) || 6);
        if (!items.length) return null;
        return (
          <section key={s.id} id={id} className={bg}><div className="wrap">
            <SectionHead {...s} settings={settings} />
            <GalleryTeaser items={items} />
          </div></section>
        );
      }
      case "faq":
        if (!settings.general_faqs?.length) return null;
        faqUsed = true;
        return <section key={s.id} id={id} className={bg}><div className="wrap narrow"><Faq items={settings.general_faqs} title={fill(s.title, settings) || undefined} /></div></section>;
      case "cta":
        return (
          <section key={s.id} id={id} className={`cta-band cta-${s.bg || "blue"}`}>
            <div className="wrap cta-inner">
              {s.text && <p>{fill(s.text, settings)}</p>}
              <CallActions settings={settings} />
            </div>
          </section>
        );
      case "custom": {
        const width = s.width === "narrow" ? "wrap narrow" : s.width === "full" ? "wrap-full" : "wrap";
        return (
          <section key={s.id} id={id} className={`${bg} custom-section`}>
            <div className={width}>
              {s.title && <h2>{fill(s.title, settings)}</h2>}
              <Markdown source={fill(s.body, settings)} format={s.format} className="prose prose-wide" />
            </div>
          </section>
        );
      }
      default:
        return null;
    }
  };

  const body = sections.map(render);
  return (
    <>
      {!h1Done && <h1 className="sr-only">{settings.site_name}: ونش إنقاذ السويس 24 ساعة</h1>}
      {body}
      {faqUsed && <JsonLd data={faqSchema(settings.general_faqs)} />}
    </>
  );
}
