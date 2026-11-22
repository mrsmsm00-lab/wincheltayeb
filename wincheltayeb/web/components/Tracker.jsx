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

// ---------- بصمة التليفون ----------
// كل اللي المتصفح بيدّيه لأي موقع من غير إذن الزائر، عشان نعرف نفس التليفون لو غيّر الـ IP أو مسح الكوكيز.
// بتتبعت بعد الزيارة في حدث "dx" فمبتأخرش العدّ. fp = تفاصيل ثابتة، hw = العتاد بس، x = كل التفاصيل للمقارنة.
const hash16 = async (t) => {
  const b = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(t));
  return [...new Uint8Array(b)].slice(0, 8).map((v) => v.toString(16).padStart(2, "0")).join("");
};
const within = (p, ms) => Promise.race([Promise.resolve(p), new Promise((r) => setTimeout(() => r(undefined), ms))]).catch(() => undefined);
const safe = (f) => { try { return f(); } catch { return undefined; } };

function canvasData() {
  const c = document.createElement("canvas");
  c.width = 240; c.height = 60;
  const x = c.getContext("2d");
  x.textBaseline = "top"; x.font = "16px Arial"; x.fillStyle = "#f60"; x.fillRect(100, 1, 62, 20);
  x.fillStyle = "#069"; x.fillText("ونش الطيب 🚛 Wt,9", 2, 15);
  x.fillStyle = "rgba(102,204,0,.7)"; x.fillText("ونش الطيب 🚛 Wt,9", 4, 17);
  x.beginPath(); x.arc(200, 30, 20, 0, Math.PI * 2); x.strokeStyle = "#d0101e"; x.stroke();
  return c.toDataURL();
}

async function audioPrint() {
  const C = window.OfflineAudioContext || window.webkitOfflineAudioContext;
  if (!C) return "";
  const ctx = new C(1, 5000, 44100);
  const o = ctx.createOscillator();
  o.type = "triangle"; o.frequency.value = 10000;
  const k = ctx.createDynamicsCompressor();
  k.threshold.value = -50; k.knee.value = 40; k.ratio.value = 12; k.attack.value = 0; k.release.value = 0.25;
  o.connect(k); k.connect(ctx.destination); o.start(0);
  const buf = await within(ctx.startRendering(), 800);
  if (!buf) return "";
  const d = buf.getChannelData(0);
  let sum = 0;
  for (let i = 4500; i < 5000; i++) sum += Math.abs(d[i]);
  return sum.toFixed(7);
}

