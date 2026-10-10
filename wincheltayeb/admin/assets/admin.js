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
    const fmtSel = form.querySelector('select[name="format"]');
    fd.append("format", fmtSel ? fmtSel.value : "markdown");

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
      if (action === "write" && fmtSel && fmtSel.value === "html") { fmtSel.value = "markdown"; fmtSel.dispatchEvent(new Event("change")); }
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

// التصرفات الجماعية: علّم على الكل، العدد، والتأكيد قبل المسح
(() => {
  document.querySelectorAll("form[data-bulk]").forEach((form) => {
    const id = form.id;
    const items = () => [...document.querySelectorAll(`.bulk-item[form="${id}"]`)];
    const all = document.querySelector(`.bulk-all[data-form="${id}"]`);
    const count = form.querySelector(".bulk-count strong");
    const btn = form.querySelector("button");
    const op = form.querySelector('select[name="op"]');
    const update = () => {
      const list = items(); const n = list.filter((c) => c.checked).length;
      count.textContent = n;
      btn.disabled = n === 0 || (op && !op.value);
      form.classList.toggle("has-selection", n > 0);
      if (all) { all.checked = n > 0 && n === list.length; all.indeterminate = n > 0 && n < list.length; }
      list.forEach((c) => c.closest("tr, li")?.classList.toggle("is-selected", c.checked));
    };
    items().forEach((c) => c.addEventListener("change", update));
    if (all) all.addEventListener("change", () => { items().forEach((c) => (c.checked = all.checked)); update(); });
    if (op) op.addEventListener("change", update);
    // Shift + click لتحديد مجموعة متتالية
    let last = null;
    items().forEach((c) => c.addEventListener("click", (e) => {
      const list = items();
      if (e.shiftKey && last) {
        const [a, b] = [list.indexOf(last), list.indexOf(c)].sort((x, y) => x - y);
        list.slice(a, b + 1).forEach((x) => (x.checked = c.checked)); update();
      }
      last = c;
    }));
    form.addEventListener("submit", (e) => {
      const n = items().filter((c) => c.checked).length;
      const isDelete = (op ? op.value : form.dataset.op) === "delete";
      if (isDelete && !confirm(`هتمسح ${n} عنصر نهائياً. متأكد؟`)) e.preventDefault();
    });
    update();
  });
})();

