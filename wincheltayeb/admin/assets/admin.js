(() => {
  // تأكيد قبل المسح
  document.querySelectorAll("form[data-confirm]").forEach((f) =>
    f.addEventListener("submit", (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); })
  );

  // عدادات السيو ومعاينة جوجل
  const counters = document.querySelectorAll(".counter");
  const serpT = document.getElementById("serp-title"), serpD = document.getElementById("serp-desc");
  const title = document.getElementById("title"), mt = document.getElementById("meta_title"), md = document.getElementById("meta_description");
  const updateSeo = () => {
    counters.forEach((c) => {
      const el = document.getElementById(c.dataset.for); const n = el.value.length; const max = +c.dataset.max;
      c.textContent = `${n} / ${max} حرف` + (n > max ? " (جوجل هيقطعه)" : n < max * 0.5 ? " (قصير)" : "");
      c.classList.toggle("over", n > max);
    });
    if (serpT) serpT.textContent = (mt.value || title.value || "عنوان الصفحة").slice(0, 65);
    if (serpD) serpD.textContent = (md.value || "اكتب وصف للصفحة يظهر في نتايج جوجل.").slice(0, 165);
  };
  [title, mt, md].forEach((el) => el && el.addEventListener("input", updateSeo));
  if (counters.length) updateSeo();

  // توليد slug من العنوان للصفحات الجديدة
  const slug = document.getElementById("slug");
  if (slug && title && !slug.value) {
    title.addEventListener("input", () => {
      if (slug.dataset.touched) return;
      slug.placeholder = "اكتب رابط إنجليزي قصير، مثلاً: ain-sokhna";
    });
    slug.addEventListener("input", () => (slug.dataset.touched = "1"));
  }

  // محرر Markdown
  const body = document.getElementById("body");
  if (body) {
    const count = document.getElementById("body-count");
    const upd = () => (count.textContent = body.value.length.toLocaleString("ar-EG"));
    body.addEventListener("input", upd); upd();
    const insert = (before, after = "") => {
      const s = body.selectionStart, e = body.selectionEnd, sel = body.value.slice(s, e);
      body.setRangeText(before + sel + after, s, e, "end"); body.focus(); upd();
    };
    document.querySelectorAll("[data-md]").forEach((b) => b.addEventListener("click", () => {
      const s = body.selectionStart; const lineStart = body.value.lastIndexOf("\n", s - 1) + 1;
      body.setRangeText(b.dataset.md, lineStart, lineStart, "end"); body.focus();
    }));
    document.querySelectorAll("[data-wrap]").forEach((b) => b.addEventListener("click", () => insert(b.dataset.wrap, b.dataset.wrap)));
    document.querySelectorAll("[data-link]").forEach((b) => b.addEventListener("click", () => {
      const url = prompt("الرابط (داخلي زي /areas/arbaeen أو كامل):"); if (url) insert("[", `](${url})`);
    }));
    const prev = document.getElementById("preview"), tog = document.getElementById("toggle-preview");
    tog.addEventListener("click", () => {
      const show = prev.hidden;
      if (show) prev.innerHTML = window.marked ? window.marked.parse(body.value) : "<p>المعاينة محتاجة إنترنت.</p>";
      prev.hidden = !show; body.hidden = show; tog.textContent = show ? "رجوع للتعديل" : "معاينة";
    });
  }

  // اختيار مكان المنطقة على الخريطة
  const picker = document.getElementById("picker");
  if (picker && window.L) {
    const lat = document.getElementById("lat"), lng = document.getElementById("lng");
    const start = [parseFloat(lat.value) || 29.9668, parseFloat(lng.value) || 32.5498];
    const map = L.map(picker).setView(start, lat.value ? 12 : 10);
    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 18, attribution: "&copy; OpenStreetMap" }).addTo(map);
    const m = L.marker(start, { draggable: true }).addTo(map);
    const set = (ll) => { lat.value = ll.lat.toFixed(5); lng.value = ll.lng.toFixed(5); m.setLatLng(ll); };
    map.on("click", (e) => set(e.latlng));
    m.on("dragend", () => set(m.getLatLng()));
    [lat, lng].forEach((el) => el.addEventListener("change", () => {
      const ll = L.latLng(parseFloat(lat.value), parseFloat(lng.value)); if (!isNaN(ll.lat)) { m.setLatLng(ll); map.panTo(ll); }
    }));
  }
})();

