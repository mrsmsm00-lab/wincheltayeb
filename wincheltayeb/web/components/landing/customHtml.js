import sanitizeHtml from "sanitize-html";

// تنضيف تصميم HTML مخصص: كل الوسوم والتنسيقات مسموحة، والسكريبتات وأي كود بيتنفذ بيتشال
const FRAME_HOSTS = ["www.youtube.com", "youtube.com", "www.youtube-nocookie.com", "player.vimeo.com", "www.google.com", "maps.google.com"];
const BLOCKED = new Set(["script", "noscript", "object", "embed", "applet", "base", "meta", "frame", "frameset", "template"]);

export function sanitizeCustomPage(html) {
  return sanitizeHtml(html, {
    allowedTags: false,
    allowedAttributes: false,
    allowVulnerableTags: true, // عشان <style> يشتغل
    parser: { lowerCaseAttributeNames: false }, // عشان viewBox في SVG
    allowedSchemes: ["http", "https", "mailto", "tel", "data"],
    allowedSchemesAppliedToAttributes: ["href", "src", "action", "poster", "background", "xlink:href", "formaction"],
    nonTextTags: ["script", "noscript", "textarea", "option"],
    exclusiveFilter: (frame) => {
      const tag = frame.tag;
      if (BLOCKED.has(tag)) return true;
      if (tag === "iframe") { try { return !FRAME_HOSTS.includes(new URL(frame.attribs.src || "").host); } catch { return true; } }
      if (tag === "link") return !/^https:\/\/fonts\.googleapis\.com\//.test(frame.attribs.href || "") || (frame.attribs.rel || "") !== "stylesheet";
      if (tag === "form") return true;
      return false;
    },
    transformTags: {
      "*": (tagName, attribs) => {
        const clean = {};
        for (const [k, v] of Object.entries(attribs)) {
          if (/^on/i.test(k)) continue;
          if (/^(href|src|action|formaction|xlink:href)$/i.test(k) && /^\s*(javascript|vbscript):/i.test(v)) continue;
          if (k === "style" && /expression\s*\(|javascript:/i.test(v)) continue;
          clean[k] = v;
        }
        return { tagName, attribs: clean };
      },
    },
  });
}
