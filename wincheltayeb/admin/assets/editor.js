// محرر ونش الطيب: Markdown أو HTML، معاينة مباشرة، صور، روابط داخلية، حفظ تلقائي
(() => {
  const dataEl = document.getElementById("editor-data");
  const DATA = dataEl ? JSON.parse(dataEl.textContent) : { links: [], phone: "", phone_intl: "", whatsapp: "", csrf: "" };
  const md = (src) => (window.marked ? window.marked.parse(src || "", { gfm: true, breaks: false }) : "<p>المعاينة محتاجة ملف marked.</p>");
  let turndown = null;
  const toMd = (html) => {
    if (!window.TurndownService) return html;
    if (!turndown) {
      turndown = new window.TurndownService({ headingStyle: "atx", bulletListMarker: "-", codeBlockStyle: "fenced", emDelimiter: "*" });
      turndown.keep(["table", "iframe", "video", "figure", "details", "summary"]);
      turndown.remove(["style", "script", "meta"]);
    }
    return turndown.turndown(html);
  };
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
  const ytId = (u) => (String(u).match(/(?:youtu\.be\/|v=|embed\/|shorts\/|live\/)([A-Za-z0-9_-]{11})/) || [])[1] || (/^[A-Za-z0-9_-]{11}$/.test(u) ? u : null);

  // نافذة اختيار الروابط الداخلية (واحدة لكل الصفحة)
  let linkDialog = null;
  function pickLink() {
    return new Promise((resolve) => {
      if (!linkDialog) {
        linkDialog = document.createElement("dialog");
        linkDialog.className = "wte-dialog";
        linkDialog.innerHTML = `<form method="dialog"><h3>اختار صفحة من الموقع</h3>
          <input type="search" placeholder="دوّر بالاسم…" aria-label="بحث">
          <ul class="wte-links"></ul>
          <div class="wte-dialog-actions"><button value="cancel" class="btn btn-ghost">إلغاء</button></div></form>`;
        document.body.appendChild(linkDialog);
      }
      const input = linkDialog.querySelector("input");
      const list = linkDialog.querySelector("ul");
      const render = () => {
        const q = input.value.trim();
        list.innerHTML = DATA.links
          .filter(([t, u]) => !q || t.includes(q) || u.includes(q))
          .slice(0, 80)
          .map(([t, u]) => `<li><button type="button" data-u="${esc(u)}" data-t="${esc(t)}">${esc(t)} <small dir="ltr">${esc(u)}</small></button></li>`)
          .join("") || "<li class='muted'>مفيش نتايج</li>";
      };
      input.value = ""; render();
      input.oninput = render;
      list.onclick = (e) => {
        const b = e.target.closest("button[data-u]");
        if (!b) return;
        linkDialog.close();
        resolve({ url: b.dataset.u, title: b.dataset.t });
      };
      linkDialog.onclose = () => resolve(null);
      linkDialog.showModal();
      input.focus();
    });
  }

  async function uploadImage(file) {
    const fd = new FormData();
    fd.append("csrf", DATA.csrf);
    fd.append("file", file);
    const res = await fetch("upload.php", { method: "POST", body: fd, credentials: "same-origin" });
    const j = await res.json().catch(() => ({ ok: false, error: "رد مش مفهوم من السيرفر" }));
    if (!j.ok) throw new Error(j.error || "الرفع فشل");
    return j.url;
  }

  class Editor {
    constructor(root) {
      this.root = root;
      this.ta = root.querySelector("textarea");
      this.pv = root.querySelector(".wte-preview");
      this.fmt = root.querySelector("[data-format]");
      this.banner = root.querySelector(".wte-banner");
      this.fileInput = root.querySelector("[data-image-input]");
      this.pasteConvert = root.querySelector("[data-paste-convert]");
      this.key = root.dataset.autosave ? `wte:${root.dataset.autosave}:${this.ta.name}` : "";
      this.mode = "write";
      this.timer = null;
      this.lastFormat = this.fmt.value;
      this.bind();
      this.stats();
      this.checkAutosave();
    }
    get isHtml() { return this.fmt.value === "html"; }

    bind() {
      this.root.querySelectorAll("[data-mode]").forEach((b) => b.addEventListener("click", () => this.setMode(b.dataset.mode)));
      this.root.querySelectorAll("[data-cmd]").forEach((b) => b.addEventListener("click", () => this.cmd(b.dataset.cmd)));
      this.ta.addEventListener("input", () => { this.schedule(); this.stats(); this.autosave(); });
      this.ta.addEventListener("keydown", (e) => this.keys(e));
      this.ta.addEventListener("paste", (e) => this.paste(e));
      this.ta.addEventListener("dragover", (e) => { if ([...e.dataTransfer.items].some((i) => i.kind === "file")) { e.preventDefault(); this.ta.classList.add("drop"); } });
      this.ta.addEventListener("dragleave", () => this.ta.classList.remove("drop"));
      this.ta.addEventListener("drop", (e) => {
        const files = [...e.dataTransfer.files].filter((f) => f.type.startsWith("image/"));
        this.ta.classList.remove("drop");
        if (!files.length) return;
        e.preventDefault();
        files.forEach((f) => this.insertUploaded(f));
      });
      this.fileInput.addEventListener("change", () => { const f = this.fileInput.files[0]; if (f) this.insertUploaded(f); this.fileInput.value = ""; });
      this.fmt.addEventListener("change", () => this.switchFormat());
      document.addEventListener("keydown", (e) => { if (e.key === "Escape" && this.root.classList.contains("is-full")) this.toggleFull(); });
      this.root.closest("form")?.addEventListener("submit", () => { if (this.key) try { localStorage.removeItem(this.key); } catch {} });
    }

    // ---------- عرض ----------
    setMode(m) {
      this.mode = m;
      this.root.dataset.mode = m;
      this.root.querySelectorAll("[data-mode]").forEach((b) => b.setAttribute("aria-pressed", String(b.dataset.mode === m)));
      this.ta.hidden = m === "preview";
      this.pv.hidden = m === "write";
      if (m !== "write") this.render();
    }
    schedule() { if (this.mode === "write") return; clearTimeout(this.timer); this.timer = setTimeout(() => this.render(), 200); }
    render() { this.pv.innerHTML = this.isHtml ? this.ta.value : md(this.ta.value); }
    toggleFull() {
      this.root.classList.toggle("is-full");
      document.body.classList.toggle("wte-lock", this.root.classList.contains("is-full"));
      if (this.root.classList.contains("is-full") && this.mode === "write") this.setMode("split");
      this.ta.focus();
    }

    // ---------- إدخال ----------
    sel() { return { s: this.ta.selectionStart, e: this.ta.selectionEnd, text: this.ta.value.slice(this.ta.selectionStart, this.ta.selectionEnd) }; }
    replace(text, selectFrom = null, selectTo = null) {
      const { s, e } = this.sel();
      this.ta.focus();
      this.ta.setRangeText(text, s, e, "end");
      if (selectFrom !== null) this.ta.setSelectionRange(s + selectFrom, s + (selectTo ?? selectFrom));
      this.ta.dispatchEvent(new Event("input", { bubbles: true }));
    }
    wrap(before, after, placeholder) {
      const { text } = this.sel();
      const inner = text || placeholder;
      this.replace(before + inner + after, before.length, before.length + inner.length);
    }
    block(text) {
      const { s } = this.sel();
      const v = this.ta.value;
      const pre = s > 0 && v[s - 1] !== "\n" ? "\n\n" : s > 1 && v[s - 2] !== "\n" ? "\n" : "";
      this.replace(pre + text + "\n\n");
    }
    linePrefix(prefix, numbered = false) {
      const v = this.ta.value;
      let { s, e } = this.sel();
      s = v.lastIndexOf("\n", s - 1) + 1;
      const endNl = v.indexOf("\n", e);
      e = endNl === -1 ? v.length : endNl;
      const lines = v.slice(s, e).split("\n");
      const out = lines.map((l, i) => (numbered ? `${i + 1}. ` : prefix) + l.replace(/^(#{1,6} |> |- |\d+\. )/, "")).join("\n");
      this.ta.setSelectionRange(s, e);
      this.replace(out);
    }
    htmlList(tag) {
      const { text } = this.sel();
      const items = (text || "عنصر أول\nعنصر تاني").split("\n").filter(Boolean).map((l) => `  <li>${l}</li>`).join("\n");
      this.block(`<${tag}>\n${items}\n</${tag}>`);
    }

    async cmd(c) {
      const H = this.isHtml;
      switch (c) {
        case "fullscreen": return this.toggleFull();
        case "h2": return H ? this.wrap("<h2>", "</h2>", "عنوان") : this.linePrefix("## ");
        case "h3": return H ? this.wrap("<h3>", "</h3>", "عنوان فرعي") : this.linePrefix("### ");
        case "bold": return H ? this.wrap("<strong>", "</strong>", "كلام عريض") : this.wrap("**", "**", "كلام عريض");
        case "italic": return H ? this.wrap("<em>", "</em>", "كلام مايل") : this.wrap("*", "*", "كلام مايل");
        case "quote": return H ? this.wrap("<blockquote>", "</blockquote>", "ملاحظة مهمة") : this.linePrefix("> ");
        case "ul": return H ? this.htmlList("ul") : this.linePrefix("- ");
        case "ol": return H ? this.htmlList("ol") : this.linePrefix("", true);
        case "hr": return this.block(H ? "<hr>" : "---");
        case "table":
          return this.block(H
            ? "<table>\n  <thead><tr><th>العمود 1</th><th>العمود 2</th><th>العمود 3</th></tr></thead>\n  <tbody>\n    <tr><td></td><td></td><td></td></tr>\n  </tbody>\n</table>"
            : "| العمود 1 | العمود 2 | العمود 3 |\n|---|---|---|\n|  |  |  |\n|  |  |  |");
        case "link": {
          const url = prompt("الرابط (كامل، أو داخلي زي /areas/arbaeen):", "https://");
          if (!url || url === "https://") return;
          const t = this.sel().text || "نص الرابط";
          return this.replace(H ? `<a href="${esc(url)}">${t}</a>` : `[${t}](${url})`);
        }
        case "ilink": {
          const keep = this.sel();
          const r = await pickLink();
          if (!r) return;
          this.ta.setSelectionRange(keep.s, keep.e);
          const t = keep.text || r.title;
          return this.replace(H ? `<a href="${esc(r.url)}">${t}</a>` : `[${t}](${r.url})`);
        }
        case "image": return this.fileInput.click();
        case "youtube": {
          const u = prompt("رابط فيديو يوتيوب:");
          const id = u && ytId(u.trim());
          if (!id) { if (u) alert("الرابط ده مش رابط يوتيوب صحيح."); return; }
          return this.block(`<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/${id}" title="فيديو" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>`);
        }
        case "cta": {
          const tel = DATA.phone_intl || ("+2" + DATA.phone);
          return this.block(H
            ? `<div class="callout"><strong>محتاج ونش دلوقتي؟</strong> اتصل على <a href="tel:${tel}">${DATA.phone}</a> أو <a href="https://wa.me/${DATA.whatsapp}">ابعت لوكيشن واتساب</a>.</div>`
            : `> **محتاج ونش دلوقتي؟** اتصل على [${DATA.phone}](tel:${tel}) أو [ابعت لوكيشن واتساب](https://wa.me/${DATA.whatsapp}).`);
        }
      }
    }

    async insertUploaded(file) {
      const alt = prompt("اكتب وصف للصورة (مهم للسيو، زي: ونش بيسحب عربية على طريق الأدبية):", "") || "";
      this.flash("بيرفع الصورة…");
      try {
        const url = await uploadImage(file);
        this.block(this.isHtml ? `<img src="${esc(url)}" alt="${esc(alt)}" loading="lazy">` : `![${alt}](${url})`);
        this.flash("الصورة اترفعت واتحطت.", "ok", 2500);
      } catch (e) { this.flash(e.message, "err", 5000); }
    }

    keys(e) {
      const mod = e.ctrlKey || e.metaKey;
      if (!mod) return;
      const k = e.key.toLowerCase();
      const map = { b: "bold", i: "italic", k: "link" };
      if (map[k]) { e.preventDefault(); this.cmd(map[k]); }
      if (k === "s") { e.preventDefault(); this.root.closest("form")?.requestSubmit(); }
    }

    paste(e) {
      if (this.isHtml || !this.pasteConvert.checked) return;
      const html = e.clipboardData?.getData("text/html");
      if (!html || !window.TurndownService) return;
      e.preventDefault();
      const clean = toMd(html.replace(/<!--[\s\S]*?-->/g, "")).replace(/\n{3,}/g, "\n\n").trim();
      this.replace(clean);
      this.flash("اللصق اتحوّل لـ Markdown نضيف. لو عايزه زي ما هو، اقفل «تنظيف اللصق».", "ok", 3500);
    }

    switchFormat() {
      const to = this.fmt.value;
      const v = this.ta.value.trim();
      if (v && to !== this.lastFormat) {
        if (confirm(to === "html" ? "تحوّل المحتوى الحالي من Markdown لـ HTML؟ (Cancel = سيبه زي ما هو)" : "تحوّل المحتوى الحالي من HTML لـ Markdown؟ (Cancel = سيبه زي ما هو)")) {
          this.ta.value = to === "html" ? md(v).trim() : toMd(v);
          this.ta.dispatchEvent(new Event("input", { bubbles: true }));
        }
      }
      this.lastFormat = to;
      this.render();
    }

    // ---------- إحصائيات وحفظ تلقائي ----------
    stats() {
      const v = this.ta.value;
      const text = (this.isHtml ? v.replace(/<[^>]+>/g, " ") : v.replace(/[#>*_`\[\]()|-]/g, " ")).trim();
      const words = text ? text.split(/\s+/).length : 0;
      const h2 = (v.match(this.isHtml ? /<h2[\s>]/gi : /^## /gm) || []).length;
      const links = (v.match(this.isHtml ? /href="\/(?!\/)/g : /\]\(\/(?!\/)/g) || []).length;
      const imgs = (v.match(this.isHtml ? /<img/gi : /!\[/g) || []).length;
      const noAlt = (v.match(this.isHtml ? /<img(?![^>]*alt="[^"]+")[^>]*>/gi : /!\[\]\(/g) || []).length;
      const st = (n) => this.root.querySelector(`[data-stat="${n}"]`);
      st("words").textContent = `${words.toLocaleString("ar-EG")} كلمة • ${Math.max(1, Math.round(words / 200))} دقيقة قراءة`;
      st("h2").textContent = `عناوين H2: ${h2}`;
      st("h2").className = h2 < 2 && words > 200 ? "warn" : "";
      st("links").textContent = `روابط داخلية: ${links}`;
      st("links").className = links < 2 && words > 200 ? "warn" : "";
      st("imgs").textContent = imgs ? `صور: ${imgs}${noAlt ? ` (${noAlt} من غير وصف)` : ""}` : "";
      st("imgs").className = noAlt ? "warn" : "";
    }
    autosave() {
      if (!this.key) return;
      clearTimeout(this.saveTimer);
      this.saveTimer = setTimeout(() => {
        try {
          localStorage.setItem(this.key, JSON.stringify({ v: this.ta.value, f: this.fmt.value, t: Date.now() }));
          const d = new Date();
          this.root.querySelector('[data-stat="saved"]').textContent = `اتحفظ مؤقتاً ${d.getHours()}:${String(d.getMinutes()).padStart(2, "0")}`;
        } catch {}
      }, 1500);
    }
    checkAutosave() {
      if (!this.key) return;
      let saved = null;
      try { saved = JSON.parse(localStorage.getItem(this.key) || "null"); } catch {}
      if (!saved || saved.v === this.ta.value) return;
      const when = new Date(saved.t);
      this.banner.innerHTML = `فيه نسخة محفوظة تلقائياً من ${when.toLocaleString("ar-EG")} مختلفة عن المحفوظ. <button type="button" data-r="1" class="link-btn">استرجعها</button> <button type="button" data-r="0" class="link-btn">تجاهلها</button>`;
      this.banner.hidden = false;
      this.banner.onclick = (e) => {
        const b = e.target.closest("[data-r]");
        if (!b) return;
        if (b.dataset.r === "1") { this.ta.value = saved.v; this.fmt.value = saved.f || this.fmt.value; this.lastFormat = this.fmt.value; this.ta.dispatchEvent(new Event("input", { bubbles: true })); }
        else try { localStorage.removeItem(this.key); } catch {}
        this.banner.hidden = true;
      };
    }
    flash(msg, kind = "", ms = 0) {
      this.banner.className = "wte-banner " + kind;
      this.banner.textContent = msg;
      this.banner.hidden = false;
      clearTimeout(this.flashT);
      if (ms) this.flashT = setTimeout(() => (this.banner.hidden = true), ms);
    }
  }

  const init = (root = document) => root.querySelectorAll("[data-editor]:not([data-ready])").forEach((el) => { el.dataset.ready = "1"; el._editor = new Editor(el); });
  window.WTEditor = { init };
  init();
})();
