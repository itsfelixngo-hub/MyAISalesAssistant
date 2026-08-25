# Alogweb

Stack 3 container, deploy bằng GitHub self-hosted runner riêng cho project này.

| Container | Image | Vai trò |
|---|---|---|
| `maa_alogweb_nginx` | `nginx:1.27-alpine` | Web server, phục vụ file tĩnh, port ra ngoài |
| `maa_alogweb_wordpress` | `wordpress:6.7.2-php8.1-fpm` | PHP-FPM, chạy WordPress |
| `maa_alogweb_mysql` | `mysql:8.0` | Database |

nginx phục vụ static trực tiếp từ WordPress root (mount read-only) và chỉ đẩy
request `.php` sang php-fpm qua `fastcgi_pass alogweb-wordpress:9000`.

```
projects/alogweb/
├── docker-compose.yml          # 3 service
├── .env                        # bí mật, KHÔNG commit, KHÔNG bị workflow ghi đè
├── .env.production.example     # mẫu cho VPS
├── nginx/default.conf          # cấu hình web server
├── php/php.ini                 # upload 128M, memory 512M
├── php/www.conf                # pool php-fpm (mặc định image chỉ 5 worker)
├── theme/apk/                  # theme, xem mục Giao diện
├── database/alogweb_current.sql  # dump, gitignore — đặt tay lên VPS
├── backups/                    # dump tự động trước mỗi lần deploy
└── scripts/
    ├── deploy.sh               # validate → backup → seed → up → smoke test
    ├── seed-db.sh              # import dump (chỉ khi DB rỗng, trừ khi --force)
    ├── backup-db.sh            # mysqldump + gzip + prune
    └── wp.sh                   # WP-CLI qua container tạm (stack vẫn 3 container)
```

Thiết kế đã duyệt và các quyết định của session nằm ở
[docs/README.md](docs/README.md); mở [docs/design-mockup.html](docs/design-mockup.html)
bằng trình duyệt để xem mockup.

## Chạy local

```bash
cd projects/alogweb
cp .env.example .env
# đặt dump vào database/alogweb_current.sql
./scripts/deploy.sh
```

## Cài đặt VPS (làm 1 lần)

### 1. Runner

Cài GitHub self-hosted runner với label:

```text
self-hosted, linux, alogweb
```

Chạy runner dưới user có quyền docker (`usermod -aG docker deploy`). Workflow
không dùng SSH và không dùng `sudo`.

### 2. Thư mục

```bash
mkdir -p /home/deploy/apps/alogweb/projects/alogweb/database
```

Workflow đồng bộ đúng cấu trúc repo vào đây:

```text
/home/deploy/apps/alogweb/
├── projects/alogweb/     ← rsync từ repo
└── shared/               ← rsync từ repo
```

### 3. File `.env`

```bash
cd /home/deploy/apps/alogweb/projects/alogweb
cp .env.production.example .env
chmod 600 .env
```

Sửa `SITE_URL`, `HTTP_PORT`, mật khẩu MySQL. `.env` nằm trong danh sách exclude
của rsync nên deploy không bao giờ ghi đè.

### 4. Dump database

Dump bị gitignore nên runner không có sẵn — copy tay một lần:

```bash
scp projects/alogweb/database/alogweb_current.sql \
  deploy@VPS:/home/deploy/apps/alogweb/projects/alogweb/database/
```

`seed-db.sh` tự tìm dump trong `database/`: ưu tiên `alogweb_current.sql` rồi
`db_apk.sql`, nếu không có thì lấy file `.sql` duy nhất trong thư mục. Có nhiều
file thì nó dừng và bắt chỉ rõ bằng `ALOGWEB_DUMP=<đường-dẫn>`.

Chỉ import khi database còn rỗng. Các lần deploy sau không đụng vào dữ liệu.

