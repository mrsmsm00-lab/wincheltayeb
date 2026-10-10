import { getData } from "@/lib/content";
import { abs, postUrl } from "@/lib/site";

export const revalidate = 600;

// https://llmstxt.org — خريطة مختصرة للموقع لمحركات الذكاء الاصطناعي
export async function GET() {
  const { settings, posts } = await getData();
  const section = (title, type) => {
    const list = posts.filter((p) => p.type === type);
    if (!list.length) return "";
    return `## ${title}\n\n${list.map((p) => `- [${p.title}](${abs(postUrl(p))}): ${p.meta_description || p.excerpt}`).join("\n")}\n`;
  };
  const txt = `# ${settings.site_name}

> ونش إنقاذ سيارات في محافظة السويس بمصر، شغال 24 ساعة. ونش ملاكي (سطحة هيدروليك)، ونش نقل تقيل وميكروباص، ونش موتوسيكلات، خدمات طريق (بطارية، كاوتش، بنزين)، وإنقاذ حوادث. نطاق الخدمة محافظة السويس، بما فيها العين السخنة والجلالة والأجزاء التابعة للمحافظة من طرق القاهرة الصحراوي والإسماعيلية، وكمان طريق السخنة الزعفرانة لحد الزعفرانة (محافظة البحر الأحمر).

- التليفون والواتساب: ${settings.phone} (${settings.phone_intl})
- مواعيد العمل: ${settings.hours}
- الموقع: ${abs("/")}
- النسخة الكاملة من المحتوى: ${abs("/llms-full.txt")}

${section("الخدمات", "service")}
${section("مناطق التغطية في محافظة السويس", "area")}
${section("مقالات ونصايح", "blog")}
## صفحات تانية

- [خريطة التغطية](${abs("/suez-map")}): خريطة تفاعلية لمناطق الخدمة في محافظة السويس.
- [صور وفيديوهات](${abs("/gallery")}): صور وفيديوهات من شغل الونش.
- [مين إحنا](${abs("/about")})
- [كلمنا](${abs("/contact")})
`;
  return new Response(txt, { headers: { "Content-Type": "text/plain; charset=utf-8" } });
}
