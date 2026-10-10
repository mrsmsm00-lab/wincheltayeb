# ونش إنقاذ الطيب: wincheltayeb.com

موقع ونش إنقاذ في محافظة السويس. المشروع جزئين في ريبو واحد:

| المجلد | إيه هو | بيترفع فين |
|---|---|---|
| `wincheltayeb/web/` | الموقع (Next.js 16) | سيرفر Coolify على `wincheltayeb.com` |
| `wincheltayeb/admin/` | لوحة التحكم (PHP خام + SQLite) | cPanel على `admin.wincheltayeb.com` |

## إزاي الجزئين بيكلموا بعض

```
لوحة التحكم (cPanel)  ──api.php (JSON)──►  الموقع (Coolify)
        │                                        ▲
        └──── بعد كل حفظ: POST /api/revalidate ──┘
```

- الموقع بيقرا المحتوى من `admin.wincheltayeb.com/api.php` ويعمله كاش 10 دقايق.
- بعد أي حفظ في اللوحة، اللوحة بتبعت للموقع يحدّث نفسه فوراً، ومعاه الـ sitemap والـ llms.txt.
- لو اللوحة وقعت، الموقع بيكمل شغال بالمحتوى المدمج في `web/content/seed.json`.

---

## 1) الرفع على GitHub

الريبو: https://github.com/mrsmsm00-lab/wincheltayeb

المشروع كله جوه فولدر `wincheltayeb/` عشان يتفصل عن أي مشروع تاني. الملفات اللي لازم تفضل في جذر الريبو: `.cpanel.yml` و`.gitignore` و`.github/`.

```bash
cd wincheltayeb-repo        # الفولدر اللي فكيت فيه الضغط
git init
git add .
git commit -m "ونش إنقاذ الطيب: الإصدار الأول"
git branch -M main
git remote add origin https://github.com/mrsmsm00-lab/wincheltayeb.git
git push -u origin main
```

الملفات السرية (`admin/config.local.php` وقاعدة البيانات والمرفوعات) متضافة في `.gitignore` ومش هتترفع.

## 2) سر مشترك

اعمل نص عشوائي طويل، واستخدمه في المكانين (Coolify ولوحة التحكم):

```bash
openssl rand -hex 32
```

## 3) الموقع على Coolify

1. **New Resource → Public/Private Repository**، واختار الريبو.
2. **Build Pack:** `Dockerfile`، و**Base Directory:** `/wincheltayeb/web`.
3. **Port:** `3000`.
4. **Domains:** `https://wincheltayeb.com,https://www.wincheltayeb.com`.
5. **Environment Variables:**
   ```
   NEXT_PUBLIC_SITE_URL=https://wincheltayeb.com
   CMS_API_URL=https://admin.wincheltayeb.com/api.php
   REVALIDATE_SECRET=السر-اللي-عملته
   ```
   علّم على `NEXT_PUBLIC_SITE_URL` و`CMS_API_URL` إنهم **Build Variable** كمان.
6. فعّل **Auto Deploy**، فأي push على `main` هيتعمله deploy لوحده.

## 4) لوحة التحكم على cPanel

**المتطلبات:** PHP 8.1 أو أحدث، مع الإضافات `pdo_sqlite` و`mbstring` و`fileinfo` و`curl`. فعّلها من **Select PHP Version**.

1. اعمل الصب دومين `admin.wincheltayeb.com` وفعّل عليه SSL (AutoSSL).
2. **Git Version Control → Create**، وحط رابط الريبو.
3. افتح `.cpanel.yml` (في جذر الريبو) وغيّر `CPANELUSER` ومسار الصب دومين، واعمل push.
4. من Git Version Control دوس **Deploy HEAD Commit**.
5. من **File Manager** في مجلد الصب دومين، اعمل ملف `config.local.php`:
   ```php
   <?php
   return [
       'revalidate_secret' => 'السر-اللي-عملته',
   ];
   ```
6. افتح `https://admin.wincheltayeb.com/install.php`. اكتب السر كـ«كود التثبيت»، واعمل حساب المدير، والمحتوى كله هيتستورد.
7. امسح `install.php` ومجلد `install/` من السيرفر.
8. اتأكد إن `https://admin.wincheltayeb.com/api.php` بيرجع JSON، وإن `https://admin.wincheltayeb.com/data/site.sqlite` بيدي **403**.

> لو مش عايز تستخدم Git في cPanel، ارفع محتوى مجلد `wincheltayeb/admin/` بالـ File Manager أو FTP في مجلد الصب دومين، وكمّل من خطوة 5.

## 5) بعد الإطلاق

