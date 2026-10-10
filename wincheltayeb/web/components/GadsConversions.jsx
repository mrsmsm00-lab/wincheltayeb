"use client";
import Script from "next/script";
import { useEffect } from "react";

// تحويلات Google Ads: كل ضغطة اتصال أو واتساب بتتسجل تحويل
export default function GadsConversions({ id, callLabel, waLabel }) {
  useEffect(() => {
    const onClick = (e) => {
      const a = e.target instanceof Element ? e.target.closest("a") : null;
      if (!a || typeof window.gtag !== "function") return;
      const href = a.getAttribute("href") || "";
      const label = /^tel:/i.test(href) ? callLabel : /wa\.me|whatsapp/i.test(href) ? waLabel || callLabel : null;
      if (label) window.gtag("event", "conversion", { send_to: `${id}/${label}` });
    };
    document.addEventListener("click", onClick, true);
    return () => document.removeEventListener("click", onClick, true);
  }, [id, callLabel, waLabel]);
  if (!/^AW-\d+$/.test(id || "")) return null;
  return (
    <>
      <Script src={`https://www.googletagmanager.com/gtag/js?id=${id}`} strategy="afterInteractive" />
      <Script id="gads-init" strategy="afterInteractive">{`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}window.gtag=gtag;gtag('js',new Date());gtag('config','${id}');`}</Script>
    </>
  );
}
