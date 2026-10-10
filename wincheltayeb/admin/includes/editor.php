<?php
/**
 * محرر المحتوى الاحترافي: Markdown أو HTML، معاينة مباشرة، رفع صور، روابط داخلية، حفظ تلقائي.
 * $name: اسم حقل المحتوى، $formatName: اسم حقل الصيغة
 */
function editor_field(string $name, string $value, string $format, string $formatName, string $id, string $autosaveKey = '', int $rows = 22, string $label = 'المحتوى'): void {
    editor_data();
    $format = clean_format($format);
    ?>
<div class="wte" data-editor data-autosave="<?= h($autosaveKey) ?>">
  <div class="wte-top">
    <span class="wte-label"><?= h($label) ?></span>
    <label class="wte-format">الصيغة
      <select name="<?= h($formatName) ?>" data-format>
        <option value="markdown"<?= $format === 'markdown' ? ' selected' : '' ?>>Markdown (ويقبل HTML جواه)</option>
        <option value="html"<?= $format === 'html' ? ' selected' : '' ?>>HTML كامل</option>
      </select>
    </label>
    <div class="wte-modes" role="group" aria-label="طريقة العرض">
      <button type="button" data-mode="write" aria-pressed="true">كتابة</button>
      <button type="button" data-mode="split" aria-pressed="false">جنب بعض</button>
      <button type="button" data-mode="preview" aria-pressed="false">معاينة</button>
      <button type="button" data-cmd="fullscreen" title="ملء الشاشة (Esc للخروج)">⛶</button>
    </div>
  </div>
  <div class="wte-toolbar" role="toolbar" aria-label="أدوات التنسيق">
    <button type="button" data-cmd="h2" title="عنوان رئيسي (H2)">H2</button>
    <button type="button" data-cmd="h3" title="عنوان فرعي (H3)">H3</button>
    <span class="sep"></span>
    <button type="button" data-cmd="bold" title="عريض (Ctrl+B)"><b>B</b></button>
    <button type="button" data-cmd="italic" title="مايل (Ctrl+I)"><i>I</i></button>
    <button type="button" data-cmd="quote" title="اقتباس / ملاحظة">❝</button>
    <span class="sep"></span>
    <button type="button" data-cmd="ul" title="قايمة نقط">• قايمة</button>
    <button type="button" data-cmd="ol" title="خطوات مرقمة">1. خطوات</button>
    <button type="button" data-cmd="table" title="جدول">جدول</button>
    <button type="button" data-cmd="hr" title="خط فاصل">―</button>
    <span class="sep"></span>
    <button type="button" data-cmd="link" title="رابط (Ctrl+K)">رابط</button>
    <button type="button" data-cmd="ilink" title="رابط لصفحة في الموقع">رابط داخلي</button>
    <button type="button" data-cmd="image" title="ارفع صورة">صورة</button>
    <button type="button" data-cmd="youtube" title="فيديو يوتيوب">يوتيوب</button>
    <button type="button" data-cmd="cta" title="صندوق اتصال بالرقم والواتساب">زرار اتصال</button>
    <span class="sep"></span>
    <label class="wte-paste" title="لما تلزق كلام من Word أو Google Docs بيتحول Markdown نضيف"><input type="checkbox" data-paste-convert checked> تنظيف اللصق</label>
  </div>
  <div class="wte-banner" hidden></div>
  <div class="wte-body">
    <textarea name="<?= h($name) ?>" id="<?= h($id) ?>" rows="<?= $rows ?>" dir="auto" spellcheck="false"><?= h($value) ?></textarea>
    <div class="wte-preview prose" hidden aria-live="off"></div>
  </div>
  <div class="wte-status" aria-live="polite"><span data-stat="words"></span><span data-stat="h2"></span><span data-stat="links"></span><span data-stat="imgs"></span><span data-stat="saved"></span></div>
  <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden data-image-input>
</div>
<?php
}

/** بيانات المحرر (روابط الموقع والرقم) مرة واحدة في الصفحة */
function editor_data(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $data = ['links' => site_links(), 'phone' => setting('phone', '01000529729'), 'phone_intl' => setting('phone_intl', '+201000529729'),
        'whatsapp' => setting('whatsapp', '201000529729'), 'csrf' => csrf_token()];
    echo '<script type="application/json" id="editor-data">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
}

function editor_scripts(): array {
    return ['assets/vendor/marked.umd.js?v=18.0.14', 'assets/vendor/turndown.umd.js?v=7.2.4', 'assets/editor.js?v=' . (is_file(__DIR__ . '/../assets/editor.js') ? filemtime(__DIR__ . '/../assets/editor.js') : 1)];
}