// قوايم قابلة للترتيب (أقسام الرئيسية والقايمة): سحب، أسهم، إضافة، مسح، إظهار/إخفاء
(() => {
  const lists = document.querySelectorAll("[data-sortable]");
  if (!lists.length) return;
  let dirty = false;
  const markDirty = () => { dirty = true; };
  document.querySelectorAll("form[data-dirty-guard]").forEach((f) => {
    f.addEventListener("input", markDirty);
    f.addEventListener("change", markDirty);
    f.addEventListener("submit", () => { dirty = false; });
  });
  window.addEventListener("beforeunload", (e) => { if (dirty) { e.preventDefault(); e.returnValue = ""; } });

  const wire = (item) => {
    const handle = item.querySelector(".drag");
    if (handle) {
      handle.addEventListener("mousedown", () => (item.draggable = true));
      handle.addEventListener("touchstart", () => (item.draggable = true), { passive: true });
    }
    item.addEventListener("dragstart", (e) => { item.classList.add("dragging"); e.dataTransfer.effectAllowed = "move"; e.dataTransfer.setData("text/plain", ""); });
    item.addEventListener("dragend", () => { item.classList.remove("dragging"); item.draggable = false; markDirty(); });
    const en = item.querySelector("[data-enabled]");
    if (en) en.addEventListener("change", () => item.classList.toggle("is-off", !en.checked));
    const tg = item.querySelector(".sec-toggle");
    const body = item.querySelector(".sec-body");
    if (tg && body) tg.addEventListener("click", () => { body.hidden = !body.hidden; tg.setAttribute("aria-expanded", String(!body.hidden)); });
    const ti = item.querySelector("[data-title-input]");
    const tl = item.querySelector("[data-title-label]");
    if (ti && tl) ti.addEventListener("input", () => { if (ti.value.trim()) tl.textContent = ti.value; });
  };

  lists.forEach((list) => {
    list.querySelectorAll(":scope > [data-item]").forEach(wire);
    list.addEventListener("dragover", (e) => {
      const dragging = list.querySelector(".dragging");
      if (!dragging) return;
      e.preventDefault();
      const after = [...list.querySelectorAll(":scope > [data-item]:not(.dragging)")].find((el) => {
        const r = el.getBoundingClientRect();
        return e.clientY < r.top + r.height / 2;
      });
      if (after) list.insertBefore(dragging, after); else list.appendChild(dragging);
    });
    list.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-move], [data-remove]");
      if (!btn) return;
      const item = btn.closest("[data-item]");
      if (btn.dataset.move === "up" && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
      else if (btn.dataset.move === "down" && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
      else if (btn.hasAttribute("data-remove")) { if (!confirm("تمسح العنصر ده؟ (التغيير مش هيتحفظ غير لما تدوس احفظ)")) return; item.remove(); }
      btn.focus();
      markDirty();
    });
  });

  document.querySelectorAll("[data-add]").forEach((btn) => btn.addEventListener("click", () => {
    const tpl = document.getElementById(btn.dataset.add);
    const list = document.getElementById(btn.dataset.addTo);
    const id = "c_" + Math.random().toString(36).slice(2, 9);
    const wrap = document.createElement("div");
    wrap.innerHTML = tpl.innerHTML.replaceAll("__ID__", id);
    const item = wrap.firstElementChild;
    list.appendChild(item);
    wire(item);
    const body = item.querySelector(".sec-body");
    if (body) { body.hidden = false; item.querySelector(".sec-toggle")?.setAttribute("aria-expanded", "true"); }
    if (window.WTEditor) window.WTEditor.init(item);
    item.scrollIntoView({ behavior: "smooth", block: "center" });
    item.querySelector("input:not([type=hidden]), textarea")?.focus();
    markDirty();
  }));
})();

// فحص الرابط وإنت بتكتبه: مكرر؟ محجوز؟
(() => {
  document.querySelectorAll("[data-slug-check]").forEach((input) => {
    const out = input.closest("label")?.querySelector(".slug-status") || input.parentNode.appendChild(Object.assign(document.createElement("span"), { className: "slug-status" }));
    let t = null, last = "";
    const check = async () => {
      const v = input.value.trim();
      if (v === last) return; last = v;
      if (!v) { out.textContent = ""; return; }
      const u = new URLSearchParams({ kind: input.dataset.slugCheck, type: input.dataset.type || "", id: input.dataset.id || "0", slug: v });
      try {
        const r = await (await fetch("slug_check.php?" + u, { credentials: "same-origin" })).json();
        out.className = "slug-status " + (r.ok ? "ok" : "bad");
        out.innerHTML = "";
        out.append(r.ok ? `✓ متاح: ` : `✗ ${r.msg} `);
        if (r.ok) { const a = document.createElement("span"); a.dir = "ltr"; a.textContent = r.url; out.append(a); }
        if (r.suggest) {
          const b = document.createElement("button"); b.type = "button"; b.className = "link-btn"; b.textContent = `استخدم «${r.suggest}»`;
          b.onclick = () => { input.value = r.suggest; last = ""; check(); };
          out.append(b);
        }
        if (r.slug && r.slug !== v && r.ok) out.append(` (هيتحفظ كده: ${r.slug})`);
        input.setCustomValidity(r.ok ? "" : r.msg);
      } catch { out.textContent = ""; }
    };
    input.addEventListener("input", () => { clearTimeout(t); t = setTimeout(check, 350); });
    input.addEventListener("blur", check);
    if (input.value) check();
  });
})();

// اختيار صور من المعرض لصفحة الهبوط
(() => {
  const ta = document.getElementById("lp-images");
  if (!ta) return;
  document.querySelectorAll("[data-pick]").forEach((b) => b.addEventListener("click", () => {
    const lines = ta.value.split("\n").map((x) => x.trim()).filter(Boolean);
    const u = b.dataset.pick;
    const i = lines.indexOf(u);
    if (i >= 0) lines.splice(i, 1); else lines.push(u);
    ta.value = lines.join("\n");
    b.classList.toggle("picked", i < 0);
    ta.dispatchEvent(new Event("input", { bubbles: true }));
  }));
  const cur = ta.value.split("\n").map((x) => x.trim());
  document.querySelectorAll("[data-pick]").forEach((b) => b.classList.toggle("picked", cur.includes(b.dataset.pick)));
})();

