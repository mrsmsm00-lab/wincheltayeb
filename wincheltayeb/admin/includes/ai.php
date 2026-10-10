<?php
// مساعد الكتابة بالذكاء الاصطناعي (OpenAI). المفتاح بيفضل على السيرفر ومش بيوصل للمتصفح أبداً.

const AI_DEFAULT_MODEL = 'gpt-4.1-mini';

function ai_key(): string { return trim((string) (cfg('openai_api_key') ?: setting('openai_api_key'))); }
function ai_key_source(): string { return cfg('openai_api_key') ? 'config' : (setting('openai_api_key') ? 'settings' : ''); }
function ai_model(): string { return trim((string) (cfg('openai_model') ?: setting('openai_model') ?: AI_DEFAULT_MODEL)); }
function ai_daily_limit(): int { $v = (int) setting('ai_daily_limit', '40'); return $v > 0 ? $v : 40; }
function ai_usage_today(): int { return (int) setting('_ai_usage_' . date('Y-m-d'), '0'); }
function ai_bump_usage(): void { set_setting('_ai_usage_' . date('Y-m-d'), (string) (ai_usage_today() + 1)); }
function ai_mask(string $key): string { return $key === '' ? '' : substr($key, 0, 3) . '…' . substr($key, -4); }

/** بيكلم OpenAI وبيرجع JSON متفكوك */
function ai_chat(string $system, string $user): array {
    $key = ai_key();
    if ($key === '') throw new RuntimeException('مفتاح OpenAI مش متظبط. حطه من صفحة الإعدادات.');
    $base = rtrim((string) (cfg('openai_base_url') ?: 'https://api.openai.com/v1'), '/');
    $payload = [
        'model' => ai_model(),
        'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $user]],
        'response_format' => ['type' => 'json_object'],
    ];
    $ch = curl_init($base . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 170, CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('مقدرناش نوصل لـ OpenAI: ' . $err);
    $j = json_decode($raw, true);
    if ($code !== 200) {
        $msg = $j['error']['message'] ?? 'خطأ غير معروف';
        $hint = match (true) {
            $code === 401 => ' (المفتاح غلط أو اتلغى)',
            $code === 429 => ' (الرصيد خلص أو الطلبات كتير، راجع حسابك في OpenAI)',
            $code === 404 => ' (اسم الموديل مش متاح لحسابك، غيّره من الإعدادات)',
            default => '',
        };
        throw new RuntimeException("OpenAI رفض الطلب ($code)$hint: $msg");
    }
    $content = $j['choices'][0]['message']['content'] ?? '';
    $content = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', trim((string) $content));
    $data = json_decode($content, true);
    if (!is_array($data)) throw new RuntimeException('الرد رجع بشكل مش متوقع. جرب تاني.');
    return $data;
}

/** بيانات الموقع الحقيقية اللي الـ AI مسموح يستخدمها */
function ai_site_context(): string {
    $lines = [];
    $lines[] = 'اسم النشاط: ' . setting('site_name', 'ونش إنقاذ الطيب');
    $lines[] = 'رقم التليفون والواتساب: ' . setting('phone', '01000529729');
    $lines[] = 'المواعيد: ' . setting('hours', '24 ساعة طول أيام الأسبوع');
    $lines[] = "\nالخدمات (ودي الروابط الداخلية الوحيدة المسموح تستخدمها للخدمات):";
    foreach (db()->query("SELECT title, slug FROM posts WHERE type='service' AND published=1 ORDER BY sort") as $s) {
        $lines[] = "- {$s['title']}: /services/{$s['slug']}";
    }
    $lines[] = "\nمناطق التغطية في محافظة السويس (الروابط الداخلية الوحيدة المسموح بيها للمناطق):";
    foreach (db()->query("SELECT place_name, title, slug, eta FROM posts WHERE type='area' AND published=1 ORDER BY sort") as $a) {
        $name = $a['place_name'] ?: $a['title'];
        $lines[] = "- {$name}: /areas/{$a['slug']}" . ($a['eta'] ? " (وقت الوصول: {$a['eta']})" : '');
    }
    return implode("\n", $lines);
}

function ai_system_prompt(): string {
    $ctx = ai_site_context();
    return <<<TXT
إنت كاتب محتوى لموقع "ونش إنقاذ الطيب"، ونش إنقاذ سيارات شغال في محافظة السويس في مصر.

قواعد لازم تلتزم بيها:
1. اكتب باللهجة المصرية العامية الطبيعية (زي: عربيتك، إزاي، دلوقتي، بنوصلك). ممنوع الفصحى الرسمية وممنوع أي لهجة تانية.
2. المحتوى عن محافظة السويس بس (بما فيها العين السخنة والأجزاء التابعة للمحافظة من الطرق السريعة). متتكلمش عن محافظات تانية كأننا بنخدمها.
3. ممنوع تخترع أسعار أو أرقام أو خصومات. لو الكلام محتاج سعر، قول إن السعر بيتحدد حسب المسافة ونوع العربية وبيتقال قبل ما الونش يتحرك.
4. ممنوع تخترع أسماء شوارع أو محلات أو معالم. استخدم بس أسماء المناطق اللي في القايمة تحت، أو اللي المستخدم ذكرها بنفسه.
5. الروابط الداخلية تكون بصيغة Markdown ومن القايمة تحت بس، زي: [ونش ملاكي](/services/private-car-towing).
6. متكتبش عنوان H1 (# ) جوه المحتوى. استخدم ## و ### بس.
7. الكلام يكون مفيد وعملي لصاحب عربية عطلانة، مش حشو ولا تكرار لكلمات مفتاحية.
8. رقم التواصل الوحيد هو الرقم اللي تحت.
9. رد دايماً بـ JSON صحيح بس، من غير أي كلام قبله أو بعده.

بيانات الموقع:
$ctx
TXT;
}

function ai_type_note(string $type, string $place): string {
    return match ($type) {
        'area' => "الصفحة دي صفحة منطقة: \"$place\" في محافظة السويس. ركّز على خدمة الونش في المنطقة دي بالذات وطبيعة الطرق والعطلات فيها، من غير ما تخترع معالم.",
        'service' => 'الصفحة دي صفحة خدمة. اشرح الخدمة، وإمتى العميل يحتاجها، وإزاي بننفذها بأمان، ومناطق السويس اللي بنقدمها فيها.',
        'page' => 'الصفحة دي صفحة تعريفية عامة عن النشاط.',
        default => 'الصفحة دي مقال نصايح لأصحاب العربيات في السويس.',
    };
}

function ai_clean_faqs($faqs): array {
    $out = [];
    foreach ((array) $faqs as $f) {
        $q = trim((string) ($f['q'] ?? $f['question'] ?? ''));
        $a = trim((string) ($f['a'] ?? $f['answer'] ?? ''));
        if ($q !== '' && $a !== '') $out[] = ['q' => $q, 'a' => $a];
    }
    return $out;
}

/** بينفذ الأكشن وبيرجع الحقول اللي تتملى في المحرر */
function ai_run(string $action, array $in): array {
    $type = $in['type'] ?? 'blog';
    $title = mb_substr(trim($in['title'] ?? ''), 0, 200);
    $idea = mb_substr(trim($in['idea'] ?? ''), 0, 3000);
    $body = mb_substr(trim($in['body'] ?? ''), 0, 14000);
    $place = mb_substr(trim($in['place_name'] ?? ''), 0, 120);
    $note = ai_type_note($type, $place);
    $sys = ai_system_prompt();

    switch ($action) {
        case 'write':
            if ($title === '' && $idea === '') throw new RuntimeException('اكتب عنوان أو فكرة الأول.');
            $user = "$note\n\nالعنوان المقترح: " . ($title ?: '(اقترح إنت عنوان)') . "\nالفكرة والملاحظات: " . ($idea ?: '(مفيش)') . "\n\n"
                . "اكتب صفحة كاملة. المطلوب JSON بالمفاتيح دي:\n"
                . "{\"title\": عنوان واضح فيه اسم الخدمة أو المنطقة (أقل من 70 حرف),\n"
                . " \"excerpt\": مقدمة من جملة أو اتنين (أقل من 200 حرف),\n"
                . " \"body\": المحتوى بصيغة Markdown، من 900 لـ 1300 كلمة، فيه من 4 لـ 7 عناوين ##، وقوايم لما تفيد، ومن 2 لـ 4 روابط داخلية من القايمة، وآخره فقرة تطلب من القارئ يتصل أو يبعت لوكيشن واتساب على الرقم,\n"
                . " \"meta_title\": عنوان جوجل أقل من 60 حرف,\n"
                . " \"meta_description\": وصف جوجل من 140 لـ 160 حرف فيه الرقم,\n"
                . " \"faqs\": [5 أسئلة بصيغة {\"q\":..., \"a\":...} والإجابة من جملة لـ 3]}";
            $d = ai_chat($sys, $user);
            return [
                'title' => trim((string) ($d['title'] ?? $title)),
                'excerpt' => trim((string) ($d['excerpt'] ?? '')),
                'body' => trim((string) ($d['body'] ?? '')),
                'meta_title' => trim((string) ($d['meta_title'] ?? '')),
                'meta_description' => trim((string) ($d['meta_description'] ?? '')),
                'faqs' => faqs_to_text(ai_clean_faqs($d['faqs'] ?? [])),
            ];

        case 'improve':
            if (mb_strlen($body) < 100) throw new RuntimeException('المحتوى قصير جداً. اكتب مسودة الأول أو استخدم «اكتب المقال».');
            $user = "$note\n\nالعنوان: $title\nملاحظات إضافية: " . ($idea ?: '(مفيش)') . "\n\nالمحتوى الحالي:\n<<<\n$body\n>>>\n\n"
                . "حسّن المحتوى ده وطوّله: خليه أوضح وأكثر فايدة، ونظّمه بعناوين ## وقوايم، وزوّد تفاصيل عملية حقيقية، وخلي طوله على الأقل مرة ونص الطول الحالي. "
                . "حافظ على كل المعلومات الصحيحة والروابط الموجودة، وزوّد رابط أو اتنين داخليين من القايمة لو مش موجودين. "
                . "المطلوب JSON: {\"body\": المحتوى الجديد بصيغة Markdown}";
            $d = ai_chat($sys, $user);
            $new = trim((string) ($d['body'] ?? ''));
            if ($new === '') throw new RuntimeException('الرد رجع فاضي. جرب تاني.');
            return ['body' => $new];

        case 'seo':
            if ($title === '' && $body === '') throw new RuntimeException('اكتب العنوان أو المحتوى الأول.');
            $user = "$note\n\nالعنوان: $title\nالمحتوى:\n<<<\n" . mb_substr($body, 0, 6000) . "\n>>>\n\n"
                . "اكتب بيانات السيو للصفحة دي. المطلوب JSON:\n"
                . "{\"meta_title\": عنوان جوجل جذاب أقل من 60 حرف فيه الكلمة الأساسية واسم السويس أو المنطقة,\n"
                . " \"meta_description\": وصف من 140 لـ 160 حرف بيشجع على الضغط وفيه الرقم,\n"
                . " \"excerpt\": مقدمة قصيرة أقل من 200 حرف}";
            $d = ai_chat($sys, $user);
            return [
                'meta_title' => trim((string) ($d['meta_title'] ?? '')),
                'meta_description' => trim((string) ($d['meta_description'] ?? '')),
                'excerpt' => trim((string) ($d['excerpt'] ?? '')),
            ];

        case 'faqs':
            if ($title === '' && $body === '') throw new RuntimeException('اكتب العنوان أو المحتوى الأول.');
            $user = "$note\n\nالعنوان: $title\nالمحتوى:\n<<<\n" . mb_substr($body, 0, 8000) . "\n>>>\n\n"
                . "اكتب 5 أسئلة شائعة حقيقية الناس في مصر بتسألها عن الموضوع ده (بالعامية زي ما بيتكتبوا في جوجل)، وإجاباتها متسقة مع المحتوى ومن غير أسعار. "
                . "المطلوب JSON: {\"faqs\": [{\"q\": السؤال, \"a\": الإجابة من جملة لـ 3}]}";
            $d = ai_chat($sys, $user);
            $f = ai_clean_faqs($d['faqs'] ?? []);
            if (!$f) throw new RuntimeException('مطلعش أسئلة. جرب تاني.');
            return ['faqs' => faqs_to_text($f)];
    }
    throw new RuntimeException('أمر مش معروف.');
}