async function deviceDetails() {
  const n = navigator;
  const s = window.screen || {};
  const x = {
    sw: s.width, sh: s.height, dpr: window.devicePixelRatio, aw: s.availWidth, ah: s.availHeight, cd: s.colorDepth,
    iw: window.innerWidth, ih: window.innerHeight, or: safe(() => s.orientation.type), cores: n.hardwareConcurrency,
    mem: n.deviceMemory, touch: n.maxTouchPoints, lang: n.language, langs: (n.languages || []).join(","), plat: n.platform,
    pdf: n.pdfViewerEnabled ? 1 : 0, dnt: n.doNotTrack || "", tzo: new Date().getTimezoneOffset(),
    dark: safe(() => matchMedia("(prefers-color-scheme: dark)").matches) ? 1 : 0,
    rm: safe(() => matchMedia("(prefers-reduced-motion: reduce)").matches) ? 1 : 0,
    fs: safe(() => getComputedStyle(document.documentElement).fontSize),
  };
  const ro = safe(() => Intl.DateTimeFormat().resolvedOptions()) || {};
  Object.assign(x, { tz: ro.timeZone, loc: ro.locale, cal: ro.calendar, num: ro.numberingSystem,
    hc: safe(() => new Intl.DateTimeFormat(undefined, { hour: "numeric" }).resolvedOptions().hourCycle) });
  const c = n.connection || {};
  Object.assign(x, { ct: c.type, ect: c.effectiveType, dl: c.downlink, rtt: c.rtt, sd: c.saveData ? 1 : 0 });
  safe(() => {
    const gl = document.createElement("canvas").getContext("webgl");
    if (!gl) return;
    const e = gl.getExtension("WEBGL_debug_renderer_info");
    x.gpuv = e ? gl.getParameter(e.UNMASKED_VENDOR_WEBGL) : String(gl.getParameter(gl.VENDOR));
    x.gpu = e ? gl.getParameter(e.UNMASKED_RENDERER_WEBGL) : String(gl.getParameter(gl.RENDERER));
    x.glt = gl.getParameter(gl.MAX_TEXTURE_SIZE);
    x.glx = (gl.getSupportedExtensions() || []).length;
  });
  const [cv, au, bat, est, devs, uad] = await Promise.all([
    within(safe(() => hash16(canvasData())), 800),
    within(safe(() => audioPrint()), 1000),
    within(n.getBattery ? safe(() => n.getBattery()) : undefined, 600),
    within(n.storage?.estimate ? safe(() => n.storage.estimate()) : undefined, 600),
    within(n.mediaDevices?.enumerateDevices ? safe(() => n.mediaDevices.enumerateDevices()) : undefined, 600),
    within(n.userAgentData?.getHighEntropyValues
      ? safe(() => n.userAgentData.getHighEntropyValues(["architecture", "bitness", "fullVersionList", "formFactors", "model", "platformVersion"])) : undefined, 600),
  ]);
  x.cv = cv || ""; x.au = au || "";
  if (bat) { x.bat = Math.round(bat.level * 100); x.chg = bat.charging ? 1 : 0; }
  if (est?.quota) x.quota = Math.round(est.quota / 1048576);
  if (devs) ["audioinput", "videoinput", "audiooutput"].forEach((k, i) => { x[["mic", "cam", "spk"][i]] = devs.filter((d) => d.kind === k).length; });
  if (uad) {
    x.m = uad.model || ""; x.pv = uad.platformVersion || ""; x.arch = uad.architecture; x.bits = uad.bitness; x.ff = (uad.formFactors || []).join(",");
    x.bv = (uad.fullVersionList || []).filter((b) => !/not.?a.?brand/i.test(b.brand)).map((b) => `${b.brand} ${b.version}`).join(", ");
  }
  const voices = safe(() => speechSynthesis.getVoices()) || [];
  x.voices = voices.length;
  if (voices.length) x.vh = await within(safe(() => hash16(voices.map((v) => v.name + v.lang).join("|"))), 300);
  const hw = [x.m, x.pv, x.arch, x.bits, `${x.sw}x${x.sh}`, x.dpr, x.cd, x.cores, x.mem, x.touch, x.gpuv, x.gpu, x.glt, x.glx, x.tz, x.mic, x.cam].join("|");
  const full = [hw, x.aw, x.ah, x.cv, x.au, x.langs, x.loc, x.num, x.hc, x.plat, x.fs, x.vh, x.pdf].join("|");
  const [fp, h] = await Promise.all([within(safe(() => hash16(full)), 300), within(safe(() => hash16(hw)), 300)]);
  return { fp, hw: h, x };
}

// زرار "اعرف أقرب ونش ليك": يطلب GPS بإذن الزائر، يخزّنه عندنا، ويفتح واتساب بپن المكان.
function shareLocation(btn) {
  const wa = (btn.dataset.wa || "").replace(/\D/g, "");
  const baseMsg = btn.dataset.msg || "السلام عليكم، محتاج ونش إنقاذ. ده موقعي:";
  let win = null;
  try { win = window.open("", "_blank"); } catch {}
  const openWa = (text) => {
    if (!wa) { if (win) win.close(); return; }
    const url = "https://wa.me/" + wa + "?text=" + encodeURIComponent(text);
    if (win) win.location = url; else location.href = url;
  };
  const original = btn.innerHTML;
  const reset = () => { try { btn.innerHTML = original; btn.removeAttribute("aria-busy"); } catch {} };
  const { vid, sid } = ids();
  send({ t: "ev", vid, sid, cat: "whatsapp", label: btn.dataset.trackLabel || "geo", href: "geo", path: location.pathname });
  if (!navigator.geolocation) { openWa(baseMsg); return; }
  try { btn.textContent = "📍 بنحدد مكانك..."; btn.setAttribute("aria-busy", "true"); } catch {}
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const lat = +pos.coords.latitude.toFixed(6);
      const lng = +pos.coords.longitude.toFixed(6);
      send({ t: "geo", vid, sid, lat, lng, acc: Math.round(pos.coords.accuracy || 0) });
      reset();
      openWa(baseMsg + " https://maps.google.com/?q=" + lat + "," + lng);
    },
    () => { reset(); openWa(baseMsg); },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
  );
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
    const t = setTimeout(() => {
      send({
        t: "pv", vid, sid, pvid, path: location.pathname, title: document.title, url: location.href,
        ref: fresh ? document.referrer : "", lang: navigator.language || "", screen: `${screen.width}x${screen.height}`,
      });
      if (fresh) deviceDetails().then((d) => send({ t: "dx", vid, sid, ...d })).catch(() => {});
    }, 250);
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
      const geoEl = el.closest("[data-geo]");
      if (geoEl) { e.preventDefault(); shareLocation(geoEl); return; }
      const cat = category(el);
      if (!cat) return;
      const node = el.closest("a, button") || el;
      const label = (node.dataset?.trackLabel || node.getAttribute("aria-label") || node.textContent || "").replace(/\s+/g, " ").trim().slice(0, 100);
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
