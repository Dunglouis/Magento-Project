# CLAUDE.md — TechNest: web bán thiết bị điện tử trên Magento 2.4.9

> Đặt file này ở **thư mục gốc repo** để Claude Code tự đọc. Đây là nguồn sự thật của dự án (bản chỉnh sửa 2026-10-16). Làm theo từng phase, và hỏi lại chủ dự án khi gặp mục ở phần 13.

---

## 1. Tổng quan

- **Tên thương hiệu:** **TechNest** (tagline: *Technology, simplified* / *Công nghệ, gọn gàng*).
- **Cửa hàng:** laptop, điện thoại, PC, phụ kiện đi kèm.
- **Mục đích:** (1) demo cho sếp về quá trình học Magento 2; (2) giữ làm nền tảng dùng về sau.
- **Nguồn lực:** làm một mình, ~12 buổi x 2h (≈ 24h), không làm T7/CN. Agent chủ động làm, chạy lệnh, kiểm thử; chủ dự án chỉ duyệt và cung cấp thông tin ở phần 13.
- **Thể hiện được:** cấu hình/quản trị Magento, custom module (backend), tùy biến theme (mức vừa phải).
- **Ưu tiên (cao → thấp):** giao diện > custom module > dữ liệu sản phẩm đầy đủ > performance test > tài liệu.
- **Giai đoạn này:** tập trung DEV. Tài liệu BA làm ở phase cuối.

## 2. Ràng buộc

- Không dùng extension trả phí. Package miễn phí/open-source chỉ dùng khi kiểm tra được giấy phép **và** tương thích Magento 2.4.9 + PHP 8.5 (kiểm tra `composer.json` của package, thử `composer require --dry-run`). Nếu không chắc, tự viết module nhỏ.
- Tận dụng tối đa tính năng có sẵn của Magento; không viết lại thứ core đã có.
- Theme: chỉ **theme con kế thừa Luma**, tùy biến nhẹ (không Hyvä).
- Một kho mặc định (không MSI nhiều kho). Customer Group để mặc định.
- Không sửa core, không sửa `vendor/`. Code theo chuẩn Magento: DI, plugin, observer, declarative schema, data patch, UI Component; không dùng `ObjectManager` trực tiếp.
- **Không commit secret** (OAuth secret, SMTP password, SSH key, token). Dùng `.env` (đã `.gitignore`), cấu hình admin (mã hóa) và GitHub Secrets.

## 3. Môi trường (Docker, đã cài)

| Component | Version |
|---|---|
| Magento Open Source | 2.4.9 (có sample data) |
| PHP | 8.5 |
| MariaDB | 11.8 |
| OpenSearch | 3 |
| Valkey (thay Redis) | 9.1 |
| RabbitMQ | 4.2 |
| nginx | 1.28 |

Repo GitHub đã khởi tạo và liên kết, có CI/CD base. Trước khi làm, đọc `docker-compose*.yml`, README gốc và cấu trúc repo để biết tên container, lệnh chạy (`docker compose exec ...`), cổng dev. **Không giả định**, hãy kiểm tra.

## 4. Thương hiệu và logo

- File trong thư mục `brand/`: `technest-logo.svg` (logo ngang, biểu tượng + chữ), `technest-mark.svg` (biểu tượng vuông), `technest-mark-{32,180,512}.png`, `favicon.ico`.
- Bảng màu: chữ/nền tối `#1d1d1f`, nền trang `#ffffff`, nền phụ `#f5f5f7`, màu nhấn duy nhất `#0071e3`. Font: stack hệ thống (`-apple-system, "SF Pro Display", "Helvetica Neue", Helvetica, Arial, sans-serif`), không tải font ngoài.
- Áp dụng: logo header (SVG, qua layout XML của theme), favicon (PNG/ICO), tên cửa hàng trong **Stores > Configuration > General > Store Information**, tiêu đề trang, footer, email giao dịch (header email dùng logo PNG), meta title mặc định "TechNest".

## 5. Quyết định thiết kế đã chốt

### 5.1 Catalog

