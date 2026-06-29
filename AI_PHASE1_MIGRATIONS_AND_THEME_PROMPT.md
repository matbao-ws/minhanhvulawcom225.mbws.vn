# AI_PHASE1_MIGRATIONS_AND_THEME_PROMPT.md

# Prompt cho AI Coding Agent - Phase 1 Migrations + Admin Theme Blade Layout

Dùng file này sau khi đã có:

```text
AI_SYSTEM_SPEC.md
AI_BUILD_PROMPT.md
```

Mục tiêu file này:
- Bắt AI tạo migrations đầy đủ cho Foundation Phase.
- Bắt AI convert theme HTML Bootstrap có sẵn thành Blade layout chuẩn.
- Tránh cắt header/sidebar/main content bị vỡ layout.
- Giữ admin dùng chung cho toàn bộ project.

---

## Vai trò của AI

Bạn là senior Laravel engineer.

Bạn đang code trực tiếp trong repo Laravel hiện tại.

Bạn phải:
1. Đọc `AI_SYSTEM_SPEC.md`.
2. Đọc `AI_BUILD_PROMPT.md`.
3. Đọc file này.
4. Inspect repo hiện tại.
5. Chỉ implement Foundation migrations + admin theme layout.

Không làm Product/Order/Voucher trong phase này.

---

# PART A - FOUNDATION MIGRATIONS

## Yêu cầu chung

Tạo migrations cho các bảng foundation:

```text
packages
features
package_features
project_subscription
feature_settings
project_settings
roles
```

Kiểm tra trước khi tạo:
- Nếu bảng đã có migration thì không tạo trùng.
- Nếu `users` table Laravel default đã có thì không tạo lại.
- Nếu cần thêm role_id/avatar_url/is_active/last_login_at vào users thì tạo migration alter users.
- Nếu users table chưa có thì dùng Laravel default users migration hoặc tạo users chuẩn Laravel.

Không đổi `password` thành `password_hash` trong Laravel. Laravel dùng field `password`.

Không thêm `shop_id`.

---

## 1. packages migration

Create:

```php
Schema::create('packages', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->string('name');
    $table->decimal('price', 15, 2)->default(0);
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

---

## 2. features migration

Create:

```php
Schema::create('features', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('value_type')->default('boolean');
    $table->timestamps();
});
```

Allowed value_type examples:

```text
boolean
number
text
json
```

---

## 3. package_features migration

Create:

```php
Schema::create('package_features', function (Blueprint $table) {
    $table->id();
    $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
    $table->foreignId('feature_id')->constrained('features')->cascadeOnDelete();
    $table->boolean('is_enabled')->default(false);
    $table->string('limit_value')->nullable();
    $table->json('config')->nullable();
    $table->timestamps();

    $table->unique(['package_id', 'feature_id']);
});
```

---

## 4. project_subscription migration

Use singular table name because spec v1 uses one project subscription.

Create:

```php
Schema::create('project_subscription', function (Blueprint $table) {
    $table->id();
    $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
    $table->string('status')->default('active');
    $table->date('started_at')->nullable();
    $table->date('expired_at')->nullable();
    $table->timestamps();
});
```

Status values:

```text
active
expired
suspended
cancelled
```

---

## 5. feature_settings migration

Create:

```php
Schema::create('feature_settings', function (Blueprint $table) {
    $table->id();
    $table->string('feature_code')->unique();
    $table->boolean('is_enabled')->default(false);
    $table->string('limit_value')->nullable();
    $table->json('config')->nullable();
    $table->timestamp('updated_at')->nullable();
});
```

No `created_at` required, but it is acceptable to use timestamps if project convention prefers.

If using timestamps, ensure code can handle it.

---

## 6. project_settings migration

Create:

```php
Schema::create('project_settings', function (Blueprint $table) {
    $table->id();
    $table->string('setting_key')->unique();
    $table->json('setting_value')->nullable();
    $table->timestamp('updated_at')->nullable();
});
```

No `created_at` required, but timestamps are acceptable if consistent.

Initial keys to seed later:

```text
shop_name
logo_url
favicon_url
contact
theme
seo
social_links
```

---

## 7. roles migration

Create:

```php
Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('permissions')->nullable();
    $table->timestamps();
});
```

---

## 8. alter users migration

If users table exists, create migration:

```php
Schema::table('users', function (Blueprint $table) {
    if (! Schema::hasColumn('users', 'role_id')) {
        $table->foreignId('role_id')->nullable()->after('id')->constrained('roles')->nullOnDelete();
    }

    if (! Schema::hasColumn('users', 'avatar_url')) {
        $table->string('avatar_url')->nullable()->after('email');
    }

    if (! Schema::hasColumn('users', 'is_active')) {
        $table->boolean('is_active')->default(true)->after('password');
    }

    if (! Schema::hasColumn('users', 'last_login_at')) {
        $table->timestamp('last_login_at')->nullable()->after('is_active');
    }
});
```

In `down()` method, drop foreign key safely before dropping role_id.

Do not break Laravel default auth.

---

## 9. Models

Create/update models:

```text
app/Models/Package.php
app/Models/Feature.php
app/Models/PackageFeature.php
app/Models/ProjectSubscription.php
app/Models/FeatureSetting.php
app/Models/ProjectSetting.php
app/Models/Role.php
app/Models/User.php
```

Model requirements:

### Package

```php
protected $fillable = [
    'code',
    'name',
    'price',
    'description',
    'is_active',
];

