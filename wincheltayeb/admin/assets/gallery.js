// رفع جماعي للمعرض + وصف بالـ AI + تعديل سريع
(() => {
  const box = document.getElementById("bulk-upload");
  const csrf = box ? box.dataset.csrf : (document.querySelector('input[name="csrf"]') || {}).value;
  const esc = (s) => String(s ?? "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);

  function post(data, onProgress) {
    return new Promise((resolve) => {
      const fd = new FormData();
      Object.entries(data).forEach(([k, v]) => fd.append(k, v));
      fd.append("csrf", csrf);
      const xhr = new XMLHttpRequest();
      xhr.open("POST", "gallery_upload.php");
      xhr.withCredentials = true;
      if (onProgress) xhr.upload.onprogress = (e) => e.lengthComputable && onProgress(e.loaded / e.total);
      xhr.onload = () => { try { resolve(JSON.parse(xhr.responseText)); } catch { resolve({ ok: false, error: `رد مش مفهوم من السيرفر (${xhr.status}). ممكن الملف أكبر من حد الاستضافة.` }); } };
      xhr.onerror = () => resolve({ ok: false, error: "النت فصل أثناء الرفع." });
      xhr.send(fd);
    });
  }

  // تصغير الصورة في المتصفح (وبيشيل بيانات EXIF زي الموقع الجغرافي)
  async function shrink(file, max = 1920) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return file;
    try {
      const bmp = await createImageBitmap(file, { imageOrientation: "from-image" });
      const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
      const w = Math.round(bmp.width * scale), h = Math.round(bmp.height * scale);
      const c = document.createElement("canvas"); c.width = w; c.height = h;
      c.getContext("2d").drawImage(bmp, 0, 0, w, h);
      const type = file.type === "image/png" ? "image/png" : "image/jpeg";
      const blob = await new Promise((r) => c.toBlob(r, type, 0.85));
      if (!blob || (scale === 1 && blob.size >= file.size)) return file;
      return new File([blob], file.name.replace(/\.\w+$/, type === "image/png" ? ".png" : ".jpg"), { type });
    } catch { return file; }
  }

  // ---------- الرفع الجماعي ----------
  if (box) {
    const zone = box.querySelector(".dropzone");
    const input = zone.querySelector("input[type=file]");
    const list = box.querySelector(".upload-list");
    const summary = box.querySelector(".bulk-summary");
    const maxBytes = (Number(box.dataset.maxMb) || 60) * 1024 * 1024;
    const usageEl = box.querySelector("[data-usage]");
    let queue = [], running = 0, stats = { done: 0, failed: 0, total: 0, ai: 0 }, finishing = false;

    const pick = () => input.click();
    zone.addEventListener("click", pick);
    zone.addEventListener("keydown", (e) => { if (e.key === "Enter" || e.key === " ") { e.preventDefault(); pick(); } });
    input.addEventListener("change", () => { add([...input.files]); input.value = ""; });
    ["dragenter", "dragover"].forEach((ev) => zone.addEventListener(ev, (e) => { e.preventDefault(); zone.classList.add("over"); }));
    ["dragleave", "drop"].forEach((ev) => zone.addEventListener(ev, () => zone.classList.remove("over")));
    zone.addEventListener("drop", (e) => { e.preventDefault(); add([...e.dataTransfer.files]); });

    function add(files) {
      const ok = files.filter((f) => /^(image\/(jpeg|png|webp)|video\/(mp4|webm))$/.test(f.type));
      if (ok.length < files.length) alert(`اتساب ${files.length - ok.length} ملف نوعه مش مدعوم (المسموح: jpg, png, webp, mp4, webm).`);
      ok.forEach((f) => {
        const li = document.createElement("li");
        li.className = "up-item";
        const isImg = f.type.startsWith("image/");
        const preview = isImg ? `<img src="${URL.createObjectURL(f)}" alt="">` : `<span class="up-vid">فيديو</span>`;
        li.innerHTML = `${preview}<div class="up-main"><div class="up-name">${esc(f.name)}</div><div class="up-bar"><span></span></div><div class="up-status">في الانتظار…</div></div>`;
        list.appendChild(li);
        queue.push({ file: f, li });
        stats.total++;
      });
      updateSummary();
      pump();
    }

    function updateSummary() {
      summary.hidden = stats.total === 0;
      summary.textContent = `اترفع ${stats.done} من ${stats.total}` + (stats.ai ? ` • الـ AI وصف ${stats.ai}` : "") + (stats.failed ? ` • فشل ${stats.failed}` : "");
    }

    function pump() {
      while (running < 2 && queue.length) run(queue.shift());
      if (!running && !queue.length && stats.total && !finishing) finish();
    }

    async function run({ file, li }) {
      running++;
      const status = li.querySelector(".up-status"), bar = li.querySelector(".up-bar span");
      const useAi = box.querySelector("[data-ai]")?.checked ? "1" : "";
      try {
        let f = file;
        if (box.querySelector("[data-resize]").checked && f.type.startsWith("image/")) { status.textContent = "بيصغّر الصورة…"; f = await shrink(f); }
        if (f.size > maxBytes) throw new Error(`الملف أكبر من ${box.dataset.maxMb} ميجا.`);
        status.textContent = "بيرفع…";
        const r = await post({ action: "upload", file: f, area_slug: box.querySelector("[data-area]").value, ai: useAi }, (p) => {
          bar.style.width = Math.round(p * 100) + "%";
          if (p >= 1) status.textContent = useAi && f.type.startsWith("image/") ? "الـ AI بيوصف الصورة…" : "بيحفظ…";
        });
        if (!r.ok) throw new Error(r.error || "فشل الرفع");
        stats.done++;
        if (r.ai_used) stats.ai++;
        if (usageEl && r.usage != null) usageEl.textContent = r.usage;
        li.classList.add("done");
        bar.style.width = "100%";
        const notes = [];
        if (r.ai_error) notes.push(`<span class="warn-text">الـ AI موصفش: ${esc(r.ai_error)}</span>`);
        if (r.privacy) notes.push(`<span class="warn-text">⚠ فيه لوحة أرقام أو وش واضح. يفضّل تغطيه قبل ما تسيب الصورة منشورة.</span>`);
        if (r.relevant === false) notes.push(`<span class="warn-text">⚠ الصورة شكلها ملهاش علاقة بالونش أو العربيات.</span>`);
        li.querySelector(".up-main").innerHTML = `
          <form class="g-edit-form" data-id="${r.item.id}">
            <label>العنوان ${r.ai_used ? '<span class="tag">AI</span>' : ""}<input name="title" value="${esc(r.item.title)}" required></label>
            <label>الوصف <textarea name="caption" rows="2">${esc(r.item.caption)}</textarea></label>
            <input type="hidden" name="area_slug" value="${esc(box.querySelector("[data-area]").value)}">
            ${notes.join("<br>")}
            <div><button class="btn btn-ghost">احفظ التعديل</button> <span class="g-status" aria-live="polite">${r.ai_used ? "اتحفظ بعنوان الـ AI. عدّله لو محتاج." : "اتحفظ. اكتب عنوان يوصف الصورة."}</span></div>
          </form>`;
        wireEdit(li.querySelector("form"));
      } catch (e) {
        stats.failed++;
        li.classList.add("failed");
        status.innerHTML = `<span class="warn-text">${esc(e.message)}</span>`;
      } finally {
        running--;
        updateSummary();
        pump();
      }
    }

    async function finish() {
      finishing = true;
      const r = await post({ action: "finish" });
      summary.textContent += r.revalidate_ok ? " • الموقع اتحدث." : ` • ${r.revalidate || ""}`;
      const more = document.createElement("a");
      more.href = "gallery.php"; more.className = "link-btn"; more.textContent = " حدّث الصفحة عشان تشوفهم في المعرض";
      summary.appendChild(more);
      finishing = false;
    }
  }

  // ---------- تعديل سريع للعنوان والوصف ----------
  function wireEdit(form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const st = form.querySelector(".g-status");
      const btn = form.querySelector("button");
      btn.disabled = true; st.textContent = "بيحفظ…";
      const r = await post({ action: "update", id: form.dataset.id, title: form.title.value, caption: form.caption.value, area_slug: form.area_slug ? form.area_slug.value : "" });
      btn.disabled = false;
      st.textContent = r.ok ? "اتحفظ ✓" : (r.error || "فشل الحفظ");
      const card = form.closest("li");
      if (r.ok && card) {
        const t = card.querySelector("[data-g-title]"); if (t) t.textContent = form.title.value;
        const c = card.querySelector("[data-g-caption]"); if (c) c.textContent = form.caption.value;
      }
    });
  }
  document.querySelectorAll(".admin-gallery .g-edit-form").forEach(wireEdit);
})();