> Dùng `db_apk.sql` (prefix `apk_`) — đây là dump của site thật, có đủ post meta
> `_screenshots`, `_info`, `_feature`, `_store_game` mà theme `apk` cần.
> `alogweb_current.sql` là DB dev của plugin AI, prefix `wp_`, **không** có các
> meta này nên trang single sẽ trống.
>
> `WORDPRESS_TABLE_PREFIX` phải khớp prefix trong dump.
> Nếu lệch, deploy dừng lại kèm thông báo prefix đúng thay vì hiện màn hình cài
> đặt WordPress trắng.

## Deploy

### Mô hình branch

`main` là bản làm việc, chứa toàn bộ repo. `deploy/alogweb` chỉ chứa đúng
project này — **cùng bố cục đường dẫn**, chỉ bỏ các project không liên quan:

| | main | deploy/alogweb |
|---|---|---|
| Số file | 212 | 76 |
| Giữ | tất cả | `projects/alogweb/`, `shared/`, `.github/workflows/deploy-alogweb.yml`, `.gitignore` |

Workflow chỉ chạy khi push vào `deploy/alogweb`, nên push lên `main` không deploy.

Đồng bộ từ main sang branch deploy:

```bash
.github/scripts/sync-deploy-branch.sh alogweb
git push origin deploy/alogweb
```

> Đừng `git merge main` thẳng vào branch deploy. Branch này **xoá** những đường
> dẫn mà main giữ, nên mỗi lần main sửa một file như thế là một xung đột
> modify/delete — lặp lại ở mọi lần merge.
>
> Script không merge: nó **dựng lại tree** từ main rồi lọc, ghi cả hai branch làm
> parent. Cách này không thể xung đột, và chạy qua index tạm nên **không đụng vào
> working tree** — quan trọng vì thư mục theme đang bind mount vào container đang
> chạy.

Nếu thay đổi trên main không chạm vào `projects/alogweb/` hay `shared/`, script
báo "Already in sync" và không tạo commit — đúng, vì tree của branch deploy không
đổi.

### Kích hoạt

Tự động khi push vào `deploy/alogweb` có thay đổi trong `projects/alogweb/**` hoặc
`shared/plugins/ai-post-content-writer/**`. Chạy tay ở tab Actions →
*Deploy alogweb* → *Run workflow*, có tuỳ chọn `reseed_database` để drop và
import lại dump (mất toàn bộ thay đổi hiện có, chỉ dùng lúc test).

Workflow chạy: preflight → rsync source → `scripts/deploy.sh`, và dump log
container nếu thất bại. Deploy thất bại không rollback tự động — khôi phục từ
`backups/`:

```bash
gunzip -c backups/alogweb-YYYYmmdd-HHMMSS.sql.gz | \
  docker compose --env-file .env -f docker-compose.yml exec -T alogweb-mysql \
  sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

## Giai đoạn test bằng port → trỏ DNS

Giai đoạn 1 (chưa có DNS): `HTTP_PORT=8093`, `SITE_URL=http://<IP-VPS>:8093`.
Mở port trên firewall, truy cập thẳng bằng IP.

Giai đoạn 2 (đã trỏ DNS): sửa `.env` thành `HTTP_PORT=80` và
`SITE_URL=https://apk.alogweb.com`, rồi chạy lại deploy. Không cần sửa
`nginx/default.conf` (`server_name _` nhận mọi host) và không cần sửa database:
`WP_HOME`/`WP_SITEURL` được ép trong `wp-config.php` nên luôn thắng giá trị lưu
trong dump.

Đã kiểm chứng: đổi `SITE_URL` rồi `up -d` lại là `WP_HOME`/`WP_SITEURL` đổi theo
ngay. `wp-config.php` do image sinh ra `eval()` biến `WORDPRESS_CONFIG_EXTRA` lúc
chạy, nên không cần xoá file config hay sửa database.

Cho HTTPS về sau: thêm certbot/reverse proxy phía trước và gửi header
`X-Forwarded-Proto: https` — WordPress đã được cấu hình sẵn để hiểu header này.

### Ảnh trong bài chưa có trên VPS