protected $casts = [
    'price' => 'decimal:2',
    'is_active' => 'boolean',
];

public function features()
{
    return $this->belongsToMany(Feature::class, 'package_features')
        ->withPivot(['is_enabled', 'limit_value', 'config'])
        ->withTimestamps();
}
```

### Feature

```php
protected $fillable = [
    'code',
    'name',
    'description',
    'value_type',
];

public function packages()
{
    return $this->belongsToMany(Package::class, 'package_features')
        ->withPivot(['is_enabled', 'limit_value', 'config'])
        ->withTimestamps();
}
```

### PackageFeature

```php
protected $fillable = [
    'package_id',
    'feature_id',
    'is_enabled',
    'limit_value',
    'config',
];

protected $casts = [
    'is_enabled' => 'boolean',
    'config' => 'array',
];
```

### ProjectSubscription

Because table name is singular:

```php
protected $table = 'project_subscription';

protected $fillable = [
    'package_id',
    'status',
    'started_at',
    'expired_at',
];

protected $casts = [
    'started_at' => 'date',
    'expired_at' => 'date',
];

public function package()
{
    return $this->belongsTo(Package::class);
}
```

### FeatureSetting

```php
protected $fillable = [
    'feature_code',
    'is_enabled',
    'limit_value',
    'config',
];

protected $casts = [
    'is_enabled' => 'boolean',
    'config' => 'array',
];

public $timestamps = false;
```

If migration uses timestamps, set timestamps accordingly.

### ProjectSetting

```php
protected $fillable = [
    'setting_key',
    'setting_value',
];

protected $casts = [
    'setting_value' => 'array',
];

public $timestamps = false;
```

If migration uses timestamps, set timestamps accordingly.

### Role

```php
protected $fillable = [
    'name',
    'permissions',
];

protected $casts = [
    'permissions' => 'array',
];