// رفع صور من أي خانة صورة: زرار «ارفع» جنب الخانة، أو كذا صورة لمعرض
(() => {
  const csrf = () => (document.querySelector('input[name="csrf"]') || {}).value || "";
  const upload = async (file) => {
    const fd = new FormData(); fd.append("csrf", csrf()); fd.append("file", file);
    const r = await (await fetch("upload.php", { method: "POST", body: fd, credentials: "same-origin" })).json().catch(() => ({ ok: false, error: "رد مش مفهوم" }));
    if (!r.ok) throw new Error(r.error || "الرفع فشل");
    return r.url;
  };
  const pick = (multiple) => new Promise((resolve) => {
    const i = document.createElement("input"); i.type = "file"; i.accept = "image/jpeg,image/png,image/webp,image/gif"; i.multiple = multiple;
    i.onchange = () => resolve([...i.files]); i.click();
  });
  const preview = (field) => {
    const inp = field.querySelector("[data-img-input]"), img = field.querySelector(".img-prev");
    if (!inp || !img) return;
    img.src = inp.value; img.hidden = !inp.value;
  };
  let galleryTarget = null;
  document.addEventListener("click", async (e) => {
    const up = e.target.closest("[data-upload]");
    if (up) {
      const field = up.closest(".img-field"); const inp = field.querySelector("[data-img-input]");
      const [f] = await pick(false); if (!f) return;
      up.disabled = true; const old = up.textContent; up.textContent = "بيرفع…";
      try { inp.value = await upload(f); inp.dispatchEvent(new Event("input", { bubbles: true })); preview(field); }
      catch (err) { alert(err.message); }
      finally { up.disabled = false; up.textContent = old; }
      return;
    }
    const multi = e.target.closest("[data-upload-multi]");
    if (multi) {
      const ta = multi.closest(".sec-body").querySelector('textarea[name$="[images]"]');
      const files = await pick(true); if (!files.length) return;
      multi.disabled = true;
      for (let i = 0; i < files.length; i++) {
        multi.textContent = `بيرفع ${i + 1} من ${files.length}…`;
        try { const u = await upload(files[i]); ta.value = (ta.value.trim() ? ta.value.trim() + "\n" : "") + u; ta.dispatchEvent(new Event("input", { bubbles: true })); }
        catch (err) { alert(files[i].name + ": " + err.message); }
      }
      multi.disabled = false; multi.textContent = "ارفع صور (كذا صورة مرة واحدة)";
      return;
    }
    const po = e.target.closest("[data-pick-open]");
    if (po) {
      galleryTarget = po.closest(".sec-body").querySelector('textarea[name$="[images]"]');
      document.querySelectorAll(".pick-gallery.small").forEach((g) => g.classList.add("picking"));
      po.textContent = "دوس على الصور في «صور المعرض» على الجنب ✓";
      document.querySelector(".pick-gallery.small")?.scrollIntoView({ behavior: "smooth", block: "center" });
      return;
    }
    const cp = e.target.closest("[data-copy-url]");
    if (cp) {
      const u = cp.dataset.copyUrl;
      if (galleryTarget) {
        galleryTarget.value = (galleryTarget.value.trim() ? galleryTarget.value.trim() + "\n" : "") + u;
        galleryTarget.dispatchEvent(new Event("input", { bubbles: true }));
        cp.classList.add("picked");
      } else {
        try { await navigator.clipboard.writeText(u); cp.classList.add("picked"); setTimeout(() => cp.classList.remove("picked"), 800); } catch { prompt("انسخ الرابط:", u); }
      }
    }
  });
  document.addEventListener("input", (e) => { if (e.target.matches("[data-img-input]")) preview(e.target.closest(".img-field")); });
})();
