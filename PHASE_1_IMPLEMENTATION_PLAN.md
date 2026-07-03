# SchemaGuard Phase 1 Implementation Plan

> الهدف من الملف ده: تحويل Phase 1 من `IMPLEMENTATION_ROADMAP.md` إلى خطة تنفيذ عملية ومقفولة، من غير كتابة كود التحليل نفسه. بعد اعتماد الخطة، نبدأ التنفيذ خطوة بخطوة.

## 1. الهدف النهائي للمرحلة

Phase 1 لازم تخرج لنا Laravel package قابل للتثبيت والاكتشاف تلقائيًا باسم:

```text
schemaguard/laravel
```

وفيه أمر Artisan واحد فقط:

```bash
php artisan schemaguard:check
```

الأمر في المرحلة دي لا يحلل migrations ولا يمسح codebase. هو فقط:

1. يثبت إن package bootstrap شغال.
2. يثبت إن Service Provider بيتسجل تلقائيًا.
3. يثبت إن config بتتدمج وتتعمل publish.
4. يطبع banner واضح.
5. يرجع exit code `0`.
6. يعدي smoke test باستخدام Orchestra Testbench.

## 2. حدود المرحلة

### داخل النطاق

- إنشاء skeleton package كامل.
- إعداد Composer metadata والـ Laravel auto-discovery.
- إنشاء config publishable باسم `schemaguard.php`.
- إنشاء Service Provider.
- إنشاء `schemaguard:check` command بدون options.
- إنشاء root exception.
- إعداد PHPUnit + Testbench.
- إنشاء smoke feature test.
- إنشاء minimal README و MIT license.
- تشغيل أوامر التحقق الخاصة بالمرحلة.

### خارج النطاق

- لا Migration Parser.
- لا AST scanning.
- لا Policy Engine.
- لا Dependency Graph.
- لا Console Reporter الحقيقي.
- لا JSON output.
- لا `--diff`, `--path`, `--migrations`, `--strict`, أو أي CLI options.
- لا تنفيذ أو boot لكود host application.

## 3. قواعد تنفيذ غير قابلة للكسر

1. كل PHP file يبدأ بـ:

```php
<?php

declare(strict_types=1);
```

2. كل production class تكون `final` إلا لو فيه سبب معماري واضح للعكس.
3. namespace root هو:

```text
SchemaGuard\
```

4. PSR-4 mapping:

```text
SchemaGuard\ => src/
SchemaGuard\Tests\ => tests/
```

5. `CheckCommand` في Phase 1 لا يحتوي أي analysis logic.
6. `SchemaGuardServiceProvider` bootstrap فقط: config merge, publish, command registration.
7. `config/schemaguard.php` لازم يحتوي template كامل أو قريب جدًا من Blueprint section 6، لأن المراحل التالية هتعتمد على keys دي.
8. ممنوع نضيف stubs بتدعي سلوك غير موجود. أي capability خارج Phase 1 تفضل غير موجودة.

## 4. الملفات والمجلدات المطلوب إنشاؤها

```text
C:\ProgramingWorld\SchemaGuard\
├── composer.json
├── README.md
├── LICENSE.md
├── phpunit.xml.dist
├── .gitattributes
├── config/
│   └── schemaguard.php
├── src/
│   ├── SchemaGuardServiceProvider.php
│   ├── Console/
│   │   └── Commands/
│   │       └── CheckCommand.php
│   └── Exceptions/
│       └── SchemaGuardException.php
└── tests/
    ├── TestCase.php
    ├── Unit/
    │   └── .gitkeep
    └── Feature/
        └── CheckCommandTest.php
```

ملاحظة: `tests/Unit/.gitkeep` مش جزء منطقي من المنتج، لكنه مفيد عشان `phpunit.xml.dist` يشاور على suite موجودة من أول يوم.

## 5. خطة التنفيذ التفصيلية

### Step 1: Composer package metadata

إنشاء `composer.json` بالقيم الأساسية:

- `name`: `schemaguard/laravel`
- `description`: جملة package الرسمية.
- `license`: `MIT`
- `type`: `library`
- `require`:
  - `php`: `^8.2`
  - `illuminate/console`: `^11.0 || ^12.0`
  - `illuminate/support`: `^11.0 || ^12.0`
  - `illuminate/filesystem`: `^11.0 || ^12.0`
  - `nikic/php-parser`: `^5.0`
- `require-dev`:
  - `orchestra/testbench`: `^9.0 || ^10.0`
  - `phpunit/phpunit`: `^11.0`
- `autoload.psr-4`:
  - `SchemaGuard\\`: `src/`