public function users()
{
    return $this->hasMany(User::class);
}
```

---

## 10. Seeders

Create seeders:

```text
database/seeders/FeatureSeeder.php
database/seeders/PackageSeeder.php
database/seeders/FoundationSeeder.php
```

`FeatureSeeder` must seed official feature codes:

```php
$features = [
    ['code' => 'catalog', 'name' => 'Catalog', 'value_type' => 'boolean'],
    ['code' => 'cart', 'name' => 'Cart', 'value_type' => 'boolean'],
    ['code' => 'cod_order', 'name' => 'COD Order', 'value_type' => 'boolean'],
    ['code' => 'online_payment', 'name' => 'Online Payment', 'value_type' => 'boolean'],
    ['code' => 'voucher', 'name' => 'Voucher', 'value_type' => 'boolean'],
    ['code' => 'review', 'name' => 'Review', 'value_type' => 'boolean'],
    ['code' => 'zalo_oa', 'name' => 'Zalo OA', 'value_type' => 'boolean'],
    ['code' => 'cms_page', 'name' => 'CMS Page', 'value_type' => 'boolean'],
    ['code' => 'banner', 'name' => 'Banner', 'value_type' => 'boolean'],
    ['code' => 'menu', 'name' => 'Menu', 'value_type' => 'boolean'],
    ['code' => 'multi_admin', 'name' => 'Multi Admin', 'value_type' => 'boolean'],
    ['code' => 'inventory_log', 'name' => 'Inventory Log', 'value_type' => 'boolean'],
    ['code' => 'max_products', 'name' => 'Max Products', 'value_type' => 'number'],
    ['code' => 'max_admin_users', 'name' => 'Max Admin Users', 'value_type' => 'number'],
];
```

Use `updateOrCreate`.

`PackageSeeder` must create:

```text
basic_2m
standard_4m
```

And create package_features for all features.

`FoundationSeeder` should:
- seed features
- seed packages
- seed project_subscription default basic_2m
- copy basic_2m feature values into feature_settings
- seed default role Admin
- seed project_settings defaults

Default project settings:

```php
[
    'shop_name' => 'Laravel Ecommerce Core',
    'logo_url' => null,
    'favicon_url' => null,
    'contact' => [
        'phone' => null,
        'email' => null,
        'address' => null,
    ],
    'theme' => [
        'primary_color' => '#0d6efd',
        'layout' => 'default',
    ],
    'seo' => [
        'title' => 'Laravel Ecommerce Core',
        'description' => null,
    ],
    'social_links' => [],
]
```

Update `DatabaseSeeder` to call `FoundationSeeder`.

---

# PART B - ADMIN THEME CONVERSION

## Input Theme

The project may have a Bootstrap HTML admin theme from user.

The theme may be in one of these locations:

```text
/theme
/admin-theme
/resources/theme
/public/theme
/public/admin
```

If theme files are not present, do not invent theme-specific classes. Create a clean Bootstrap 5 fallback layout.

If theme files are present, inspect:
- main HTML file
- CSS/JS asset paths
- sidebar markup
- header/navbar markup
- main content wrapper
- footer markup

---

## Goal

Convert static Bootstrap HTML theme into Blade layout:

```text
resources/views/admin/layouts/app.blade.php
resources/views/admin/layouts/header.blade.php
resources/views/admin/layouts/sidebar.blade.php
resources/views/admin/layouts/footer.blade.php
resources/views/admin/dashboard/index.blade.php
```

Asset files should be placed under:

```text
public/admin-assets
```

Do not leave assets pointing to broken relative paths like:

```text
../../assets/css/style.css
assets/js/main.js
```

Use Laravel `asset()` helper.

Correct:

```blade
<link rel="stylesheet" href="{{ asset('admin-assets/css/style.css') }}">
<script src="{{ asset('admin-assets/js/main.js') }}"></script>
```

---

## Theme Cutting Rules

When cutting the theme, never randomly split HTML.

Identify layout wrappers first.

Most admin themes look like one of these:

### Pattern 1

```html
<body>
  <div class="wrapper">
    <aside class="sidebar">...</aside>
    <div class="main">
      <nav class="navbar">...</nav>
      <main class="content">
        PAGE CONTENT HERE
      </main>
      <footer>...</footer>
    </div>
  </div>
</body>
```

Cut as:

```text
app.blade.php
- html/head/body opening
- wrapper opening
- include sidebar
- main opening
- include header
- main content yield
- include footer
- close main/wrapper/body/html
```

### Pattern 2

```html
<body>
  <div id="app">
    <div class="main-wrapper">
      <div class="navbar-bg"></div>
      <nav class="navbar">...</nav>
      <div class="main-sidebar">...</div>
      <div class="main-content">
        <section class="section">
          PAGE CONTENT HERE
        </section>
      </div>
      <footer class="main-footer">...</footer>
    </div>
  </div>
