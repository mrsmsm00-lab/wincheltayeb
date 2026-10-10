import { getData } from "@/lib/content";
import { abs, postUrl } from "@/lib/site";

export const revalidate = 600;

export async function GET() {
  const { settings, posts } = await getData();
  const parts = posts.map((p) => {
    const faqs = (p.faqs || []).map((f) => `**س: ${f.q}**\nج: ${f.a}`).join("\n\n");
    const text = p.format === "html" ? String(p.body || "").replace(/<[^>]+>/g, " ").replace(/[ \t]+/g, " ").replace(/\n\s*\n+/g, "\n\n") : p.body;
    return `---\n\n# ${p.title}\n\nالرابط: ${abs(postUrl(p))}\n\n${text}${faqs ? `\n\n## أسئلة شائعة\n\n${faqs}` : ""}\n`;
  });
  const general = (settings.general_faqs || []).map((f) => `**س: ${f.q}**\nج: ${f.a}`).join("\n\n");
  const txt = `# ${settings.site_name}: المحتوى الكامل\n\nالتليفون والواتساب: ${settings.phone}\nنطاق الخدمة: محافظة السويس والعين السخنة والجلالة، ولحد الزعفرانة.\n\n## أسئلة عامة\n\n${general}\n\n${parts.join("\n")}`;
  return new Response(txt, { headers: { "Content-Type": "text/plain; charset=utf-8" } });
}
