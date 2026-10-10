// يبني content/seed.json من ملفات المحتوى
// نفس الملف بيتنسخ للوحة التحكم (admin/install/seed.json) عشان يتعمل import أول مرة
import { writeFileSync, mkdirSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { services } from "../content/services.mjs";
import { areas, districts } from "../content/areas.mjs";
import { blog, generalFaqs } from "../content/blog.mjs";

const here = dirname(fileURLToPath(import.meta.url));
const PHONE = "01000529729";
const today = new Date().toISOString().slice(0, 10);

const settings = {
  site_name: "ونش إنقاذ الطيب",
  tagline: "ونش إنقاذ السويس 24 ساعة",
  phone: PHONE,
  phone_intl: "+201000529729",
  whatsapp: "201000529729",
  email: "",
  address: "مدينة السويس، محافظة السويس، مصر",
  hours: "24 ساعة طول أيام الأسبوع",
  facebook: "",
  youtube: "",
  tiktok: "",
  instagram: "",
  general_faqs: JSON.stringify(generalFaqs),
};

const areaBody = (a) => {
  const d = districts[a.district]?.name ?? "";
  const svc = services
    .map((s) => `- [${s.title}](/services/${s.slug}): ${s.short}.`)
    .join("\n");
  return `${a.intro}

## ونش إنقاذ في ${a.name} شغال 24 ساعة

ونش إنقاذ الطيب بيغطي ${a.name}${a.district !== "roads" && !a.name.startsWith("حي") ? ` التابعة لـ${d.replace(/^حي /, "حي ")}` : ""} في محافظة السويس، ليل ونهار وفي الإجازات. وقت الوصول المتوقع في العادي **${a.eta}**، وبنأكدلك الوقت بالظبط أول ما تكلمنا وتبعت مكانك. بنقولك السعر النهائي قبل ما الونش يتحرك، ومفيش أي زيادة بعد الوصول.

## الخدمات المتاحة في ${a.name}

${svc}

## الأماكن والطرق اللي بنوصلها في ${a.name}

${a.landmarks.map((l) => `- ${l}`).join("\n")}

والطرق الأساسية اللي بنتحرك عليها هنا: ${a.roads.join("، ")}.

## نصيحة لو عربيتك عطلت في ${a.name}

${a.tip}

## إزاي تطلب ونش في ${a.name}

1. اتصل أو ابعت واتساب على **${PHONE}**.
2. ابعت لوكيشن مكانك من الموبايل، وقولنا رايح فين.
3. قولنا نوع العربية والعطلة باختصار.
4. هنقولك السعر النهائي ووقت الوصول، والونش يتحرك على طول.`;
};

const areaFaqs = (a) => [
  ...a.faqs,
  { q: `ونش الطيب بيوصل ${a.name} في قد إيه؟`, a: `وقت الوصول المتوقع في ${a.name} ${a.eta}، وبيتحدد بالظبط حسب مكانك والزحمة وقت الطلب.` },
  { q: `ونش ${a.name} شغال بالليل؟`, a: `أيوه، الخدمة في ${a.name} شغالة 24 ساعة، واتصل على ${PHONE} في أي وقت.` },
];

let sort = 0;
const posts = [];

for (const s of services) {
  posts.push({
    type: "service", slug: s.slug, title: s.title, excerpt: s.short,
    body: s.body, meta_title: s.meta_title, meta_description: s.meta_description,
    image: "", icon: s.icon, district: "", lat: null, lng: null, eta: "",
    faqs: s.faqs, published: 1, sort: sort++, published_at: "2026-05-01", updated_at: today,
  });
}

for (const a of areas) {
  posts.push({
    type: "area", slug: a.slug, title: `ونش إنقاذ ${a.name}`, excerpt: `ونش إنقاذ في ${a.name} شغال 24 ساعة، بيوصلك خلال ${a.eta}، وبتعرف السعر النهائي قبل ما نتحرك.`,
    body: areaBody(a),
    meta_title: `ونش إنقاذ ${a.name} 24 ساعة | ونش الطيب السويس`,
    meta_description: `ونش إنقاذ في ${a.name} بمحافظة السويس شغال 24 ساعة: ونش ملاكي، نقل تقيل، موتوسيكلات، وخدمات طريق. وصول خلال ${a.eta}. اتصل ${PHONE}`,
    image: "", icon: "", district: a.district, lat: a.lat, lng: a.lng, eta: a.eta, place_name: a.name,
    faqs: areaFaqs(a), published: 1, sort: sort++, published_at: "2026-05-01", updated_at: today,
  });
}

for (const b of blog) {
  posts.push({
    type: "blog", slug: b.slug, title: b.title, excerpt: b.excerpt, body: b.body,
    meta_title: `${b.title} | ونش إنقاذ الطيب`, meta_description: b.meta_description,
    image: "", icon: "", district: "", lat: null, lng: null, eta: "",
    faqs: b.faqs, published: 1, sort: sort++, published_at: b.published_at, updated_at: b.published_at,
  });
}

posts.push({
  type: "page", slug: "about", title: "مين إحنا", excerpt: "ونش إنقاذ الطيب: فريق إنقاذ عربيات من السويس وللسويس.",
  meta_title: "مين إحنا | ونش إنقاذ الطيب في السويس",
  meta_description: "ونش إنقاذ الطيب فريق إنقاذ سيارات من أهل السويس، شغال 24 ساعة في كل أحياء المحافظة والعين السخنة.",
  body: `ونش إنقاذ الطيب فريق إنقاذ عربيات من أهل السويس. بنشتغل في المحافظة وبس، وده مش قيد، ده اختيار: لما تركّز على مدينة واحدة بتعرف شوارعها ومداخلها وطرقها السريعة كويس، وبتوصل أسرع.

## إحنا بنشتغل إزاي

- **سعر واضح قبل الحركة:** بنقولك السعر النهائي في التليفون، ومفيش مفاجآت.
- **سطحات هيدروليك:** كل ونشاتنا الملاكي سطحات، عشان الفتيس والعجل يفضلوا سليمين.
- **صور قبل التحميل:** السواق بيصور العربية قدامك قبل ما تطلع على الونش.
- **24 ساعة:** في الأعياد، وبالليل، وفي عز الصيف.

## بنغطي فين

كل محافظة السويس: أحياء السويس والأربعين وفيصل وعتاقة والجناين، وبورتوفيق والأدبية والعين السخنة وجنيفة والشلوفة، والأجزاء التابعة للمحافظة من طرق القاهرة الصحراوي والإسماعيلية والجلالة.`,
  image: "", icon: "", district: "", lat: null, lng: null, eta: "",
  faqs: [], published: 1, sort: sort++, published_at: "2026-05-01", updated_at: today,
});

const gallery = [];

const out = { generated_at: new Date().toISOString(), settings, districts, posts, gallery };
const target = resolve(here, "../content/seed.json");
writeFileSync(target, JSON.stringify(out, null, 2));
const adminCopy = resolve(here, "../../admin/install/seed.json");
mkdirSync(dirname(adminCopy), { recursive: true });
writeFileSync(adminCopy, JSON.stringify(out, null, 2));
console.log(`seed.json: ${posts.length} posts`);