- **Google Search Console:** ضيف الدومين وابعت `sitemap.xml` و`video-sitemap.xml`.
- **Google Business Profile:** اعمل بروفايل للنشاط في السويس بنفس الاسم والرقم. ده أهم حاجة للظهور في الخريطة.
- **Bing Webmaster Tools:** استورد من Search Console.
- **اختبار الاسكيما:** https://search.google.com/test/rich-results
- **الصور والفيديوهات:** ارفع صور حقيقية من شغلكم بعناوين بتوصف المكان. ده بيفرق جداً في السيو المحلي.
- **الإحداثيات:** نقط المناطق تقريبية. راجعها من «المناطق»، واسحب العلامة للمكان الصح.

---

## ملفات السيو اللي بتتولد تلقائي

| الرابط | الوظيفة |
|---|---|
| `/sitemap.xml` | كل الصفحات بتاريخ آخر تعديل |
| `/video-sitemap.xml` | فيديوهات المعرض |
| `/robots.txt` | بيشاور على السايت ماب |
| `/llms.txt` | ملخص الموقع لمحركات الذكاء الاصطناعي ([llmstxt.org](https://llmstxt.org)) |
| `/llms-full.txt` | المحتوى الكامل نص |
| `/feed.xml` | RSS للمقالات |
| `/manifest.webmanifest` | بيانات التطبيق للموبايل |

**الاسكيما (JSON-LD):** `AutomotiveBusiness` و`EmergencyService` و`WebSite` في كل صفحة، و`Service` و`FAQPage` و`BreadcrumbList` في الخدمات والمناطق، و`BlogPosting` في المقالات، و`ImageObject` و`VideoObject` في المعرض.

> **ملاحظة:** جوجل بقى يظهر أسئلة FAQ كنتايج مميزة لمواقع حكومية وصحية بس تقريباً. لكن الاسكيما لسه بتساعد جوجل والذكاء الاصطناعي يفهموا الصفحة.

## التشغيل محلياً

```bash
# الموقع
cd wincheltayeb/web
npm install
npm run dev                       # http://localhost:3000

# اللوحة (محتاج PHP)
cd wincheltayeb/admin
cp config.local.example.php config.local.php   # وحط admin_url و revalidate_url محلي
php -S localhost:8090
```

لو عدّلت ملفات المحتوى في `wincheltayeb/web/content/*.mjs`، شغّل `npm run seed` عشان يتحدث `seed.json` في الموقع وفي `wincheltayeb/admin/install/`.

## مفتوح المصدر

- Next.js وReact (MIT)
- Leaflet (BSD-2)
- خرايط OpenStreetMap (ODbL)
- marked (MIT)
- خطوط Alexandria وIBM Plex Sans Arabic (SIL OFL)
- SQLite (Public Domain)

## مساعد الكتابة بالذكاء الاصطناعي (OpenAI)

في محرر أي صفحة في اللوحة فيه صندوق «مساعد الكتابة (AI)» بأربع أزرار: اكتب المقال كامل، حسّن وطوّل المحتوى، اعمل عنوان ووصف جوجل، اعمل أسئلة شائعة.

- **التشغيل:** اعمل مفتاح من platform.openai.com > API keys، وحطه من **الإعدادات > مساعد الكتابة**، أو في `config.local.php` بالمفتاح `openai_api_key`.
- **الأمان:** المفتاح بيفضل على السيرفر بس، ومش بيظهر في الـ API العام ولا في المتصفح.
- **الحد اليومي:** افتراضياً 40 طلب في اليوم، ويتغير من الإعدادات.
- **الموديل:** الافتراضي `gpt-4.1-mini`، ويتغير من الإعدادات لو OpenAI وقّفه أو عايز موديل تاني.
- **الحفظ:** النتيجة بتتحط في الخانات بس، ومش بتتحفظ غير لما تدوس «احفظ». وفيه زرار تراجع.
- **لو الكتابة بتفشل بعد حوالي دقيقة:** يبقى الاستضافة بتقطع الطلبات الطويلة. اطلب من الاستضافة يزوّدوا `max_execution_time`، أو استخدم «حسّن» على مسودة أقصر.

## الاستيراد الجماعي ودفعات المحتوى

- **ملفات الدفعات:** موجودة في `wincheltayeb/content-batches/`.
- **الاستيراد:** من اللوحة افتح **استيراد**، وارفع ملف الدفعة. الصفحات بتتضاف **كمسودة** عشان تراجعها، وبعدها تنشرها من القوايم بالتدريج.
- **التكرار:** إعادة استيراد نفس الملف مش بتكرر الصفحات.

## زرار «حدّث خريطة الموقع وبلّغ محركات البحث»

موجود في الصفحة الرئيسية للوحة، وبيعمل 3 حاجات:
1. يحدّث الموقع وخريطته.
2. يقرا كل الروابط منها.
3. يبعتها لـ IndexNow (Bing وYandex وغيرهم).

**مفتاح IndexNow:** بيتولد تلقائي من `REVALIDATE_SECRET`، وبيظهر على `/indexnow.txt`، فمفيش إعدادات زيادة.

**جوجل:** مش بيدعم التبليغ الفوري، فابعت `sitemap.xml` مرة واحدة في Search Console، واستخدم «Request indexing» للصفحات المهمة.
