import { marked } from "marked";

marked.setOptions({ gfm: true, breaks: false });

// المحتوى بييجي من لوحة التحكم (مصدر موثوق) بصيغة Markdown
export default function Markdown({ source, className = "prose" }) {
  const html = marked.parse(source || "");
  return <div className={className} dangerouslySetInnerHTML={{ __html: html }} />;
}
