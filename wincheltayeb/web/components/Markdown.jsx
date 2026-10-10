import { marked } from "marked";
import sanitizeHtml from "sanitize-html";

marked.setOptions({ gfm: true, breaks: false });

// المحتوى بييجي من لوحة التحكم: Markdown أو HTML. بننضفه من أي سكريبت أو كود خطر قبل العرض
const SANITIZE = {
  allowedTags: sanitizeHtml.defaults.allowedTags.concat([
    "img", "h1", "h2", "iframe", "video", "source", "figure", "figcaption", "details", "summary",
    "picture", "mark", "small", "u", "s", "sup", "sub", "del", "ins", "kbd", "address",
  ]),
  allowedAttributes: {
    "*": ["class", "id", "style", "dir", "lang", "title", "aria-label", "aria-hidden", "role"],
    a: ["href", "name", "target", "rel"],
    img: ["src", "srcset", "sizes", "alt", "width", "height", "loading", "decoding"],
    iframe: ["src", "width", "height", "allow", "allowfullscreen", "frameborder", "title", "loading", "referrerpolicy"],
    video: ["src", "controls", "poster", "width", "height", "muted", "loop", "playsinline", "preload", "autoplay"],
    source: ["src", "srcset", "type", "media"],
    td: ["colspan", "rowspan"], th: ["colspan", "rowspan", "scope"],
    ol: ["start", "type"], details: ["open"],
  },
  allowedSchemes: ["http", "https", "mailto", "tel"],
  allowedSchemesByTag: { img: ["http", "https", "data"] },
  allowedIframeHostnames: ["www.youtube.com", "youtube.com", "www.youtube-nocookie.com", "player.vimeo.com", "www.google.com", "maps.google.com", "www.facebook.com"],
  allowProtocolRelative: false,
  transformTags: {
    a: (tag, attribs) => {
      const ext = /^https?:\/\//i.test(attribs.href || "") && !/wincheltayeb\.com/i.test(attribs.href || "");
      return { tagName: "a", attribs: ext ? { ...attribs, rel: "noopener nofollow", target: attribs.target || "_blank" } : attribs };
    },
    img: (tag, attribs) => ({ tagName: "img", attribs: { loading: "lazy", decoding: "async", ...attribs } }),
  },
};

export function renderContent(source, format = "markdown") {
  const raw = format === "html" ? source || "" : marked.parse(source || "");
  return sanitizeHtml(raw, SANITIZE);
}

export default function Markdown({ source, format = "markdown", className = "prose" }) {
  return <div className={className} dangerouslySetInnerHTML={{ __html: renderContent(source, format) }} />;
}