- `autoload-dev.psr-4`:
  - `SchemaGuard\\Tests\\`: `tests/`
- `extra.laravel.providers`:
  - `SchemaGuard\\SchemaGuardServiceProvider`
- `scripts.test`:
  - `phpunit`
- `minimum-stability`: `stable`

Acceptance:

- `composer validate` ينجح.
- `composer dump-autoload` ينجح بعد إنشاء الملفات.

### Step 2: Config template

إنشاء:

```text
config/schemaguard.php
```

المطلوب وجود keys الأساسية من Blueprint:

- `scan_paths`
- `migration_paths`
- `ignore_paths`
- `policy.modes`
- `policy.escalate_exposed_to_block`
- `policy.block_confidence_floor`
- `enforce.tables`
- `enforce.columns`
- `ignore.tables`
- `ignore.columns`
- `custom_rules`
- `builder_column_methods`
- `common_column_names`
- `exit_codes.warning_exit_code`
- `exit_codes.treat_warnings_as_failure`
- `cache.enabled`
- `cache.path`

Acceptance:

- الملف يرجع array سليمة.
- لا يعتمد على classes من package.
- يستخدم Laravel helpers المقبولة مثل `storage_path()` لأن config هيتم تحميلها داخل Laravel/Testbench context.

### Step 3: Service Provider

إنشاء:

```text
src/SchemaGuardServiceProvider.php
```

المسؤوليات:

- `register()`:
  - يعمل `mergeConfigFrom(__DIR__ . '/../config/schemaguard.php', 'schemaguard')`.
- `boot()`:
  - يتحقق من `runningInConsole()`.
  - يعمل publish للـ config تحت tag:

```text
schemaguard-config
```

  - يسجل command:

```php
CheckCommand::class
```

Acceptance:

- مفيش analysis logic.
- مفيش bindings للمراحل المتقدمة لسه إلا لو احتجناها فعلًا في Phase 1، والأفضل عدم إضافتها الآن.
- package auto-discovery يقدر يوصل للـ provider من Composer metadata.

### Step 4: CheckCommand skeleton

إنشاء:

```text
src/Console/Commands/CheckCommand.php
```

المطلوب:

- extends `Illuminate\Console\Command`
- signature:

```text
schemaguard:check
```

- لا options في Phase 1.
- description واضحة.
- `handle(): int` يطبع:

```text
SchemaGuard - Deployment Firewall for Database Changes
No analysis wired yet.
```

- يرجع:

```php
self::SUCCESS
```

Acceptance:

- output يحتوي `Deployment Firewall`.
- exit code = `0`.
- لا parser/scanner/policy code.

### Step 5: Base exception

إنشاء:

```text
src/Exceptions/SchemaGuardException.php
```

المطلوب:

- base exception لكل package exceptions القادمة.
- ممكن تكون:

```php
class SchemaGuardException extends RuntimeException
```

أو `abstract class` لو هنستخدمها كجذر فقط.

Acceptance:

- namespace صحيح.
- strict types.
- بدون logic إضافي.

### Step 6: Testbench TestCase

إنشاء:

```text
tests/TestCase.php
```

المطلوب:

- extends `Orchestra\Testbench\TestCase`.
- override:

```php
protected function getPackageProviders($app): array
```

- يرجع:

```php
[
    SchemaGuardServiceProvider::class,
]
```

Acceptance:

- Testbench يقدر يboot package داخل app مؤقت.

### Step 7: Smoke feature test

إنشاء:

```text
tests/Feature/CheckCommandTest.php
```

المطلوب test واحد:

```php
public function test_command_is_registered_and_runs_successfully(): void
```

يتحقق من:

- الأمر مسجل.
- output يحتوي `Deployment Firewall`.
- exit code = `0`.

Acceptance:

```bash
vendor/bin/phpunit tests/Feature/CheckCommandTest.php
```

ينجح.

### Step 8: PHPUnit config

إنشاء:

```text
phpunit.xml.dist
```

المطلوب:

- bootstrap:

```text
vendor/autoload.php
```

- testsuites:
  - `Unit` => `tests/Unit`
  - `Feature` => `tests/Feature`
- colors enabled.

Acceptance:

- `vendor/bin/phpunit` يشتغل.
- وجود Unit suite حتى لو فاضي لا يكسر الاختبارات.

### Step 9: .gitattributes

إنشاء:

```text
.gitattributes
```

المطلوب export-ignore للملفات غير المطلوبة في Composer dist:

```text
/tests export-ignore
/phpunit.xml.dist export-ignore
/.gitattributes export-ignore
```

ممكن نضيف لاحقًا ملفات dev أخرى لما تظهر.

Acceptance:

- الملف بسيط ومحدد.