- ~20 sản phẩm/nhóm x 4 nhóm (Laptop, Điện thoại, PC, Phụ kiện) ≈ 80 sản phẩm.
- Cây danh mục:
  - Laptop → Gaming / Văn phòng / Mỏng nhẹ
  - Điện thoại → Flagship / Tầm trung / Giá rẻ
  - PC → PC Gaming / PC Văn phòng / Linh kiện
  - Phụ kiện → Chuột / Bàn phím / Tai nghe / Sạc & cáp
- Loại sản phẩm do agent chọn, đã chốt:

| Loại | Dùng cho |
|---|---|
| Simple | Phụ kiện đơn lẻ, PC cấu hình cố định |
| Configurable | Điện thoại (màu x dung lượng), Laptop (RAM x SSD) |
| Bundle | Combo ưu đãi (PC + màn hình + bàn phím) |
| Grouped | Phụ kiện mua kèm (1-2 sản phẩm demo) |
| Virtual | Gói bảo hành mở rộng (1-2 sản phẩm demo, tùy chọn) |

- **Attribute set riêng** từng nhóm, bật **layered navigation**:
  - Chung: Thương hiệu (`brand`, dropdown, filterable), giá
  - Laptop: CPU, RAM, Ổ cứng, Kích thước màn hình, GPU
  - Điện thoại: Chipset, Dung lượng, Camera, Pin, Màu sắc
  - PC: CPU, Mainboard, VGA, RAM, PSU
  - Phụ kiện: Loại kết nối, Tương thích
- Hiển thị bảng thông số ở tab "More Information" trang chi tiết.

### 5.2 Dữ liệu

Phương án lai, chạy lặp lại được (idempotent):

1. **DummyJSON API** (miễn phí, không key): `laptops`, `smartphones`, `mobile-accessories`, `tablets`... lấy tên, mô tả, giá, brand, ảnh. **Tải ảnh về local** (`pub/media/import/`), không hotlink. Chấp nhận chất lượng ảnh ở mức demo.
2. **Seed JSON trong repo** (`app/code/TechNest/DataSeeder/data/`) cho nhóm **PC** (API không có), toàn bộ **thông số kỹ thuật** và **bản dịch tiếng Việt** (tên + mô tả).
3. Import qua CLI `bin/magento technest:seed:run` (module `TechNest_DataSeeder`): tạo attribute, attribute set, category, sản phẩm, ảnh, tồn kho, giá, bundle/grouped.
4. Ẩn/xóa sản phẩm quần áo của sample data (disable category Luma, không xóa DB trừ khi chủ dự án đồng ý).

### 5.3 Giao diện

- Phong cách **tối giản kiểu Apple, sang trọng**: nền trắng/xám nhạt, chữ đen, một màu nhấn, nhiều khoảng trắng, bo góc nhẹ, ít hiệu ứng, không gradient/màu mè.
- Theme con `TechNest/minimal` (parent `Magento/luma`) tại `app/design/frontend/TechNest/minimal/`: ghi đè LESS variables (màu, font, spacing), header/footer, trang chủ (CMS block + widget), card sản phẩm, trang chi tiết. Tận dụng tối đa framework có sẵn.
- Responsive (kiểm tra 375px, 768px, 1280px).
- Trang: Trang chủ (banner, sản phẩm nổi bật, danh mục, khối sale), Danh mục (lưới + bộ lọc), Chi tiết sản phẩm (gallery, bảng thông số, sản phẩm liên quan), Giỏ hàng, Thanh toán, Tài khoản.
- Tính năng UI: **So sánh**, **Wishlist**, **Review**, **Quick view**, **Tìm kiếm gợi ý** (autocomplete của Magento + OpenSearch).
- Quick view: Magento không có sẵn → module nhỏ `TechNest_QuickView` (modal + AJAX), ưu tiên thấp nhất.

### 5.4 Đa ngôn ngữ và tiền tệ

- 2 store view cùng 1 website/store: `en` (USD) và `vi` (VND).
- Base currency USD; allowed currencies USD, VND; display currency mặc định theo store view; tỷ giá nhập tay (ví dụ 1 USD = 25.000 VND); VND không hiển thị số thập phân.
- Ngôn ngữ `vi_VN`: tìm gói miễn phí tương thích; nếu không có, dịch các chuỗi hiển thị chính (catalog, checkout, tài khoản, email) bằng `i18n/vi_VN.csv` của theme/module. Chuỗi mới trong code phải có bản `vi_VN`.

