<?php
// التحكم في القايمة الرئيسية: إضافة، حذف، تعديل، ترتيب، إخفاء
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/site_config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'reset') {
        set_setting('nav_menu', '');
        $r = revalidate_site();
        flash('القايمة رجعت للشكل الافتراضي.' . ($r['ok'] ? '' : ' ' . $r['msg']), $r['ok'] ? 'ok' : 'warn');
        redirect('menu.php');
    }
    $items = [];
    $bad = 0;
    foreach ((array) ($_POST['order'] ?? []) as $rid) {
        $in = $_POST['m'][$rid] ?? null;
        if (!is_array($in)) continue;
        if (trim((string) ($in['label'] ?? '')) === '' && trim((string) ($in['href'] ?? '')) === '') continue;
        $item = clean_menu_item($in);
        if ($item) $items[] = $item; else $bad++;
    }
    if (!$items) { flash('القايمة مينفعش تبقى فاضية.', 'err'); redirect('menu.php'); }
    set_setting('nav_menu', json_encode($items, JSON_UNESCAPED_UNICODE));
    $r = revalidate_site();
    flash('اتحفظت القايمة.' . ($bad ? " اتشال $bad عنصر عشان الاسم أو الرابط ناقص أو غلط." : '') . ($r['ok'] ? ' والموقع اتحدث.' : ' ' . $r['msg']), $bad || !$r['ok'] ? 'warn' : 'ok');
    redirect('menu.php');
}

function menu_row(array $m, string $rid): void { ?>
  <li class="menu-row<?= empty($m['enabled']) ? ' is-off' : '' ?>" data-item>
    <input type="hidden" name="order[]" value="<?= h($rid) ?>">
    <span class="drag" title="اسحب عشان تغيّر الترتيب" aria-hidden="true">⠿</span>
    <label>الاسم <input name="m[<?= h($rid) ?>][label]" value="<?= h($m['label'] ?? '') ?>" maxlength="60"></label>
    <label>الرابط <input name="m[<?= h($rid) ?>][href]" value="<?= h($m['href'] ?? '') ?>" list="site-links-list" dir="ltr" placeholder="/services"></label>
    <label>الشكل <select name="m[<?= h($rid) ?>][style]"><option value="normal">عادي</option><option value="button"<?= ($m['style'] ?? '') === 'button' ? ' selected' : '' ?>>زرار مميز</option></select></label>
    <label class="switch"><input type="checkbox" name="m[<?= h($rid) ?>][enabled]" value="1" data-enabled<?= !empty($m['enabled']) ? ' checked' : '' ?>><span>ظاهر</span></label>
    <label class="check"><input type="checkbox" name="m[<?= h($rid) ?>][new_tab]" value="1"<?= !empty($m['new_tab']) ? ' checked' : '' ?>> تبويب جديد</label>
    <span class="sec-actions">
      <button type="button" data-move="up" aria-label="لفوق">▲</button>
      <button type="button" data-move="down" aria-label="لتحت">▼</button>
      <button type="button" data-remove class="link-danger">مسح</button>
    </span>
  </li>
<?php }

layout_start('القايمة الرئيسية', 'menu');
?>
<div class="head-row">
  <h1>القايمة الرئيسية</h1>
  <a class="btn btn-ghost" href="<?= h(cfg('site_url')) ?>/" target="_blank" rel="noopener">شوفها على الموقع</a>
</div>
<p class="muted">دي الروابط اللي فوق في الموقع. الشعار بيودّي للرئيسية لوحده، وفي الموبايل «الرئيسية» بتتضاف أول القايمة تلقائي. اكتب في خانة الرابط عشان تختار من صفحات الموقع، أو حط رابط خارجي يبدأ بـ https، أو tel: أو #قسم.</p>

<datalist id="site-links-list">
  <?php foreach (site_links() as [$t, $u]): ?><option value="<?= h($u) ?>"><?= h($t) ?></option><?php endforeach; ?>
</datalist>

<form method="post" data-dirty-guard>
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <ul class="sec-list menu-list" data-sortable id="menu-items">
    <?php foreach (nav_menu() as $i => $m) menu_row($m, 'r' . $i); ?>
  </ul>
  <template id="tpl-menu"><?php menu_row(['label' => '', 'href' => '', 'enabled' => true, 'new_tab' => false, 'style' => 'normal'], '__ID__'); ?></template>
  <div class="sticky-save">
    <button type="button" class="btn btn-ghost" data-add="tpl-menu" data-add-to="menu-items">+ أضف عنصر</button>
    <button class="btn">احفظ القايمة</button>
  </div>
</form>
<form method="post" class="reset-form" data-confirm="هترجع القايمة للشكل الافتراضي. متأكد؟">
  <?= csrf_field() ?><input type="hidden" name="action" value="reset">
  <button class="link-danger">رجّع القايمة للشكل الافتراضي</button>
</form>
<?php layout_end();