### Step 10: README minimal

إنشاء:

```text
README.md
```

المطلوب في Phase 1:

- اسم المنتج.
- جملة positioning:

```text
A deployment firewall for database schema changes.
```

- install:

```bash
composer require schemaguard/laravel
```

- usage:

```bash
php artisan schemaguard:check
```

- ملاحظة واضحة إن Phase 1 command skeleton فقط وأن التحليل يأتي في المراحل القادمة أثناء التطوير الداخلي.

Acceptance:

- README صادق، لا يبيع capabilities غير مبنية.

### Step 11: MIT license

إنشاء:

```text
LICENSE.md
```

المطلوب:

- MIT License text.
- copyright holder:

```text
SchemaGuard
```

أو اسم المالك لو قررناه قبل التنفيذ.

Acceptance:

- license واضحة ومتوافقة مع open-source wedge.

## 6. ترتيب التنفيذ المقترح

1. إنشاء المجلدات الأساسية.
2. إنشاء `composer.json`.
3. إنشاء `config/schemaguard.php`.
4. إنشاء Service Provider.
5. إنشاء CheckCommand.
6. إنشاء base exception.
7. إنشاء tests/TestCase.
8. إنشاء CheckCommandTest.
9. إنشاء phpunit.xml.dist.
10. إنشاء .gitattributes.
11. إنشاء README و LICENSE.
12. تشغيل Composer install/validate.
13. تشغيل PHPUnit.
14. تشغيل Testbench command يدويًا.
15. تشغيل vendor publish يدويًا.

## 7. أوامر التحقق

الأوامر الأساسية:

```bash
composer validate
composer install
vendor/bin/phpunit tests/Feature/CheckCommandTest.php
vendor/bin/phpunit
vendor/bin/testbench schemaguard:check
vendor/bin/testbench vendor:publish --tag=schemaguard-config
```

على PowerShell، للتحقق من exit code يدويًا:

```powershell
vendor/bin/testbench schemaguard:check
$LASTEXITCODE
```

المتوقع:

```text
0
```

## 8. Definition of Done

Phase 1 تعتبر مكتملة فقط لما كل الآتي يبقى صحيح:

- `composer install` ينجح.
- `composer validate` ينجح.
- `vendor/bin/phpunit tests/Feature/CheckCommandTest.php` ينجح.
- `vendor/bin/phpunit` ينجح.
- `vendor/bin/testbench schemaguard:check` يطبع banner ويرجع exit code `0`.
- `vendor/bin/testbench vendor:publish --tag=schemaguard-config` ينشر config بنجاح.
- `config('schemaguard.scan_paths')` يقدر يتحمل داخل Testbench context.
- مفيش analysis logic اتضاف بالغلط.
- مفيش CLI options اتضافت في Phase 1.
- كل PHP files strict typed و namespaces صحيحة.

## 9. مخاطر صغيرة أثناء التنفيذ

### Composer dependency mismatch

ممكن يحصل conflict بين Laravel 11/12 و Testbench/PHPUnit حسب PHP الموجود على الجهاز.

التعامل:

- نبدأ بالقيود الرسمية من Blueprint.
- لو Composer رفض، نراجع نسخة PHP وسبب conflict من غير تغيير فلسفة package.

### Empty Unit suite

بعض إعدادات PHPUnit ممكن تتضايق من suite path غير موجود.

التعامل:

- ننشئ `tests/Unit/.gitkeep`.

### Testbench publish path

أمر publish داخل Testbench ممكن يختلف سلوكه قليلًا حسب نسخة Testbench.

التعامل:

- smoke test الأهم هو command registration.
- publish test يتم يدويًا كجزء من DoD، ولو احتاج تعديل path نثبته بدون تغيير interface.

### README over-promising

الخطر إن README يقول إن الأداة بتمنع breaking migrations قبل ما نبني التحليل.

التعامل:

- README في Phase 1 يكون صادق ومحدود.
- README الكامل يأتي في Phase 6.

## 10. القرار التنفيذي المقترح

ننفذ Phase 1 كما هي بالضبط: package skeleton + command skeleton + config + tests.

لا نبدأ AST parser في نفس المرحلة حتى لو هنختاره مبكرًا في Phase 2، لأن قيمة Phase 1 هي تثبيت الأساس والتأكد إن Laravel package lifecycle شغال قبل إدخال أي تعقيد.

بعد نجاح Phase 1، نبدأ Phase 2 بخيار أفضل من roadmap token MVP: نبني `MigrationParser` مباشرة على `nikic/php-parser` بما إن الـ Blueprint هو المصدر الحاكم وده هيقلل إعادة العمل.