Nội dung trong dump trỏ ảnh về `https://apk.alogweb.com/wp-content/uploads/...`,
trong khi volume trên VPS mới chưa có file nào. Mở khối `@live_uploads` đã comment
sẵn trong `nginx/default.conf` để nginx lấy ảnh thiếu từ site đang chạy trong lúc
test, hoặc rsync thư mục `uploads` sang volume `alogweb_wordpress_data`.

## Proxy ảnh screenshot (static.*)

Theme rewrite mọi URL `*.googleusercontent.com` thành
`https://static.<host-của-site>/lh3.googleusercontent.com/<id>` (hàm
`screenshot_rewrite_lh3_in_url`). nginx nhận host đó, proxy sang Google và cache
xuống đĩa.

| File | Vai trò |
|---|---|
| `nginx/static-cache.conf` | khai báo `proxy_cache_path` (http context) |
| `nginx/static.conf` | server HTTP — mặc định, dùng lúc test bằng IP:port |
| `nginx/static-ssl.conf` | server HTTPS + redirect 80→443, chỉ bật qua overlay |
| `nginx/snippets/static-proxy.conf` | block `location` dùng chung cho cả hai |

`server_name ~^static\..+$` khớp mọi subdomain `static.*`, nên **không phải sửa
file khi trỏ DNS**. Cache 2GB, giữ 30 ngày, `proxy_cache_lock` để nhiều request
cùng một ảnh chỉ gọi Google một lần.

Kiểm tra thủ công (chưa cần DNS):

```bash
curl -I -H 'Host: static.alogweb.com' \
  'http://127.0.0.1:8093/lh3.googleusercontent.com/<image-id>'
```

Xem `X-Cache-Status`: `MISS` lần đầu, `HIT` lần sau. Thêm `?nocache=1` để bypass
và nạp lại. Đường dẫn ngoài `/lh3.googleusercontent.com/` trả 404.

`lh3` phục vụ được cả id gốc của `play-lh` (đã đối chiếu byte-for-byte), nên việc
theme quy mọi subdomain về `lh3` là hợp lệ. Trong DB: 74 bài dùng `lh3.`, 10 bài
`play-lh.`.

### Bật HTTPS (sau khi có DNS + certbot)

```bash
docker compose -f docker-compose.yml -f docker-compose.ssl.yml up -d
```

Overlay mở thêm port 443, mount `/etc/letsencrypt` read-only và thay
`static.conf` bằng `static-ssl.conf`. Sửa hai dòng `ssl_certificate*` trong
`nginx/static-ssl.conf` nếu host static không phải `static.alogweb.com`.

> **Phải khớp domain.** Theme dựng static host = `static.` + host của `SITE_URL`.
> `SITE_URL=https://apk.alogweb.com` → ảnh trỏ tới `static.apk.alogweb.com`;
> `SITE_URL=https://alogweb.com` → `static.alogweb.com`. nginx khớp cả hai nhờ
> regex, nhưng **chứng chỉ TLS thì không** — cấp cert cho đúng host mà
> `SITE_URL` sinh ra, nếu không trình duyệt sẽ báo lỗi TLS và ảnh không hiện.

## Worker của plugin AI

Plugin AI Post Content Writer **không tự chạy được**: `start_scan()` chỉ đặt job
sang `running` rồi redirect, không schedule cron event nào. Event
`aipcw_process_batch` chỉ được đặt lại từ bên trong `process_batch()`, nên không
có gì khởi động vòng đầu tiên. Bấm Start mà không có worker thì thanh tiến độ
đứng nguyên `0/85 (0%)`, không báo lỗi.

Worker nằm dưới compose profile `worker`, mặc định không chạy để stack giữ đúng
3 container. Khi cần chạy job:

```bash
docker compose --profile worker up -d alogweb-worker   # bật
docker compose --profile worker stop alogweb-worker    # tắt khi xong
docker compose logs -f alogweb-worker                  # theo dõi
```

`BATCH_SIZE = 1` và worker nghỉ 5s giữa hai vòng, nên tốc độ khoảng 1 bài mỗi
5-10 giây tuỳ thời gian AI trả lời. Worker chỉ làm việc khi job ở trạng thái
`running`; job `stopped`/`idle` thì nó nằm im.

