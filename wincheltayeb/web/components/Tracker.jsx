"use client";
import { useEffect, useRef } from "react";
import { usePathname } from "next/navigation";

// إحصائيات خاصة بالموقع: بتتبعت للوحة التحكم بتاعتنا بس، مفيش أي طرف تالت
const ENDPOINT = process.env.NEXT_PUBLIC_TRACK_URL || "https://admin.wincheltayeb.com/hit.php";
const SESSION_GAP = 30 * 60 * 1000;

const rid = () => {
  try { return crypto.randomUUID().replace(/-/g, "").slice(0, 24); }
  catch { return (Math.random().toString(36).slice(2) + Date.now().toString(36)).replace(/[^a-z0-9]/g, "").slice(0, 24); }
};
const store = (kind) => { try { return window[kind]; } catch { return null; } };

function ids() {
  const ls = store("localStorage"), ss = store("sessionStorage");
  let vid = ls?.getItem("wt_vid");
  if (!vid) { vid = rid(); ls?.setItem("wt_vid", vid); }
  let s = null;
  try { s = JSON.parse(ss?.getItem("wt_sid") || "null"); } catch {}
  const fresh = !s || Date.now() - s.last > SESSION_GAP;
  if (fresh) s = { id: rid(), last: Date.now() };
  s.last = Date.now();
  ss?.setItem("wt_sid", JSON.stringify(s));
  return { vid, sid: s.id, fresh };
}

function send(data) {
  const body = JSON.stringify(data);
  try { if (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, new Blob([body], { type: "text/plain" }))) return; } catch {}
  fetch(ENDPOINT, { method: "POST", body, keepalive: true, mode: "no-cors", headers: { "Content-Type": "text/plain" } }).catch(() => {});
}

function category(el) {
  const a = el.closest("a");
  const href = a?.getAttribute("href") || "";
  if (/^tel:/i.test(href)) return "call";
  if (/wa\.me|whatsapp\.com|api\.whatsapp/i.test(href)) return "whatsapp";
  if (a && /^https?:\/\//i.test(href) && new URL(href, location.href).host !== location.host) return "outbound";
  if (el.closest(".main-nav, .mobile-menu, .site-footer")) return "nav";
  if (el.closest(".btn, .callbar, button, .nav-cta")) return "cta";
  return a ? "link" : null;
}

export default function Tracker() {
  const path = usePathname();
  const cur = useRef(null); // { pvid, ms, since }

  const flush = (type) => {
    const c = cur.current;
    if (!c) return;
    if (c.since) { c.ms += Date.now() - c.since; c.since = document.visibilityState === "visible" ? Date.now() : 0; }
    const { vid, sid } = ids();
    send({ t: type, vid, sid, pvid: c.pvid, ms: c.ms, path: location.pathname });
  };

  // تشغيل أو إيقاف التتبع على الجهاز ده: ?wt_ignore=1 أو 0
  const ignored = () => {
    const ls = store("localStorage");
    const p = new URLSearchParams(location.search).get("wt_ignore");
    if (p === "1") ls?.setItem("wt_ignore", "1");
    if (p === "0") ls?.removeItem("wt_ignore");
    return ls?.getItem("wt_ignore") === "1";
  };

  useEffect(() => {
    if (ignored()) return;
    if (cur.current) flush("leave");
    const { vid, sid, fresh } = ids();
    const pvid = rid();
    cur.current = { pvid, ms: 0, since: document.visibilityState === "visible" ? Date.now() : 0 };
    const t = setTimeout(() => send({
      t: "pv", vid, sid, pvid, path: location.pathname, title: document.title, url: location.href,
      ref: fresh ? document.referrer : "", lang: navigator.language || "", screen: `${screen.width}x${screen.height}`,
    }), 250);
    return () => clearTimeout(t);
  }, [path]);

  useEffect(() => {
    if (ignored()) return;
    const hb = setInterval(() => { if (document.visibilityState === "visible") flush("hb"); }, 15000);
    const onVis = () => {
      const c = cur.current; if (!c) return;
      if (document.visibilityState === "hidden") flush("leave");
      else c.since = Date.now();
    };
    const onHide = () => flush("leave");
    const onClick = (e) => {
      const el = e.target instanceof Element ? e.target : null;
      if (!el) return;
      const cat = category(el);
      if (!cat) return;
      const node = el.closest("a, button") || el;
      const label = (node.getAttribute("aria-label") || node.textContent || "").replace(/\s+/g, " ").trim().slice(0, 100);
      const { vid, sid } = ids();
      send({ t: "ev", vid, sid, cat, label, href: node.getAttribute("href") || "", path: location.pathname });
    };
    document.addEventListener("visibilitychange", onVis);
    window.addEventListener("pagehide", onHide);
    document.addEventListener("click", onClick, true);
    return () => { clearInterval(hb); document.removeEventListener("visibilitychange", onVis); window.removeEventListener("pagehide", onHide); document.removeEventListener("click", onClick, true); };
  }, []);

  return null;
}
