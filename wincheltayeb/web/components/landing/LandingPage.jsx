import templates from "@/content/lp-templates.json";
import Markdown from "@/components/Markdown";
import JsonLd from "@/components/JsonLd";
import Icon from "@/components/Icon";
import GadsConversions from "@/components/GadsConversions";
import { faqSchema } from "@/lib/schema";

const fillText = (text, ctx) =>
  String(text || "").replaceAll("{keyword}", ctx.keyword).replaceAll("{area}", ctx.area).replaceAll("{phone}", ctx.phone);

export function landingTitle(lp, settings, keyword) {
  const d = lp.data || {};
  const hero = blocksOf(d).find((b) => b.type === "hero") || {};
  return fillText(hero.headline || d.headline || "ونش إنقاذ {area}", { keyword: keyword || d.keyword_default || "ونش إنقاذ", area: d.area_name || "السويس", phone: d.phone || settings.phone });
}

/** الصفحات القديمة (حقول ثابتة) بتتحول لأقسام */
export function blocksOf(d) {
  if (Array.isArray(d.blocks) && d.blocks.length) return d.blocks;
  const faqText = Array.isArray(d.faqs) ? d.faqs.map((f) => `س: ${f.q}\nج: ${f.a}`).join("\n") : String(d.faqs || "");
  const b = [{ id: "hero", type: "hero", enabled: true, badge: d.badge, headline: d.headline, subheadline: d.subheadline, eta: d.eta, image: d.hero_image, show_phone: true }];
  if (d.trust) b.push({ id: "trust", type: "trust", enabled: true, items: d.trust, bg: "alt" });
  if (d.services) b.push({ id: "services", type: "services", enabled: true, title: "خدماتنا في {area}", items: d.services });
  if (d.button_style === "hero") b.push({ id: "cta1", type: "cta", enabled: true, buttons: "both", size: "big", bg: "alt" });
  if (d.steps) b.push({ id: "steps", type: "steps", enabled: true, title: "تطلب الونش إزاي؟", items: d.steps, bg: "alt" });
  if (d.body) b.push({ id: "text1", type: "text", enabled: true, body: d.body, format: d.body_format });
  if (d.images) b.push({ id: "gallery1", type: "gallery", enabled: true, images: d.images });
  if (faqText.trim()) b.push({ id: "faq", type: "faq", enabled: true, title: "أسئلة سريعة", items: faqText, bg: "alt" });
  b.push({ id: "final", type: "final", enabled: true, text: d.final_cta || "واقف دلوقتي؟ متستناش" });
  return b;
}