Chạy tay một vòng mà không cần bật container:

```bash
./scripts/wp.sh aipcw worker --once
```

> `mode=update` **ghi đè nội dung bài hiện có**, không tạo bài mới. Plugin lưu
> `_aipcw_backup_history` (tối đa 10 bản) nên revert được từ trang admin. Dùng
> `mode=draft` nếu chỉ muốn sinh bài nháp và giữ nguyên bài gốc.

## Form liên hệ

Page template `theme/apk/template-pages/contact.php`, PHP thuần, không plugin.
Gán template cho một Page trong wp-admin (hiện là trang **Contact us**, `/contact`).

**Captcha xoay.** Server sinh góc ngẫu nhiên bội số 15°, lưu vào transient 15
phút, rồi dùng GD xoay ảnh trước khi gửi đi — **góc không bao giờ xuất hiện trong
HTML**. Người dùng kéo thanh trượt cho mũi tên thẳng đứng. ExchangeHub xoay bằng
CSS transform nên bot đọc được đáp án ngay trong trang; đây là chỗ khác biệt có
chủ đích.

Ngoài captcha còn bốn lớp: honeypot, thời gian tối thiểu 3 giây, rate limit 60
giây theo IP đã băm, và nonce của WordPress.

**Tên trường bắt buộc có tiền tố `cf_`.** WordPress đọc public query var từ
`$_POST` chứ không chỉ `$_GET`, nên một trường tên `name` khiến WP tưởng đang tìm
bài có slug đó và trả 404 trước khi template kịp chạy. `s`, `order`, `author`,
`title` cũng là bẫy tương tự.

### SMTP dùng chung với ExchangeHub

Trùng tên biến với app ExchangeHub, nên một bộ giá trị cấu hình được cả hai site:

```
SITE_CONTACT_EMAIL   CONTACT_FORWARD_TO      CONTACT_FROM_EMAIL
CONTACT_SMTP_HOST    CONTACT_SMTP_PORT       CONTACT_SMTP_USER
CONTACT_SMTP_PASSWORD  CONTACT_SMTP_USE_TLS  CONTACT_SMTP_TLS_VERIFY
CONTACT_RATE_LIMIT_SECONDS  CONTACT_MIN_SUBMIT_SECONDS  CONTACT_ROTATION_TOLERANCE
```

`CONTACT_SMTP_HOST` để trống thì `wp_mail()` rơi về mailer cục bộ và form **báo
lỗi gửi** thay vì giả vờ đã gửi.

> `CONTACT_SMTP_HOST` phải là hostname hợp lệ hoặc IP. PHPMailer từ chối tên có
> dấu gạch dưới và chỉ báo "Could not connect to SMTP host", khiến ta đi tìm nhầm
> phía mạng. Code ghi log rõ ràng khi gặp trường hợp này.

Trang `/contact` và ảnh captcha đã được loại khỏi page cache — nonce và token chỉ
dùng một lần.

### Test gửi mail cục bộ

```bash
docker run -d --name alogweb-mailpit --network alogweb_net \
  -p 127.0.0.1:8025:8025 axllent/mailpit
```

Đặt `CONTACT_SMTP_HOST=alogweb-mailpit`, `CONTACT_SMTP_PORT=1025`,
`CONTACT_SMTP_USE_TLS=false` trong `.env`, deploy lại container wordpress rồi xem
mail ở <http://localhost:8025>. Xong thì `docker rm -f alogweb-mailpit`.

## wp-cron

`DISABLE_WP_CRON=true` vì WordPress spawn cron bằng cách tự gọi `SITE_URL` từ
bên trong container — ở giai đoạn test bằng IP:port thì địa chỉ đó không tồn tại
trong container nên cron không bao giờ chạy. Chạy bằng crontab của host:

```cron
*/5 * * * * cd /home/deploy/apps/alogweb/projects/alogweb && ./scripts/wp.sh cron event run --due-now >/dev/null 2>&1
```