### 5.5 Tài khoản và mua hàng

- **Khách vãng lai không được thêm vào giỏ/mua:** tắt guest checkout; khi chưa đăng nhập bấm "Add to Cart"/"Checkout" thì chuyển sang trang đăng nhập/đăng ký (module `TechNest_CustomerAccess`).
- **Đăng ký bằng email** + trường bổ sung **Số điện thoại** (thuộc tính khách hàng `phone_number`, bắt buộc, validate số di động Việt Nam `^(0|\+84)(3|5|7|8|9)\d{8}$`, hiển thị/sửa ở trang tài khoản). **Không** OTP SMS, SĐT không dùng để đăng nhập.
- **Đăng nhập Google (OAuth 2.0)** do **tự viết module** `TechNest_GoogleLogin`:
  - Authorization Code flow, có `state` chống CSRF (và PKCE nếu thư viện hỗ trợ); scope `openid email profile`; chỉ nhận email đã xác minh.
  - Route `technest_google/oauth/start` và `technest_google/oauth/callback`; liên kết tài khoản qua bảng `technest_google_account` (`google_sub`, `customer_id`); email đã tồn tại thì liên kết, chưa có thì tạo khách hàng mới.
  - Người dùng Google chưa có SĐT thì bắt bổ sung `phone_number` ngay sau lần đăng nhập đầu.
  - Client ID/Secret cấu hình ở Admin (**Stores > Configuration > TechNest > Google Login**, secret mã hóa) hoặc `.env`; không hard-code.
  - Thư viện: `league/oauth2-google` hoặc Guzzle thuần, chỉ dùng nếu tương thích PHP 8.5.
- **Thanh toán:** Cash on Delivery và Bank Transfer (có sẵn trong core: chỉ cấu hình + nội dung hướng dẫn chuyển khoản). **Không** làm VNPay/MoMo, **không** viết payment method mới.
- **Vận chuyển:** Flat Rate, **Free Shipping theo ngưỡng** (ví dụ từ 500 USD / 12.500.000 VND), **voucher free ship** (Cart Price Rule loại Free Shipping), kèm **carrier tùy biến tính phí theo tỉnh/thành** (module `TechNest_Shipping`, xem mục 6). Các phương thức cùng hiển thị ở checkout; free shipping và voucher free ship phải áp dụng được lên carrier tùy biến.

### 5.6 Khuyến mãi

| Loại | Cách làm |
|---|---|
| Catalog Price Rule | Giảm theo danh mục (ví dụ -10% Phụ kiện) |
| Cart Price Rule | Mã giảm giá (ví dụ `WELCOME10`) và mã free ship |
| Flash sale theo thời gian | `special_price` + from/to date hoặc Catalog Price Rule có thời hạn; khối **countdown** + nhãn "Sale" trên trang chủ/danh mục |
| Combo/Bundle | Bundle product, giá cố định hoặc giảm % |

### 5.7 Đơn hàng và email

- Luồng: Order → Invoice → Shipment → Credit Memo.
- **Trạng thái đơn tùy chỉnh** (ví dụ "Chờ xác nhận chuyển khoản", "Đang đóng gói", "Đang giao") gắn với state có sẵn.
- **Email giao dịch gửi thật** (đơn hàng mới, invoice, shipment, credit memo, xác nhận tài khoản/quên mật khẩu), có bản **tiếng Việt và tiếng Anh**, header dùng logo TechNest.
- **SMTP thật** qua module nhỏ `TechNest_Mail`:
  - Trước hết kiểm tra lớp transport mail mà framework Magento 2.4.9 đang dùng (xem `vendor/magento/framework/Mail`), rồi viết plugin/chèn transport SMTP phù hợp. Cấu hình ở Admin hoặc `.env` (host, port, mã hóa, user, password, from).
  - Nhà cung cấp mặc định: **Gmail SMTP** (`smtp.gmail.com`, cổng 587 STARTTLS) với **App Password**. Phương án thay thế: Brevo free (300 mail/ngày).
  - Dùng cổng 587/465, không dùng cổng 25 (Oracle Cloud chặn cổng 25 outbound).
  - Dev dùng Mailpit; kiểm tra thật bằng cách gửi mail đơn hàng tới hộp thư của chủ dự án.