function parseFaqs(text) {
  const out = []; let q = null;
  for (const raw of String(text || "").split("\n")) {
    const l = raw.trim(); if (!l) continue;
    const mq = l.match(/^(س|q)\s*[:：]\s*(.+)$/i), ma = l.match(/^(ج|a)\s*[:：]\s*(.+)$/i);
    if (mq) q = mq[2]; else if (ma && q) { out.push({ q, a: ma[2] }); q = null; } else if (out.length && !q) out[out.length - 1].a += " " + l;
  }
  return out;
}
const ytId = (u) => (String(u || "").match(/(?:youtu\.be\/|v=|embed\/|shorts\/|live\/)([A-Za-z0-9_-]{11})/) || [])[1] || null;
const isUrl = (u) => /^(https?:\/\/|\/)[^\s"<>]*$/.test(String(u || ""));
const lines = (t) => String(t || "").split("\n").map((x) => x.trim()).filter(Boolean);
const pairs = (t) => lines(t).map((l) => l.split("|").map((x) => x.trim()));
const intl = (p) => { const d = String(p).replace(/\D/g, ""); return d.startsWith("20") ? `+${d}` : d.startsWith("0") ? `+2${d}` : `+${d}`; };

// رسمة ونش بسيطة لما مفيش صورة
function TruckArt() {
  return (
    <svg className="lp-art" viewBox="0 0 320 170" role="img" aria-label="ونش سطحة">
      <rect x="0" y="150" width="320" height="6" rx="3" className="lp-art-road" />
      <path d="M20 112h170l18-30h52l30 30v22H20z" className="lp-art-bed" />
      <path d="M208 82h44l24 26h-68z" className="lp-art-glass" />
      <path d="M40 104l120-34" className="lp-art-line" />
      <rect x="60" y="70" width="90" height="30" rx="8" className="lp-art-car" />
      <circle cx="70" cy="136" r="16" className="lp-art-wheel" /><circle cx="250" cy="136" r="16" className="lp-art-wheel" /><circle cx="130" cy="136" r="16" className="lp-art-wheel" />
      <circle cx="70" cy="136" r="6" className="lp-art-hub" /><circle cx="250" cy="136" r="6" className="lp-art-hub" /><circle cx="130" cy="136" r="6" className="lp-art-hub" />
    </svg>
  );
}

function Buttons({ ctx, where, big = false, only = "both" }) {
  return (
    <div className={`lp-actions${big ? " lp-actions-big" : ""}`}>
      {only !== "wa" && <a className="lp-btn lp-btn-call" href={`tel:${ctx.tel}`} data-track-label={`${ctx.callLabel} • ${where}`}>
        <Icon name="phone" size={big ? 26 : 22} /><span><b>{ctx.callLabel}</b><small dir="ltr">{ctx.phone}</small></span>
      </a>}
      {only !== "call" && <a className="lp-btn lp-btn-wa" href={ctx.waHref} target="_blank" rel="noopener" data-track-label={`${ctx.waLabel} • ${where}`}>
        <Icon name="whatsapp" size={big ? 26 : 22} /><span><b>{ctx.waLabel}</b></span>
      </a>}
    </div>
  );
}

function Hero({ variant, d, ctx, title }) {
  const sub = fillText(d.subheadline, ctx);
  const showPhone = d.show_phone !== false;
  const badge = d.badge && <p className="lp-badge"><span className="lp-dot" aria-hidden="true" />{fillText(d.badge, ctx)}</p>;
  const eta = d.eta && <p className="lp-eta"><Icon name="clock" size={18} /> {fillText(d.eta, ctx)}</p>;
  const img = d.image || d.hero_image;
  switch (variant) {
    case "sign":
      return (
        <section className="lp-hero lp-hero-sign">
          <div className="lp-wrap lp-sign-grid">
            <div className="lp-sign">
              <div className="lp-sign-face">
                {badge}
                <h1>{title}</h1>
                {showPhone && <a className="lp-sign-phone" href={`tel:${ctx.tel}`} data-track-label={`رقم اللافتة • الواجهة`}><bdi dir="ltr">{ctx.phone}</bdi></a>}
                {eta}
              </div>
              <span className="lp-pole" /><span className="lp-pole" />
            </div>
            <div className="lp-hero-copy"><p className="lp-sub">{sub}</p><Buttons ctx={ctx} where="الواجهة" /></div>
          </div>
        </section>
      );
    case "split":
      return (
        <section className="lp-hero lp-hero-split">
          <div className="lp-wrap lp-split">
            <div className="lp-hero-copy">{badge}<h1>{title}</h1><p className="lp-sub">{sub}</p>{eta}<Buttons ctx={ctx} where="الواجهة" big /></div>
            <div className="lp-hero-media">{img ? <img src={img} alt={title} fetchPriority="high" /> : <TruckArt />}</div>
          </div>
        </section>
      );
    case "photo":
      return (
        <section className="lp-hero lp-hero-photo" style={img ? { "--lp-photo": `url("${img.replace(/"/g, "")}")` } : undefined}>
          <div className="lp-wrap lp-center">{badge}<h1>{title}</h1><p className="lp-sub">{sub}</p>{eta}<Buttons ctx={ctx} where="الواجهة" big /></div>
        </section>
      );
    case "stripes":
      return (
        <section className="lp-hero lp-hero-stripes">
          <div className="lp-wrap lp-center">
            {badge}<h1>{title}</h1>
            {showPhone && <a className="lp-giant-phone" href={`tel:${ctx.tel}`} data-track-label="الرقم الكبير • الواجهة"><bdi dir="ltr">{ctx.phone}</bdi></a>}
            <p className="lp-sub">{sub}</p>{eta}<Buttons ctx={ctx} where="الواجهة" big />
          </div>
        </section>
      );
    case "minimal":
      return (
        <section className="lp-hero lp-hero-minimal">
          <div className="lp-wrap">
            {badge}<h1>{title}</h1><p className="lp-sub">{sub}</p>
            {showPhone && <a className="lp-giant-phone" href={`tel:${ctx.tel}`} data-track-label="الرقم الكبير • الواجهة"><bdi dir="ltr">{ctx.phone}</bdi></a>}
            {eta}<Buttons ctx={ctx} where="الواجهة" />
          </div>
        </section>
      );
    default:
      return (
        <section className="lp-hero lp-hero-center">
          <div className="lp-wrap lp-center">{badge}<h1>{title}</h1><p className="lp-sub">{sub}</p>{eta}<Buttons ctx={ctx} where="الواجهة" big /></div>
        </section>
      );
  }
}

export default function LandingPage({ lp, settings, keyword }) {
  const t = templates.find((x) => x.id === lp.template) || templates[0];
  const d = lp.data || {};
  const phone = d.phone || settings.phone;
  const ctx = {
    keyword: keyword || d.keyword_default || "ونش إنقاذ",
    area: d.area_name || "السويس",
    phone,
    tel: d.phone ? intl(d.phone) : settings.phone_intl || intl(phone),
    callLabel: d.call_label || "اتصل الآن",
    waLabel: d.wa_label || "واتساب",
  };
  const wa = String(d.whatsapp || settings.whatsapp || "").replace(/\D/g, "");
  ctx.waHref = `https://wa.me/${wa}?text=${encodeURIComponent(fillText(d.wa_message || "السلام عليكم، محتاج ونش إنقاذ", ctx))}`;
  const title = landingTitle(lp, settings, keyword);
  const btn = d.button_style || t.buttons;
  const c = { ...t.colors, ...(d.primary_override ? { primary: d.primary_override } : {}) };
  const vars = Object.fromEntries(Object.entries(c).map(([k, v]) => [`--lp-${k}`, v]));
  const logo = (isUrl(d.logo) && d.logo) || (isUrl(settings.site_logo) && settings.site_logo) || "/logo.svg";
  const blocks = blocksOf(d).filter((b) => b && b.enabled !== false);
  const F = (x) => fillText(x, ctx);
  const allFaqs = [];
  let ctaN = 0, heroDone = false;

  const section = (b, inner, extra = "") => (
    <section key={b.id} className={`lp-section lp-bg-${b.bg || "none"} lp-b-${b.type} ${extra}`}><div className="lp-wrap">{inner}</div></section>
  );

  const render = (b) => {
    switch (b.type) {
      case "hero":
        if (heroDone) return null; heroDone = true;
        return <Hero key={b.id} variant={t.hero} d={b} ctx={ctx} title={title} />;
      case "trust": {
        const it = lines(b.items); if (!it.length) return null;
        return <section key={b.id} className={`lp-trust lp-bg-${b.bg || "none"}`}><ul className="lp-wrap">{it.map((x, i) => <li key={i}><span aria-hidden="true">✓</span>{F(x)}</li>)}</ul></section>;
      }
      case "services": {
        const it = pairs(b.items); if (!it.length) return null;
        return section(b, <>{b.title && <h2>{F(b.title)}</h2>}<ul className="lp-services">{it.map(([n, ds, img], i) => <li key={i} className={isUrl(img) ? "has-img" : ""}>{isUrl(img) && <img src={img} alt={F(n)} loading="lazy" />}<strong>{F(n)}</strong>{ds && <span>{F(ds)}</span>}</li>)}</ul></>);
      }
      case "steps": {
        const it = pairs(b.items); if (!it.length) return null;
        return section(b, <>{b.title && <h2>{F(b.title)}</h2>}<ol className="lp-steps">{it.map(([n, ds], i) => <li key={i}><strong>{F(n)}</strong>{ds && <span>{F(ds)}</span>}</li>)}</ol></>);
      }
      case "cta": {
        ctaN++;
        const where = `قسم أزرار ${ctaN}`;
        if (b.size === "banner") {
          return (
            <section key={b.id} className={`lp-cta-banner lp-bg-${b.bg || "primary"}`}>
              <div className="lp-wrap lp-cta-banner-inner">
                <div>{b.title && <h2>{F(b.title)}</h2>}{b.text && <p>{F(b.text)}</p>}</div>
                <a className="lp-banner-phone" href={`tel:${ctx.tel}`} data-track-label={`الرقم • ${where}`}><Icon name="phone" size={30} /><bdi dir="ltr">{ctx.phone}</bdi></a>
                {b.buttons !== "call" && <Buttons ctx={ctx} where={where} only="wa" />}
              </div>
            </section>
          );
        }
        return section(b, <div className="lp-center">{b.title && <h2>{F(b.title)}</h2>}{b.text && <p className="lp-cta-text">{F(b.text)}</p>}<Buttons ctx={ctx} where={where} big={b.size !== "normal"} only={b.buttons || "both"} /></div>, "lp-cta-block");
      }
      case "testimonials": {
        const it = pairs(b.items).filter((x) => x[2]); if (!it.length) return null;
        return section(b, <>{b.title && <h2>{F(b.title)}</h2>}<ul className="lp-reviews">{it.map(([name, place, text, stars], i) => {
          const n = Math.max(1, Math.min(5, parseInt(stars, 10) || 5));
          return <li key={i}><p className="lp-stars" aria-label={`${n} من 5`}>{"★".repeat(n)}<span>{"★".repeat(5 - n)}</span></p><blockquote>{F(text)}</blockquote><p className="lp-reviewer"><strong>{name}</strong>{place && <span> • {place}</span>}</p></li>;
        })}</ul></>);
      }
      case "image_text": {
        if (!b.text && !isUrl(b.image) && !b.title) return null;
        return section(b, <div className={`lp-imgtext lp-side-${b.side || "start"}`}>
          {isUrl(b.image) && <img src={b.image} alt={F(b.title || ctx.keyword)} loading="lazy" />}
          <div>{b.title && <h2>{F(b.title)}</h2>}{b.text && <Markdown source={F(b.text)} className="prose lp-prose" />}{b.button && <Buttons ctx={ctx} where="صورة وكلام" />}</div>
        </div>);
      }
      case "gallery": {
        const it = lines(b.images).filter(isUrl); if (!it.length) return null;
        return section(b, <>{b.title && <h2>{F(b.title)}</h2>}<ul className="lp-images">{it.slice(0, 24).map((u, i) => <li key={i}><img src={u} alt={`${ctx.keyword} - صورة ${i + 1}`} loading="lazy" /></li>)}</ul></>);
      }
      case "video": {
        const id = ytId(b.url); if (!id) return null;
        return section(b, <>{b.title && <h2>{F(b.title)}</h2>}<div className="lp-video"><iframe src={`https://www.youtube-nocookie.com/embed/${id}`} title={F(b.title || "فيديو")} loading="lazy" allow="encrypted-media; picture-in-picture" allowFullScreen /></div></>);
      }
      case "counters": {
        const it = pairs(b.items); if (!it.length) return null;
        return section(b, <ul className="lp-counters">{it.map(([n, l], i) => <li key={i}><strong>{F(n)}</strong><span>{F(l)}</span></li>)}</ul>);
      }
      case "text":
        if (!String(b.body || "").trim()) return null;
        return section(b, <>{b.title && <h2>{F(b.title)}</h2>}<Markdown source={F(b.body)} format={b.format} className="prose prose-wide lp-prose" /></>);
      case "faq": {
        const it = parseFaqs(b.items); if (!it.length) return null;
        allFaqs.push(...it.map((f) => ({ q: F(f.q), a: F(f.a) })));
        return section(b, <div className="lp-narrow">{b.title && <h2>{F(b.title)}</h2>}{it.map((f, i) => <details key={i} className="lp-faq" open={i === 0}><summary>{F(f.q)}</summary><p>{F(f.a)}</p></details>)}</div>);
      }
      case "final":
        return (
          <section key={b.id} className="lp-final">
            <div className="lp-wrap lp-center"><h2>{F(b.text || "واقف دلوقتي؟ متستناش")}</h2><Buttons ctx={ctx} where="آخر الصفحة" big /></div>
          </section>
        );
      default: return null;
    }
  };
  const content = blocks.map(render);

  return (
    <div className={`lp lp-${t.id} lp-btn-${btn}`} style={vars}>
      {lp.preview && <div className="lp-preview-note">معاينة: الصفحة دي مسودة ومحدش شايفها غيرك</div>}
      <header className={`lp-top${btn === "topbar" ? " lp-top-fixed" : ""}`}>
        <div className="lp-wrap lp-top-inner">
          <span className="lp-brand"><img src={logo} alt={settings.site_name || ""} height="40" /> <span>{settings.site_name || "ونش إنقاذ الطيب"}</span></span>
          {btn === "topbar"
            ? <Buttons ctx={ctx} where="الشريط اللي فوق" />
            : <a className="lp-top-phone" href={`tel:${ctx.tel}`} data-track-label="الرقم في الهيدر"><Icon name="phone" size={18} /> <bdi dir="ltr">{phone}</bdi></a>}
        </div>
      </header>

      <main id="main">
        {!heroDone && <h1 className="sr-only">{title}</h1>}
        {content}
      </main>

      <footer className="lp-foot"><div className="lp-wrap">© {new Date().getFullYear()} {settings.site_name} • <a href="/">الموقع الرئيسي</a></div></footer>

      {btn === "sticky" && <div className="lp-sticky"><Buttons ctx={ctx} where="الشريط الثابت" /></div>}
      {btn === "floating" && (
        <div className="lp-fabs">
          <a className="lp-fab lp-fab-wa" href={ctx.waHref} target="_blank" rel="noopener" aria-label={ctx.waLabel} data-track-label={`${ctx.waLabel} • الزرار العائم`}><Icon name="whatsapp" size={30} /></a>
          <a className="lp-fab lp-fab-call" href={`tel:${ctx.tel}`} aria-label={ctx.callLabel} data-track-label={`${ctx.callLabel} • الزرار العائم`}><Icon name="phone" size={30} /></a>
        </div>
      )}
      {btn === "side" && (
        <div className="lp-side">
          <a href={`tel:${ctx.tel}`} data-track-label={`${ctx.callLabel} • الزرار الجانبي`}><Icon name="phone" size={22} /><span>{ctx.callLabel}</span></a>
          <a href={ctx.waHref} target="_blank" rel="noopener" data-track-label={`${ctx.waLabel} • الزرار الجانبي`}><Icon name="whatsapp" size={22} /><span>واتساب</span></a>
        </div>
      )}
      {btn !== "sticky" && btn !== "topbar" && <div className="lp-sticky lp-sticky-mobile"><Buttons ctx={ctx} where="الشريط الثابت (موبايل)" /></div>}

      {settings.gads_id && <GadsConversions id={settings.gads_id} callLabel={settings.gads_call_label} waLabel={settings.gads_wa_label} />}
      <JsonLd data={allFaqs.length ? faqSchema(allFaqs) : null} />
    </div>
  );
}