// مساعد الكتابة بالذكاء الاصطناعي
(() => {
  const box = document.getElementById("ai-box");
  const form = document.getElementById("editor");
  if (!box || !form) return;
  const buttons = box.querySelectorAll("[data-ai]");
  if (!buttons.length) return;
  const status = document.getElementById("ai-status");
  const undoBtn = document.getElementById("ai-undo");
  const usage = document.getElementById("ai-usage");
  const csrf = form.querySelector('input[name="csrf"]').value;
  const ids = ["title", "excerpt", "body", "meta_title", "meta_description", "faqs"];
  const el = (id) => document.getElementById(id);
  let snapshot = null;

  const setStatus = (text, kind) => { status.textContent = text; status.className = "ai-status " + (kind || ""); };
  const fill = (id, value) => {
    const f = el(id);
    if (!f || typeof value !== "string" || value === "") return;
    f.value = value;
    f.dispatchEvent(new Event("input", { bubbles: true }));
    f.classList.remove("ai-filled"); void f.offsetWidth; f.classList.add("ai-filled");
  };
  const labels = { write: "بيكتب المقال… ممكن ياخد دقيقة أو اتنين", improve: "بيحسّن المحتوى… ممكن ياخد دقيقة", seo: "بيكتب عنوان ووصف جوجل…", faqs: "بيكتب الأسئلة…" };
  const done = { write: "المقال اتكتب. راجعه وبعدين دوس احفظ.", improve: "المحتوى اتحسّن. راجعه وبعدين دوس احفظ.", seo: "عنوان ووصف جوجل جاهزين.", faqs: "الأسئلة جاهزة." };

  buttons.forEach((btn) => btn.addEventListener("click", async () => {
    const action = btn.dataset.ai;
    const body = el("body").value.trim();
    if (action === "write" && body.length > 200 && !confirm("المحتوى الحالي هيتستبدل بمقال جديد. تقدر ترجعه بزرار التراجع. تكمّل؟")) return;

    const fd = new FormData();
    fd.append("csrf", csrf);
    fd.append("action", action);
    fd.append("type", form.dataset.type || "blog");
    fd.append("title", el("title").value);
    fd.append("body", el("body").value);
    fd.append("idea", (el("ai-idea") || {}).value || "");
    fd.append("place_name", (el("place_name") || {}).value || "");

    buttons.forEach((b) => (b.disabled = true));
    setStatus(labels[action], "busy");
    try {
      const res = await fetch("ai.php", { method: "POST", body: fd, credentials: "same-origin" });
      let data;
      try { data = await res.json(); } catch { throw new Error("السيرفر رجّع رد مش مفهوم (" + res.status + "). ممكن الطلب أخد وقت أطول من المسموح في الاستضافة."); }
      if (!data.ok) throw new Error(data.error || "حصل خطأ.");
      snapshot = Object.fromEntries(ids.map((id) => [id, el(id) ? el(id).value : ""]));
      const f = data.fields || {};
      if (action === "faqs" && el("faqs").value.trim()) {
        if (confirm("فيه أسئلة موجودة. دوس OK عشان تضيف الجديدة تحتها، أو Cancel عشان تستبدلها.")) f.faqs = el("faqs").value.trim() + "\n\n" + f.faqs;
      }
      if (action === "write" && el("title").value.trim()) delete f.title; // العنوان اللي كتبته بيفضل زي ما هو
      Object.entries(f).forEach(([k, v]) => fill(k, v));
      if (usage && data.usage != null) usage.textContent = data.usage;
      undoBtn.hidden = false;
      setStatus(done[action], "ok");
    } catch (e) {
      setStatus(e.message, "err");
    } finally {
      buttons.forEach((b) => (b.disabled = false));
    }
  }));

  undoBtn.addEventListener("click", () => {
    if (!snapshot) return;
    Object.entries(snapshot).forEach(([k, v]) => { const f = el(k); if (f) { f.value = v; f.dispatchEvent(new Event("input", { bubbles: true })); } });
    snapshot = null; undoBtn.hidden = true;
    setStatus("رجعنا للنسخة اللي قبل الـ AI.", "");
  });
})();