### 5.8 Phân quyền admin

- Role: `Catalog Manager`, `Sales Staff`, `Content Editor` với ACL khác nhau; module custom khai báo ACL resource riêng (ví dụ `TechNest_Warranty::manage`). Chỉ cần ACL/Role (không bắt buộc 2FA/reCAPTCHA; có thể tắt 2FA ở dev).

## 6. Custom module

Vendor namespace: **`TechNest`**; thư mục `app/code/TechNest/<Module>/`; composer package `technest/module-<ten>`. Module chuẩn: `registration.php`, `composer.json`, `etc/module.xml`, DI đúng cách, `i18n/vi_VN.csv`, test.

| Module | Ý tưởng | Nội dung chính |
|---|---|---|
| `TechNest_DataSeeder` | Hạ tầng dữ liệu | CLI `technest:seed:run`, DummyJSON + seed JSON, idempotent |
| `TechNest_ProductSpecs` (A) | Bảng thông số, so sánh | Block specs theo attribute set; tùy biến trang Compare (highlight khác biệt) |
| `TechNest_Warranty` (B, G, H) | Bảo hành theo serial | Bảng `technest_warranty` (declarative schema); **admin grid + form CRUD (UI Component)**; trang tra cứu frontend; **REST API** `GET /V1/technest/warranty/:serial` (+ GraphQL nếu còn thời gian); ACL |
| `TechNest_Installment` (C) | Trả góp | Block trang sản phẩm tính trả góp 3/6/12 tháng (lãi suất cấu hình admin), đúng khi đổi tiền tệ |
| `TechNest_Automation` (I) | Cron, CLI, observer | Cron (kiểm tra hết hạn flash sale, dọn dữ liệu), CLI, observer sau đặt hàng → tạo bản ghi bảo hành và/hoặc gửi email |
| `TechNest_GoogleLogin` | Đăng nhập Google | Xem 5.5 |
| `TechNest_CustomerAccess` | Chặn khách chưa đăng nhập, trường SĐT | Xem 5.5 |
| `TechNest_Mail` | SMTP thật | Xem 5.7 |
| `TechNest_QuickView` | Xem nhanh | Modal AJAX (ưu tiên thấp) |
| `TechNest_Shipping` (F) | Carrier theo tỉnh/thành | Carrier `technest_regional` tính phí theo vùng (Bắc/Trung/Nam) dựa trên tỉnh/thành của địa chỉ giao; bảng phí và tên phương thức cấu hình ở Admin; hỗ trợ cả USD/VND; tương thích Free Shipping theo ngưỡng và voucher free ship |

Test bắt buộc: PHPUnit unit test cho logic chính (tính trả góp, tính phí ship theo vùng, validate serial, validate SĐT, mapping Google → customer); integration test cho repository/API nếu kịp.

## 7. Hiệu năng

- Chạy **production mode** khi demo/deploy; `setup:di:compile`, `setup:static-content:deploy` cho `vi_VN`, `en_US`.
- Full Page Cache; session + cache trên **Valkey**; Varnish chỉ khi tài nguyên cho phép (nếu không, dùng FPC built-in).
- Indexer "Update by Schedule" + cron chạy ổn; tối ưu ảnh (WebP, kích thước phù hợp); minify/merge CSS/JS (bundling theo kết quả đo).
- **Performance test nhẹ bằng k6**: duyệt danh mục → tìm kiếm → xem sản phẩm → thêm giỏ (đã đăng nhập) → checkout COD. Ghi p95 và error rate trước/sau tối ưu. Mục tiêu: TTFB < 500ms khi cache nóng, không lỗi ở 20 VU.

## 8. Kiểm thử (agent chủ động chạy)

- Tĩnh: `phpcs --standard=Magento2`, `phpstan`, `setup:di:compile`.
- PHPUnit cho module custom.
- E2E smoke (Playwright): đăng ký (có SĐT), đăng nhập Google (mock hoặc thủ công ở môi trường có redirect URI), thêm giỏ khi chưa đăng nhập (bị chuyển hướng), áp mã giảm giá, checkout COD, so sánh sản phẩm, đổi ngôn ngữ/tiền tệ.
- Sau mỗi phase: chạy test liên quan và báo ngắn gọn (pass/fail, lỗi còn lại).