</body>
```

Cut carefully:
- Do not move `.main-content` outside `.main-wrapper`.
- Do not move `.navbar-bg`.
- Keep sidebar/header/footer in the same wrapper order.

### Pattern 3

```html
<body>
  <div class="page">
    <div class="page-main">
      <div class="app-header">...</div>
      <div class="app-sidebar">...</div>
      <div class="app-content">
        PAGE CONTENT HERE
      </div>
    </div>
  </div>
</body>
```

Cut carefully:
- Keep `.page` and `.page-main`.
- Yield only inside `.app-content`.

---

## Golden Rule

Only replace the original page-specific content block with:

```blade
@yield('content')
```

Do not change wrapper hierarchy.

Do not remove required divs.

Do not close divs in different partials unless the opening div is in the parent layout.

Good:

```blade
<body>
<div class="wrapper">
    @include('admin.layouts.sidebar')

    <div class="main">
        @include('admin.layouts.header')

        <main class="content">
            @yield('content')
        </main>

        @include('admin.layouts.footer')
    </div>
</div>
</body>
```

Bad:

```blade
<body>
@include('admin.layouts.header')
@include('admin.layouts.sidebar')
@yield('content')
@include('admin.layouts.footer')
</body>
```

The bad version often breaks admin themes because wrapper hierarchy is lost.

---

## How To Find Main Content

Search the static HTML for likely markers:

```text
main-content
content-wrapper
page-content
app-content
main-panel
content
container-fluid
section
```

The correct content area usually contains:
- dashboard cards
- tables
- forms
- breadcrumbs
- page title
- sample widgets

Replace only the inside page content, not the wrapper.

Example:

Original:

```html
<div class="main-content">
  <section class="section">
    <div class="section-header">
      <h1>Dashboard</h1>
    </div>

    <div class="section-body">
      ...
    </div>
  </section>
</div>
```

Blade:

```blade
<div class="main-content">
    @yield('content')
</div>
```

Dashboard page:

```blade
@extends('admin.layouts.app')

@section('content')
<section class="section">
    <div class="section-header">
        <h1>Dashboard</h1>
    </div>

    <div class="section-body">
        ...
    </div>
</section>
@endsection
```

---

## Header Partial Rules

`header.blade.php` should contain only navbar/header markup.

It can include:
- top navbar
- user dropdown
- notification dropdown
- collapse button
- search box

It should not include:
- opening `<html>`
- opening `<body>`
- sidebar markup
- main content wrapper unless theme requires it in parent layout

---

## Sidebar Partial Rules

`sidebar.blade.php` should contain only sidebar markup.

It must include feature-based menu visibility.

Example:

```blade
<li>
    <a href="{{ route('admin.dashboard') }}">Dashboard</a>
</li>

@if(app(\App\Support\FeatureGate::class)->enabled('catalog'))
<li>
    <a href="#">Catalog</a>
    <ul>
        <li><a href="{{ route('admin.categories.index') }}">Categories</a></li>
        <li><a href="{{ route('admin.products.index') }}">Products</a></li>
    </ul>
</li>
@endif

@if(app(\App\Support\FeatureGate::class)->enabled('voucher'))
<li>
    <a href="{{ route('admin.vouchers.index') }}">Vouchers</a>
</li>
@endif

@if(app(\App\Support\FeatureGate::class)->enabled('online_payment'))
<li>
    <a href="{{ route('admin.payment-settings.index') }}">Payment Settings</a>
</li>
@endif

@if(app(\App\Support\FeatureGate::class)->enabled('zalo_oa'))
<li>
    <a href="{{ route('admin.zalo-oa.index') }}">Zalo OA</a>
</li>
@endif
```

If routes are not created yet for later modules, use `#` temporarily or comment with TODO.

Do not create routes for modules outside Phase 1.

---

## Footer Partial Rules

`footer.blade.php` contains footer markup and may include closing footer-specific divs only if they were opened in footer.

Do not close wrapper divs opened in `app.blade.php` unless the structure is clearly controlled by `app.blade.php`.

---

## Asset Rules

Copy theme assets into:

```text
public/admin-assets
```

Update all CSS/JS/images/fonts references.

Use:

```blade
{{ asset('admin-assets/...') }}
```

For images inside CSS, keep relative paths working by preserving folder structure.

Example:

```text
public/admin-assets/css
public/admin-assets/js
public/admin-assets/images
public/admin-assets/fonts
public/admin-assets/vendors
```

Do not move CSS without moving related images/fonts if CSS references them.

---

## Dashboard Page

Create:

```text
resources/views/admin/dashboard/index.blade.php
```

Must extend layout:

```blade
@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    ...
@endsection
```

Keep content simple in Phase 1.

Example:

```blade
<div class="container-fluid">
    <h1 class="mb-4">Dashboard</h1>

    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted">Package</div>
                    <h5>{{ optional($subscription->package)->name ?? 'N/A' }}</h5>
                </div>
            </div>
        </div>
    </div>
</div>
```

If theme has its own card classes, use the theme's classes.

---

## Avoid Broken Layout Checklist

Before final response, verify:

```text
- app.blade.php has exactly one <html>, <head>, <body>.
- @yield('content') is inside the original main content wrapper.
- Sidebar remains inside original sidebar wrapper.
- Header/navbar remains inside original header wrapper.
- Footer remains in original footer position.
- All required wrapper divs are preserved.
- CSS/JS paths use asset().
- No broken relative paths remain.
- Disabled feature menu items are hidden.
- Dashboard renders inside layout.
```

---

## Fallback Clean Bootstrap Layout

If no uploaded/provided theme exists, create fallback layout:

```blade
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body>
<div class="d-flex min-vh-100">
    @include('admin.layouts.sidebar')

    <div class="flex-grow-1 bg-light">
        @include('admin.layouts.header')

        <main class="p-4">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @yield('content')
        </main>

        @include('admin.layouts.footer')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
```

Fallback sidebar:

```blade
<aside class="bg-dark text-white p-3" style="width: 260px;">
    <h5 class="mb-4">Admin</h5>

    <nav class="nav flex-column gap-1">
        <a class="nav-link text-white" href="{{ route('admin.dashboard') }}">Dashboard</a>

        @if(app(\App\Support\FeatureGate::class)->enabled('catalog'))
            <a class="nav-link text-white" href="#">Categories</a>
            <a class="nav-link text-white" href="#">Products</a>
        @endif

        <a class="nav-link text-white" href="#">Orders</a>
        <a class="nav-link text-white" href="#">Customers</a>

        @if(app(\App\Support\FeatureGate::class)->enabled('banner'))
            <a class="nav-link text-white" href="#">Banners</a>
        @endif

        @if(app(\App\Support\FeatureGate::class)->enabled('menu'))
            <a class="nav-link text-white" href="#">Menus</a>
        @endif

        @if(app(\App\Support\FeatureGate::class)->enabled('cms_page'))
            <a class="nav-link text-white" href="#">Pages</a>
        @endif

        @if(app(\App\Support\FeatureGate::class)->enabled('voucher'))
            <a class="nav-link text-white" href="#">Vouchers</a>
        @endif

        @if(app(\App\Support\FeatureGate::class)->enabled('online_payment'))
            <a class="nav-link text-white" href="#">Payment Settings</a>
        @endif

        @if(app(\App\Support\FeatureGate::class)->enabled('review'))
            <a class="nav-link text-white" href="#">Reviews</a>
        @endif

        @if(app(\App\Support\FeatureGate::class)->enabled('zalo_oa'))
            <a class="nav-link text-white" href="#">Zalo OA</a>
        @endif

        <a class="nav-link text-white" href="#">Settings</a>
    </nav>
</aside>
```

Fallback header:

```blade
<header class="bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
    <div>
        <strong>@yield('title', 'Dashboard')</strong>
    </div>

    <div class="text-muted">
        {{ auth()->user()->name ?? 'Admin' }}
    </div>
</header>
```

Fallback footer:

```blade
<footer class="px-4 py-3 text-muted small">
    Laravel Ecommerce Core
</footer>
```

---

# Final Output Required

After implementation, respond:

```md
## Summary
...

## Migrations created
...

## Models created/updated
...

## Theme files created/updated
...

## Commands to run
...

## Notes
...

## Next recommended phase
...
```

Do not continue to Product/Catalog phase unless explicitly requested.