## Giao diện

Theme `apk` phiên bản 2.0. Bố cục sáng, ảnh làm chủ; ba kiểu chữ mỗi kiểu một
việc: **Archivo** cho giao diện, **Source Serif 4** cho nội dung bài, **IBM Plex
Mono** cho thông số kỹ thuật.

| File | Vai trò |
|---|---|
| `inc/alogweb-data.php` | Lớp truy xuất dữ liệu app duy nhất |
| `parts/app-card.php` | Thẻ app dùng chung cho mọi lưới |
| `header.php` `footer.php` | Thanh điều hướng, ô tìm kiếm, chip danh mục, footer |
| `index.php` | Dải nổi bật + lưới theo danh mục |
| `single.php` | Thẻ định danh dính, dải screenshot, bài viết, app liên quan |
| `archive.php` `search.php` `page.php` | Dùng lại lưới của trang chủ |
| `inc/alogweb-sort.php` | Hàng lọc sắp xếp cho danh mục và tìm kiếm |
| `inc/alogweb-index.php` | Meta phái sinh để sort được theo điểm và dung lượng |
| `inc/alogweb-contact.php` | Captcha xoay, kiểm tra và gửi mail |
| `template-pages/download-template.php` | Trang tải, không có điều hướng |
| `style.css` | 250 dòng (bản cũ 1126) |

### Tên app lấy từ đâu

Job AI đã viết lại `post_title` thành tiêu đề bài dài, nên tiêu đề bài **không
còn dùng làm tên app được**. `alogweb_app_name()` lấy tên ngắn từ
`_info->json_script` (schema.org `SoftwareApplication.name`), fallback về slug.
Tiêu đề bài dài vẫn hiện, nhưng ở vị trí headline của phần Giới thiệu.

### Mọi template đọc dữ liệu qua một cửa

`alogweb_app($post_id)` trả về mảng đã chuẩn hoá với giá trị mặc định an toàn.
Trước đây `_info`, `_feature`, `_screenshots` được đọc trực tiếp ở 11 chỗ với
cách khác nhau — đó là lý do `single.php` gọi `sizeof()` lên một chuỗi và fatal
trên PHP 8. Không thêm chỗ nào đọc meta trực tiếp nữa.

### Sắp xếp kết quả

`ratingValue` và `size` nằm trong object serialize `_info` nên MySQL không
`ORDER BY` được. `inc/alogweb-index.php` tạo hai meta phái sinh
(`_alogweb_rating`, `_alogweb_size_bytes`), cập nhật khi lưu bài và dựng lại
toàn bộ bằng:

```bash
./scripts/wp.sh alogweb reindex
```

`_info` vẫn là nguồn sự thật; hai meta kia chỉ để sort. Sắp xếp áp qua
`pre_get_posts` nên giữ đúng thứ tự khi sang trang.

### Admin bar

Ẩn ở front end. Admin bar tự ghim vào đỉnh màn hình và đẩy trang xuống bằng
`html{margin-top}`, nên người đang đăng nhập nhìn thấy bố cục khác hẳn khách
vãng lai — đúng góc nhìn tệ nhất để soát giao diện. `wp-admin` không ảnh hưởng.

Bật lại bằng `ALOGWEB_ADMIN_BAR=1`. Các quy tắc `body.admin-bar` trong
`style.css` vẫn giữ để lúc bật lại header dính đúng chỗ, không chui xuống dưới
thanh.

### Đã gỡ bỏ

`wp_is_mobile()` (render khác nhau theo user-agent nên phá cache), layout
`float`, jQuery 85K, Font Awesome 56K, `main.js`, emoji script, `wp-block-library`
và khối `global-styles` — head giảm từ 11.8KB xuống 2.4KB.

## Cache toàn trang

`nginx/fastcgi-cache.conf` + phần `fastcgi_cache` trong `nginx/default.conf`.
Đây là lý do site giữ nguyên kiến trúc theme thay vì đi headless: trang đã cache
trả về trong **0.6ms**, ngang file tĩnh.