## 9. Git, CI/CD (GitHub)

- Nhánh: `main` (ổn định/deploy), `develop`, `feature/<ten>`; commit theo Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`), commit nhỏ và thường xuyên.
- **CI (GitHub Actions)** khi push/PR: PHPCS, PHPStan, PHPUnit, `setup:di:compile`.
- **CD** khi merge vào `main`: SSH vào server demo → pull → `composer install --no-dev` → `setup:upgrade` → compile → deploy static → flush cache → health check, có rollback đơn giản (giữ release trước).
- Secrets lưu ở **GitHub Secrets** (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, ...). `.gitignore` chuẩn Magento.
- Cấu trúc repo: `app/code`, `app/design`, `brand/`, `docker/`, `docs/`, `scripts/`, `tests/` (unit, e2e, perf), `.github/workflows/`.

## 10. Triển khai: Oracle Cloud Always Free

- **VM:** `VM.Standard.A1.Flex` (ARM64, tối đa 4 OCPU/24GB RAM, dùng khoảng 4 OCPU/16-24GB), Ubuntu LTS aarch64, boot volume 100GB+. Tạo swap 4GB.
- **Docker:** dùng lại stack docker compose; xác nhận mọi image (PHP 8.5, MariaDB, OpenSearch 3, Valkey, RabbitMQ, nginx) có bản **arm64**; build image PHP trên chính VM nếu cần.
- **Mạng:** mở cổng 80/443 ở VCN Security List **và** firewall hệ điều hành (iptables/ufw); SSH chỉ bằng key.
- **Domain/HTTPS:** subdomain miễn phí **DuckDNS** (ví dụ `technest-demo.duckdns.org`) trỏ về IP công khai; **Caddy** làm reverse proxy trước nginx, tự cấp Let's Encrypt. Đặt Magento `base_url` về domain này.
- **Rủi ro cần biết:** (1) hay báo "Out of capacity" khi tạo VM ARM, cần thử lại/đổi availability domain; (2) Oracle có thể thu hồi instance Always Free nếu rảnh kéo dài, nên giữ có hoạt động hoặc nâng cấp tài khoản lên Pay As You Go (vẫn dùng tài nguyên Always Free miễn phí); (3) sao lưu DB định kỳ bằng cron (`mysqldump`) ra thư mục backup.
- Dữ liệu demo: chạy `technest:seed:run` trên server, tạo tài khoản demo (khách + admin phân quyền) và ghi vào `docs/`, **không** ghi mật khẩu thật vào repo.

## 11. Kế hoạch theo phase (≈ 12 buổi x 2h)

| Phase | Buổi | Kết quả |
|---|---|---|
| 0. Chuẩn bị | 1 | Rà soát repo/môi trường, thư mục `brand/`, CI khung, store view en/vi, tiền tệ, gói ngôn ngữ |
| 1. Dữ liệu và catalog | 2-3 | Seeder: category, attribute set, ~80 sản phẩm (simple/configurable/bundle/grouped), ảnh, tồn kho; layered navigation |
| 2. Theme và UI | 4-5 | Theme con TechNest, logo/favicon, trang chính, responsive, compare/wishlist/review, trường SĐT, chặn khách vãng lai |
| 3. Khuyến mãi, checkout, tài khoản | 6-7 | Price rules, flash sale + countdown, free ship, COD/Bank transfer, order status, SMTP thật + email VI/EN, Google login |
| 4. Custom module | 8-9 | Warranty (CRUD/API/ACL), Installment, ProductSpecs, Shipping, Automation, QuickView; test |
| 5. Hiệu năng | 10 | Production mode, cache, k6, báo cáo trước/sau |
| 6. Deploy | 11 | Oracle VM, HTTPS, CD, dữ liệu demo, tài khoản demo |
| 7. Tài liệu | 12 | README, sơ đồ kiến trúc (Mermaid), tài liệu nghiệp vụ chuẩn BA (mục tiêu, phạm vi, yêu cầu chức năng/phi chức năng, use case, luồng nghiệp vụ, quy tắc nghiệp vụ, ma trận truy vết, test case) |

Nếu trễ tiến độ, cắt theo thứ tự: QuickView → GraphQL → Varnish → Virtual product → Grouped.

## 12. Definition of Done

- [ ] Chạy trên domain demo có HTTPS, bản `en`/`vi` đúng tiền tệ, đúng thương hiệu TechNest
- [ ] ~20 sản phẩm/nhóm, có ảnh, thông số, lọc theo brand và thuộc tính
- [ ] Luồng mua trọn vẹn: đăng ký (email + SĐT) hoặc Google → thêm giỏ → mã giảm giá → ship → COD/chuyển khoản → invoice → shipment → credit memo
- [ ] Khách chưa đăng nhập bị chặn thêm giỏ/mua
- [ ] Email đơn hàng gửi thật, tiếng Việt/Anh, có logo
- [ ] Flash sale có countdown, Catalog/Cart rule, Bundle hoạt động
- [ ] So sánh, wishlist, review, quick view, tìm kiếm gợi ý hoạt động
- [ ] Module Warranty (CRUD + REST), Installment, ProductSpecs, Shipping, Automation, GoogleLogin, Mail chạy và có test
- [ ] Phí ship theo tỉnh/thành hiển thị đúng ở checkout, free ship theo ngưỡng/voucher áp dụng đúng
- [ ] Role admin phân quyền đúng
- [ ] CI xanh; CD deploy được
- [ ] Báo cáo performance (trước/sau)
- [ ] README, sơ đồ kiến trúc, tài liệu nghiệp vụ BA

## 13. Quy tắc làm việc cho Claude Code

1. Bắt đầu mỗi phiên: `git status`, đọc file này, xem mục "Tiến độ" bên dưới; với việc lớn thì lập kế hoạch ngắn trước khi code.
2. Làm tuần tự theo phase; cuối phase báo ngắn: đã làm gì, kết quả test, việc còn tồn; cập nhật mục "Tiến độ".
3. Chủ động chạy lệnh và test, không bắt chủ dự án chạy hộ nếu agent làm được.
4. Cấu hình quan trọng phải nằm trong code/script (data patch, `config:set`, seed) để chạy lại được; tránh chỉ chỉnh tay trong admin.
5. **Hỏi chủ dự án trước khi:** đổi quyết định ở phần 5, thêm package ngoài, xóa/ghi đè dữ liệu (`DROP`, `setup:uninstall`, xóa sample data), đụng đến server production, hoặc cần thông tin/quyền ở bảng dưới.
6. Không in secret ra log/chat, không commit secret.

### Thông tin chủ dự án cần cung cấp (agent hỏi đúng lúc cần)

| Khi nào | Cần gì | Cách cung cấp |
|---|---|---|
| Phase 3 (Google login) | **Google OAuth Client ID + Secret**: Google Cloud Console → tạo project → OAuth consent screen (External, Testing; thêm email người xem demo vào Test users, hoặc Publish) → Credentials → OAuth client ID loại *Web application*. Redirect URI: dev `http://localhost:<cổng>/technest_google/oauth/callback`, prod `https://<domain>/technest_google/oauth/callback` | Tự nhập vào Admin config hoặc `.env`, **không dán vào chat/commit** |
| Phase 3 (email thật) | **Gmail App Password** (bật 2-Step Verification → Security → App passwords) hoặc tài khoản Brevo; địa chỉ gửi đi | Tự nhập vào Admin config hoặc `.env` |
| Phase 6 (deploy) | Tài khoản **Oracle Cloud** (cần thẻ để xác minh), VM ARM đã tạo hoặc quyền để hướng dẫn tạo, **IP công khai**, cặp SSH key (private key đưa vào GitHub Secrets) | GitHub Secrets; IP nói trong chat |
| Phase 6 (domain) | Tài khoản **DuckDNS** (miễn phí) và tên subdomain mong muốn | Token đưa vào GitHub Secrets/`.env` của server |

## 14. Tiến độ (agent cập nhật)

- [ ] Phase 0 — Chuẩn bị
- [ ] Phase 1 — Dữ liệu và catalog
- [ ] Phase 2 — Theme và UI
- [ ] Phase 3 — Khuyến mãi, checkout, tài khoản
- [ ] Phase 4 — Custom module
- [ ] Phase 5 — Hiệu năng
- [ ] Phase 6 — Deploy
- [ ] Phase 7 — Tài liệu
