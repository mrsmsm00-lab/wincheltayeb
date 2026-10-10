// تصميم صفحة الهبوط: التبديل بين الوضعين، الألوان، ومعاينة الـ HTML المخصص
(() => {
  // إظهار حقول كل وضع
  const radios = document.querySelectorAll("[data-mode-radio]");
  const applyMode = () => {
    const m = document.querySelector("[data-mode-radio]:checked")?.value || "blocks";
    document.querySelectorAll("[data-show-mode]").forEach((el) => (el.hidden = el.dataset.showMode !== m));
    if (m === "custom") refresh();
  };
  radios.forEach((r) => r.addEventListener("change", applyMode));

  // الألوان: منتقي اللون مربوط بخانة النص، والخانة الفاضية = لون القالب
  document.querySelectorAll("[data-color-for]").forEach((picker) => {
    const txt = document.getElementById(picker.dataset.colorFor);
    picker.addEventListener("input", () => { txt.value = picker.value; picker.removeAttribute("data-empty"); txt.dispatchEvent(new Event("input", { bubbles: true })); });
    txt.addEventListener("input", () => { if (/^#[0-9a-f]{6}$/i.test(txt.value)) { picker.value = txt.value; picker.removeAttribute("data-empty"); } else if (!txt.value) picker.setAttribute("data-empty", ""); });
  });
  document.querySelectorAll("[data-color-clear]").forEach((b) => b.addEventListener("click", () => {
    const txt = document.getElementById(b.dataset.colorClear); txt.value = ""; txt.dispatchEvent(new Event("input", { bubbles: true }));
  }));

  // معاينة الـ HTML المخصص في iframe معزول (من غير سكريبتات)
  const ta = document.getElementById("custom-html");
  const frame = document.getElementById("custom-preview");
  const data = JSON.parse(document.getElementById("lp-preview-data")?.textContent || "{}");
  const g = (n) => (document.querySelector(`[name="g[${n}]"]`) || {}).value || "";
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
  const tokens = (html) => {
    const phone = g("phone") || data.phone || "";
    const tel = "tel:" + (data.phone_intl || ("+2" + phone));
    const wa = "https://wa.me/" + String(g("whatsapp") || data.whatsapp || "").replace(/\D/g, "") + "?text=" + encodeURIComponent((g("wa_message") || "").replaceAll("{area}", g("area_name")));
    return html
      .replaceAll("{call_button}", `<a class="lp-btn lp-btn-call" href="${tel}">📞 <b>${esc(g("call_label") || "اتصل الآن")}</b></a>`)
      .replaceAll("{wa_button}", `<a class="lp-btn lp-btn-wa" href="${wa}">💬 <b>${esc(g("wa_label") || "واتساب")}</b></a>`)
      .replaceAll("{phone}", esc(phone)).replaceAll("{tel}", tel).replaceAll("{whatsapp_link}", wa)
      .replaceAll("{logo}", data.logo || "").replaceAll("{keyword}", esc(g("keyword_default") || "ونش إنقاذ")).replaceAll("{area}", esc(g("area_name") || "السويس"));
  };
  let t = null;
  function refresh() {
    if (!ta || !frame) return;
    const body = tokens(ta.value).replace(/<script[\s\S]*?<\/script>/gi, "");
    frame.srcdoc = `<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <style>body{margin:0;font-family:Tahoma,Arial,sans-serif}.lp-btn{display:inline-flex;gap:8px;align-items:center;padding:14px 22px;border-radius:12px;text-decoration:none;font-weight:800}.lp-btn-call{background:#0B4EA2;color:#fff}.lp-btn-wa{background:#0E8A43;color:#fff}</style></head><body>${body}</body></html>`;
  }
  if (ta) ta.addEventListener("input", () => { clearTimeout(t); t = setTimeout(refresh, 400); });
  document.querySelector("[data-custom-refresh]")?.addEventListener("click", refresh);

  // نموذج بداية
  const STARTER = `<style>
  .cx { --main: #0B4EA2; --dark: #0a0f1c; font-family: inherit; color: #111; }
  /* عشان تحط صورة خلفية: ارفعها من «صور وفيديوهات» وحط رابطها مكان الرابط اللي تحت */
  .cx-hero { background: linear-gradient(rgba(10,15,28,.55), rgba(10,15,28,.85)), url("") center/cover, var(--dark); color: #fff; text-align: center; padding: 90px 20px; }
  .cx-hero img.logo { height: 70px; margin-bottom: 18px; }
  .cx-hero h1 { font-size: clamp(2rem, 6vw, 3.6rem); margin: 0 0 12px; }
  .cx-hero p { font-size: 1.2rem; max-width: 640px; margin: 0 auto 26px; opacity: .9; }
  .cx-phone { display: block; font-size: clamp(2.4rem, 9vw, 5rem); font-weight: 800; color: #FFD000; text-decoration: none; margin: 10px 0 24px; }
  .cx-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
  .cx-sec { padding: 60px 20px; max-width: 1080px; margin: auto; }
  .cx-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
  .cx-card { background: #f3f6fb; border-radius: 16px; padding: 22px; border-top: 5px solid var(--main); }
  .cx-card h3 { margin: 0 0 6px; }
  .cx-cta { background: var(--main); color: #fff; text-align: center; padding: 50px 20px; }
</style>

<div class="cx">
  <section class="cx-hero">
    <img class="logo" src="{logo}" alt="الشعار">
    <h1>{keyword} في {area} خلال 30 دقيقة</h1>
    <p>عربيتك عطلت؟ اتصل دلوقتي وخد السعر النهائي قبل ما الونش يتحرك. شغالين 24 ساعة.</p>
    <a class="cx-phone" href="{tel}">{phone}</a>
    <div class="cx-btns">{call_button} {wa_button}</div>
  </section>

  <section class="cx-sec">
    <h2>ليه تختار ونش الطيب؟</h2>
    <div class="cx-grid">
      <div class="cx-card"><h3>وصول سريع</h3><p>في المتوسط خلال 30 دقيقة جوه {area}.</p></div>
      <div class="cx-card"><h3>سعر واضح</h3><p>السعر النهائي بيتقال في التليفون، ومفيش زيادة بعد الوصول.</p></div>
      <div class="cx-card"><h3>سطحات هيدروليك</h3><p>لكل العربيات، والأوتوماتيك من غير ما الفتيس يتأذي.</p></div>
    </div>
  </section>

  <section class="cx-cta">
    <h2>واقف دلوقتي؟ متستناش</h2>
    <div class="cx-btns">{call_button} {wa_button}</div>
  </section>
</div>`;
  document.querySelector("[data-custom-starter]")?.addEventListener("click", () => {
    if (ta.value.trim() && !confirm("هيتحط النموذج مكان الكود الحالي. تكمّل؟")) return;
    ta.value = STARTER; ta.dispatchEvent(new Event("input", { bubbles: true })); refresh();
  });

  applyMode();
})();