Luôn **bỏ qua** cache: người đã đăng nhập, POST, `/wp-admin`, `/wp-login`,
`/wp-json`, `/feed`, sitemap, kết quả tìm kiếm và `/download-apk`. Xem header
`X-Page-Cache` để biết `HIT` / `MISS` / `BYPASS`.

TTL 1 giờ. nginx ghi tệp cache với quyền `nginx:nginx 0600` nên PHP **không** tự
xoá được khi lưu bài — vì vậy có script purge:

```bash
./scripts/purge-cache.sh         # xoá cache trang
./scripts/purge-cache.sh --all   # xoá cả cache ảnh screenshot
```

`deploy.sh` tự chạy purge sau mỗi lần deploy. Chạy tay sau khi job AI cập nhật
nội dung. Người đang đăng nhập luôn thấy bản mới nhất nên chỉ khách vãng lai bị
ảnh hưởng.

## Hiệu năng

`php/www.conf` nâng `pm.max_children` từ 5 (mặc định của image) lên 16. Ước lượng:
mỗi worker WordPress tốn ~80-120MB, giảm xuống nếu VPS chỉ 1-2GB RAM.

Đừng bật `fastcgi_keep_conn` / `keepalive` trong `nginx/default.conf`. Đo trên
stack này: 30 request song song, request chậm nhất **0.11s khi tắt** và **64s khi
bật** — kể cả file PHP không load WordPress. Lý do đã ghi ngay trong file conf.

Số đo hiện tại (đã verify): 100 request song song → 100 × HTTP 200, chậm nhất 0.46s.

## Phiên bản WordPress

Image ghi `wordpress:6.7.2-php8.1-fpm` nhưng core trong volume đã **tự cập nhật
lên 7.1**. `WP_AUTO_UPDATE_CORE` giờ đặt là `minor`: bản vá bảo mật vẫn tự động,
còn nhảy phiên bản lớn phải là quyết định có chủ đích bằng cách đổi image tag.

Nếu muốn deploy tái lập được chính xác, đổi image tag trong `docker-compose.yml`
cho khớp phiên bản đang chạy.

## Bật debug

Dùng biến `WORDPRESS_DEBUG` của image, **không phải** `WP_DEBUG`: image define
`WP_DEBUG` trước khi eval `WORDPRESS_CONFIG_EXTRA`. Để trống là tắt; bất kỳ giá
trị non-empty nào cũng bật, kể cả chuỗi `"false"`.

## WP-CLI

Stack không có container WP-CLI thường trú. `wp.sh` chạy WP-CLI trong container
dùng một lần rồi tự xoá:

```bash
./scripts/wp.sh core version
./scripts/wp.sh rewrite flush
./scripts/wp.sh theme activate apk
```

## Kiểm tra game đã bị gỡ khỏi Google Play

Theme lưu URL Google Play của mỗi bài trong post meta `_store_game`. Tool WP-CLI
kiểm tra các bài `post` có meta này.

Chạy thử, không thay đổi dữ liệu:

```bash
./scripts/wp.sh alogweb check-store-links
```

- URL trả `200`: bài vẫn truy cập được.
- URL trả `404`: game có thể đã bị gỡ.
- Timeout, `403`, `429` hoặc lỗi mạng: bỏ qua, không xoá bài.

Chuyển các bài trả đúng HTTP 404 vào Trash (chỉ chạy sau khi đã xem dry-run):

```bash
./scripts/wp.sh alogweb check-store-links --trash-404
./scripts/wp.sh alogweb check-store-links --limit=50
```

Bài bị chuyển Trash không còn nằm trong danh sách public và không vào sitemap,
có thể Restore lại từ WordPress Trash. Chỉ HTTP `404` mới bị chuyển Trash.

## Thêm project mới

Copy `.github/workflows/deploy-alogweb.yml`, đổi `PROJECT`, `APP_DIR`, `paths`
và label runner. Mỗi project có runner + thư mục + `.env` riêng, không dùng chung.
