# Tài liệu session — 24/08/2026

Hồ sơ những gì đã dựng và những quyết định đã chốt trong session này. Hướng dẫn
vận hành nằm ở [../README.md](../README.md); file này chỉ ghi lại bối cảnh và lý
do, tức phần không đọc được từ code.

## Hiện vật

| File | Nội dung |
|---|---|
| [design-mockup.html](design-mockup.html) | Mockup giao diện đã duyệt. Mở trực tiếp bằng trình duyệt, không cần server — 25 ảnh thật nhúng sẵn dạng data URI. |

Bản trên web: <https://claude.ai/code/artifact/6ced8eea-9cfd-40be-85f1-be82dabcbeba>

Mockup là **ảnh chụp tại thời điểm chốt thiết kế**, không phải nguồn sự thật.
Theme thật ở `../theme/apk/` và đã đi trước mockup ở vài chỗ (toàn bộ chuỗi giao
diện đã chuyển sang tiếng Anh cho khớp locale `en_US`, mockup vẫn còn tiếng Việt).

## Quyết định kiến trúc

**3 container: nginx + wordpress-fpm + mysql.** Worker WP-CLI tách ra profile
`worker`, mặc định không chạy.

**Giữ theme, không đi headless.** Lý do đi headless thường là tốc độ, nhưng bật
`fastcgi_cache` cho kết quả tương đương mà không thêm hạ tầng: trang đã cache trả
về trong 0.6ms so với 18ms khi chưa cache. Tự do thiết kế cũng không cần headless
— mockup là HTML/CSS thuần, chuyển sang template PHP gần như copy nguyên.

**Dùng `db_apk.sql`, không dùng `alogweb_current.sql`.** Bản `alogweb_current`
là database dev của plugin AI (prefix `wp_`), không có `_screenshots`, `_info`,
`_feature`, `_store_game` mà theme cần — 0 dòng, không phải thiếu vài bài.
`db_apk.sql` (prefix `apk_`) mới là dữ liệu site thật, 85 bài đủ meta.

**Dump đặt tay lên VPS, không commit vào git.** Import chỉ chạy khi DB rỗng.

## Những chỗ dễ vấp, đã ghi lại để không lặp

**Đừng bật `fastcgi_keep_conn` / `keepalive` cho upstream php-fpm.** Đo trên
stack này: 30 request song song, request chậm nhất **0.11s khi tắt** và **64s
khi bật** — kể cả file PHP không load WordPress. Lý do đã ghi trong
`nginx/default.conf`.

**`fastcgi_cache_use_stale` nhận ít giá trị hơn `proxy_cache_use_stale`** —
`http_502` và `http_504` không hợp lệ, nginx sẽ crash-loop.

**Tên project compose phải là `maa-alogweb`.** Tên trống `alogweb` trùng với một
project khác ở `/home/felix/alogweb`, và `--remove-orphans` đã xoá nhầm container
của project đó một lần. `--remove-orphans` cũng đã bỏ khỏi `deploy.sh`.

**phpMyAdmin ở :8084 trỏ vào MySQL *dev*, không phải alogweb.** Đây là chỗ dễ
sửa nhầm database. DB alogweb ở `127.0.0.1:3317`.

**Bind mount lồng trong named volume chỉ hiện ở container khai báo nó.** nginx
mount volume WordPress read-only nên không thấy theme/plugin nếu không mount lại
cùng bind — biểu hiện là mọi asset của theme trả 404 trong khi php-fpm vẫn thấy file.

**`wp_dequeue_style('global-styles')` không có tác dụng** — khối đó do action
`wp_enqueue_global_styles` in ra, phải `remove_action`.

**`WORDPRESS_DEBUG`, không phải `WP_DEBUG`.** Image define `WP_DEBUG` trước khi
eval `WORDPRESS_CONFIG_EXTRA`. Mọi giá trị non-empty đều bật debug, kể cả chuỗi
`"false"`.

**Core tự cập nhật 6.7.2 → 7.1 trong volume**, image tag không còn phản ánh thực
tế. `WP_AUTO_UPDATE_CORE` đã đặt `minor`.

## Lỗi đã sửa trong theme

`single.php` gọi `sizeof()` lên kết quả `get_post_meta(..., true)`, trả chuỗi
rỗng khi meta không tồn tại — PHP 8 ném TypeError thay vì warning. Mọi trang
single đều fatal.

`functions.php` bật `display_errors` cho user đã đăng nhập, khiến notice in
thẳng vào body và phá mọi `wp_redirect()` trong wp-admin.

Theme setup gọi `__()` lúc file được include, tức trước `init` — WordPress 6.7+
báo `_load_textdomain_just_in_time`. Đã gói vào `after_setup_theme`.

Gốc rễ chung: `_info`, `_feature`, `_screenshots` được đọc trực tiếp ở 11 chỗ
theo 11 kiểu khác nhau. Giờ mọi template đi qua `alogweb_app()` trong
`inc/alogweb-data.php`.

## Về dữ liệu

Job AI viết lại `post_title` thành tiêu đề bài dài, nên **tiêu đề bài không còn
dùng làm tên app được**. Tên ngắn thật lấy từ `_info->json_script`
(schema.org `SoftwareApplication.name`) qua `alogweb_app_name()`.

Bài 295 là nháp trống từ site cũ (2023), backup của nó rỗng là đúng — bấm Revert
sẽ xoá trắng nội dung. Không phải lỗi.

Plugin AI **không tự chạy được**: `start_scan()` chỉ đặt job sang `running` mà
không schedule cron event nào. Chỉ container worker mới đẩy được queue.
