# CSDL_GROUP11 – Smart City EV Charging & Parking Management

Đồ án môn Cơ sở dữ liệu – Học viện Công nghệ Bưu chính Viễn thông cơ sở TP.HCM (PTITHCM)

## 1. Thông tin chung

- **Môn học:** Cơ sở dữ liệu
- **Lớp / Khóa:** D25CQCAT02-N – Khóa 2025
- **Giảng viên hướng dẫn:** Lê Hà Thanh ([lehathanh@ptithcm.edu.vn](mailto:lehathanh@ptithcm.edu.vn))
- **Học kỳ / Năm học:** Học kì I (2026 - 2027)

## 2. Thành viên nhóm

| STT | Họ và tên         | MSSV       | GitHub                                                       |
| --- | ----------------- | ---------- | ------------------------------------------------------------ |
| 1   | Nguyễn Tiến Trung | N25DCAT117 | [@darkslayer281](https://github.com/darkslayer281)           |
| 2   | Mai Chí Trung     | N25DCAT116 | [@mct727069](https://github.com/mct727069)                   |
| 3   | Lê Hữu Triết      | N25DCAT115 | [@lhtriet2906-source](https://github.com/lhtriet2906-source) |

## 3. Giới thiệu dự án

Dự án xây dựng cơ sở dữ liệu quản lý **trạm sạc xe điện và bãi đỗ xe trong thành phố thông minh**. Hệ thống quản lý khách hàng (cá nhân / doanh nghiệp) cùng phương tiện của họ, các trạm sạc và cổng sạc (AC và DC nhanh), bảng giá điện theo khung giờ, quy trình **đặt chỗ → phiên sạc → hóa đơn**, và nhật ký thay đổi phục vụ kiểm toán.

**Các thực thể chính:** `KHACH_HANG`, `PHUONG_TIEN`, `TRAM_SAC`, `CONG_SAC` (chuyên biệt hóa thành `CONG_AC` và `CONG_DC_NHANH`), `BANG_GIA`, `DAT_CHO`, `PHIEN_SAC`, `HOA_DON`, `NGAY_LE`, `HOA_DON_KHUNG_GIA` và `AUDIT_LOG`.

**Mục tiêu của CSDL:**

- Theo dõi tình trạng khả dụng của từng cổng sạc theo thời gian thực.
- Đặt chỗ không trùng lịch, chặn đặt cổng đang bảo trì.
- Tính tiền theo đơn giá kWh theo khung giờ, loại ngày (thường / cuối tuần / lễ tết) và phí đỗ xe.
- Báo cáo doanh thu, sản lượng điện, hiệu suất sử dụng cổng.
- Đảm bảo toàn vẹn dữ liệu bằng ràng buộc và trigger, lưu vết thay đổi bảng giá và trạng thái cổng.

### Mô hình dữ liệu

| Nhóm | Bảng | Ghi chú |
| ---- | ---- | ------- |
| Khách hàng & xe | `KHACH_HANG`, `PHUONG_TIEN` | Một khách có nhiều xe; `SDT`, `Email`, `BienSo` là duy nhất |
| Hạ tầng sạc | `TRAM_SAC`, `CONG_SAC`, `CONG_AC`, `CONG_DC_NHANH` | Chuyên biệt hóa kiểu *disjoint*: một cổng chỉ thuộc `CONG_AC` hoặc `CONG_DC_NHANH` |
| Giá | `BANG_GIA`, `NGAY_LE`, `HOA_DON_KHUNG_GIA` | Giá theo trạm, loại ngày và khung giờ; chi tiết hóa đơn lưu điện năng/chi phí theo từng mức giá |
| Nghiệp vụ | `DAT_CHO`, `PHIEN_SAC`, `HOA_DON` | `DAT_CHO` 1:1 `PHIEN_SAC` 1:1 `HOA_DON` |
| Kiểm toán | `AUDIT_LOG` | Lưu giá trị cũ/mới (JSON) khi đổi bảng giá hoặc trạng thái cổng |

**Thành phần chính:** 13 bảng, 5 view, 23 trigger và 10 index tự định nghĩa (theo script DDL).

### View

| View | Mục đích |
| ---- | -------- |
| `v_cong_sac_chi_tiet` | Gộp lớp cha `CONG_SAC` với 2 lớp con để lấy nhóm cổng và công suất |
| `v_cong_dang_trong` | Các cổng đang trống tại thời điểm hiện tại |
| `v_phien_sac_thoi_luong` | Thời lượng đặt chỗ và thời lượng sạc thực tế (thuộc tính dẫn xuất, không lưu) |
| `v_hoa_don_chi_tiet` | Hóa đơn kèm khách, xe, trạm, cổng và chi tiết các mức giá áp dụng |
| `v_doanh_thu_tram` | Số hóa đơn, tổng kWh, tổng doanh thu theo trạm |

### Ràng buộc nghiệp vụ được cài đặt bằng trigger

| Mã | Nội dung |
| -- | -------- |
| EER | Chuyên biệt hóa cổng sạc: `CONG_AC` / `CONG_DC_NHANH` phải khớp `LoaiDauSac` của cổng cha và không được giao nhau |
| BR3 | Một cổng tại một thời điểm chỉ phục vụ một phiên sạc đang chạy |
| BR7/BR8 | Xe đặt chỗ phải thuộc về đúng khách hàng |
| BR10 | Hai lịch đặt chỗ đang hiệu lực trên cùng một cổng không được chồng thời gian |
| BR15 | Chỉ tạo phiên sạc từ đặt chỗ ở trạng thái `Confirmed` |
| BR18 | Khung giờ của `BANG_GIA` không được chồng lấn trong cùng trạm và loại ngày |
| BR25/BR28 | Ghi `AUDIT_LOG` khi thay đổi bảng giá; cấm xóa cứng `DAT_CHO`, `PHIEN_SAC`, `HOA_DON`, `BANG_GIA` |
| BR27 | Cổng `Maintenance` / `Inactive` không nhận đặt chỗ mới; đổi trạng thái cổng được ghi log |

Vòng đời trạng thái được tự động hóa: bắt đầu phiên sạc thì đặt chỗ chuyển `Active` và cổng chuyển `Occupied`; kết thúc phiên thì đặt chỗ chuyển `Completed` và cổng trở lại `Available`.

### Phân quyền (RBAC)

Hệ thống có 3 vai trò (BR24, BR25, BR26, BR28), cài đặt trong `5_2_rbac.sql`:

| Vai trò | Quyền chính |
| ------- | ----------- |
| `role_admin` | `SELECT`, `INSERT`, `UPDATE` trên các bảng nghiệp vụ, **không có `DELETE`** (BR28). `AUDIT_LOG` chỉ được đọc, chỉ trigger mới ghi được nên nhật ký không sửa được |
| `role_operator` | Chỉ đọc dữ liệu thuộc trạm được giao qua các view `v_op_*`; xác nhận/hủy đặt chỗ và đổi trạng thái cổng qua stored procedure trong phạm vi trạm; chỉ đọc `BANG_GIA` |
| `role_customer` | Đọc thông tin công khai (`TRAM_SAC`, `BANG_GIA`, `v_cong_dang_trong`) và dữ liệu của chính mình qua `v_kh_phuong_tien`, `v_kh_dat_cho`, `v_kh_hoa_don`; **không ghi trực tiếp vào bảng**, chỉ đặt/hủy chỗ qua thủ tục `sp_khach_dat_cho` và `sp_khach_huy_dat_cho` |

**Cách xác định phạm vi dữ liệu:** hàm `fn_ma_kh_hien_tai()` và `fn_ds_tram_hien_tai()` suy ra phạm vi từ tên tài khoản đang kết nối (hàm `USER()`), nên người dùng không thể giả mạo bằng `SET @biến`. Quy ước đặt tên tài khoản:

- `kh_<MaKH>`: khách hàng, ví dụ `kh_1`.
- `op_<danh sách trạm>`: nhân viên trạm, ví dụ `op_1` (trạm 1) hoặc `op_2-3` (trạm 2 và 3).

Các thủ tục đặt/hủy chỗ chạy với `SQL SECURITY DEFINER` và vẫn đi qua các trigger nghiệp vụ (BR7/BR8, BR10, BR27). Ngoài ra `5_2_rbac.sql` tạo lại 3 trigger ghi nhật ký (`trg_bang_gia_ai`, `trg_bang_gia_au`, `trg_cong_sac_au`) để dùng `USER()` thay cho `CURRENT_USER()`, vì `CURRENT_USER()` trong trigger trả về người định nghĩa trigger (root) chứ không phải người đang thao tác.

**Tài khoản demo:** `admin_demo`, `op_1`, `op_2-3`, `kh_1`, `kh_2`, `kh_3` (tất cả `@localhost`). Mật khẩu demo được khai báo trong `5_2_rbac.sql`; đây chỉ là thông tin minh họa và **phải thay đổi trước khi triển khai thực tế**.

## 4. Cấu trúc repo

| File | Nội dung |
| ---- | -------- |
| `4_1_ddl_script.sql` | Script tạo cấu trúc CSDL (DDL): database, bảng, index, view, trigger |
| `4_2a_mock_data.sql` | Dữ liệu mẫu (hơn 12.000 dòng lệnh) |
| `4_2b_queries_and_performance.sql` | 13 truy vấn nâng cao và các test hiệu năng bằng `EXPLAIN ANALYZE` |
| `5_1_constraint_tests.sql` | Bộ test kiểm tra ràng buộc và trigger; báo cáo tổng hợp 46 test case (T01–T46) |
| `5_2_rbac.sql` | Phân quyền theo vai trò (RBAC): role, view giới hạn phạm vi, thủ tục, GRANT, tài khoản demo |
| `5_2_rbac_tests.sql` | Kịch bản kiểm thử phân quyền, chạy bằng từng tài khoản demo |
| `Project_Report_Phase1.pdf` | Báo cáo giai đoạn 1 |
| `Project_Report_Phase2.docx` | Báo cáo giai đoạn 2 |
| `group11_phase2_week6.docx`, `group11_phase2_week9.docx` | Báo cáo tiến độ tuần 6, tuần 9 |
| `final.docx` | Báo cáo tổng hợp |

## 5. Hướng dẫn cài đặt

- **Yêu cầu:**
  - MySQL **8.0.16 trở lên** (từ phiên bản này `CHECK` constraint mới được thực thi; script cũng dùng window function, CTE và `EXPLAIN ANALYZE` nên cần MySQL 8).
  - Công cụ chạy script: MySQL Workbench hoặc MySQL command-line client.
- Clone repo:

  ```bash
  git clone https://github.com/darkslayer281/csdl_group11.git
  cd csdl_group11
  ```

## 6. Triển khai

> ⚠️ `4_1_ddl_script.sql` bắt đầu bằng `DROP DATABASE IF EXISTS ev_charging_db;`, nên chạy lại sẽ **xóa toàn bộ dữ liệu cũ** của database này.

Chạy lần lượt theo đúng thứ tự:

1. **Tạo cấu trúc:** chạy `4_1_ddl_script.sql`. Script tự tạo database `ev_charging_db` (`utf8mb4`, `utf8mb4_unicode_ci`) rồi tạo bảng, index, view, trigger. Không cần `CREATE DATABASE` thủ công.
2. **Nạp dữ liệu mẫu:** chạy `4_2a_mock_data.sql`.
3. **Cài đặt phân quyền:** chạy `5_2_rbac.sql` bằng tài khoản quản trị CSDL (`root`). Script tạo 3 role, các view và thủ tục theo phạm vi, cấp quyền và tạo 6 tài khoản demo. Script chạy lại được nhiều lần (idempotent).

Chạy bằng command-line:

```bash
mysql -u root -p < 4_1_ddl_script.sql
mysql -u root -p --default-character-set=utf8mb4 < 4_2a_mock_data.sql
mysql -u root -p < 5_2_rbac.sql
```

Hoặc trong MySQL Workbench: **File → Open SQL Script…** chọn file rồi bấm **Execute** (biểu tượng tia sét).

Kiểm tra nhanh sau khi tạo cấu trúc:

```sql
USE ev_charging_db;
SHOW FULL TABLES IN ev_charging_db;   -- 11 bảng + 5 view (sau bước 1); thêm 6 view v_kh_*, v_op_* sau bước 3
SHOW TRIGGERS FROM ev_charging_db;    -- 16 trigger
SHOW INDEX FROM DAT_CHO;
```

## 7. Vận hành

Chạy `4_2b_queries_and_performance.sql` **sau khi** đã nạp dữ liệu mẫu. File gồm hai phần:

**Phần A – 13 truy vấn nâng cao**

| Truy vấn | Kỹ thuật | Nội dung |
| -------- | -------- | -------- |
| Q1 | JOIN 6 bảng | Lịch đặt chỗ sắp tới kèm khách, xe, trạm, cổng |
| Q2 | JOIN + Aggregation | Doanh thu, sản lượng kWh, doanh thu/kWh theo trạm và theo tháng |
| Q3 | Subquery trong HAVING | Khách có tổng chi tiêu cao hơn mức trung bình |
| Q4 | NOT EXISTS + scalar subquery | Khách hàng chưa từng sử dụng dịch vụ |
| Q5 | Conditional aggregation | Tỷ lệ hủy / no-show của từng khách |
| Q6 | Window `RANK()` | Cổng sạc công suất lớn nhất của mỗi trạm |
| Q7 | CTE + JOIN theo khoảng thời gian | Tính hóa đơn khi phiên sạc kéo dài qua nhiều khung giá, đối chiếu với `TongTien` đã lưu |
| Q8 | Self-join | Kiểm toán BR10: tìm các cặp đặt chỗ chồng thời gian (kỳ vọng 0 dòng) |
| Q9 | View + Aggregation | Hiệu suất sử dụng cổng (thời gian sạc thực tế / thời gian đã đặt) |
| Q10 | Subquery trong FROM + Window `SUM() OVER` | Phân bố phiên sạc theo giờ, đánh dấu cao điểm |
| Q11 | CTE + `ROW_NUMBER()` | Top 2 khách dùng nhiều điện nhất ở mỗi trạm |
| Q12 | Correlated subquery | Phiên đang chạy và đặt chỗ kế tiếp trên cùng cổng (cảnh báo xung đột) |
| Q13 | NOT EXISTS + View | Gợi ý cổng thay thế còn trống trong cùng trạm cho một khung giờ |

Một số truy vấn dùng biến phiên: `@now_demo = '2026-10-21 12:00:00'` (mốc "hôm nay" của bộ dữ liệu mẫu), `@phi_do_xe_gio` (phí đỗ xe giả định, VND/giờ) và `@tram`, `@tu`, `@den` cho Q13. Có thể sửa các giá trị này ở đầu file để thử các tình huống khác.

**Phần B – Đánh giá hiệu năng**

- Tạo bảng tạm `DAT_CHO_PERF` (bản sao cấu trúc và index của `DAT_CHO`, không có FK và trigger), nạp **200.000 dòng** bằng recursive CTE.
- So sánh `EXPLAIN ANALYZE` khi **có** index và khi **ép bỏ** index (`IGNORE INDEX`):

| Test | Index được kiểm tra | Tình huống |
| ---- | ------------------- | ---------- |
| P1 | `idx_dat_cho_cong_thoigian` | Kiểm tra trùng lịch trên một cổng (truy vấn của trigger BR10) |
| P2 | `idx_dat_cho_kh_thoigian` | Lịch sử đặt chỗ của một khách, sắp xếp theo thời gian (tránh filesort) |
| P3 | `idx_dat_cho_trangthai` | Lọc theo cột có độ chọn lọc thấp (6 giá trị) |
| P4 | – | Truy vấn Q13 trên dữ liệu thật, đảm bảo không full scan `DAT_CHO` |

- Cuối file script tự `DROP TABLE DAT_CHO_PERF` để dọn dẹp. Bảng kết quả mẫu ở cuối file dùng để điền số liệu đo được vào báo cáo.

> Lưu ý: bước nạp 200.000 dòng cần `cte_max_recursion_depth = 200000` (đã được đặt sẵn trong script) và có thể mất vài giây.

### Kiểm thử ràng buộc – `5_1_constraint_tests.sql`

Chạy **sau** `4_1` và `4_2a`. Báo cáo mô tả 46 test case (T01–T46); các test kiểm tra thao tác sai phải bị từ chối, cùng với một số test đối chứng hợp lệ:

| Nhóm | Test | Nội dung |
| ---- | ---- | -------- |
| A – Ràng buộc khai báo | T01–T16 | `UNIQUE` (SĐT, biển số, 1:1 phiên–hóa đơn), `CHECK` (pin, kinh/vĩ độ, khung giờ, đơn giá, kWh, tổng tiền, thời gian), `NOT NULL`, `ENUM`, khóa ngoại và `ON DELETE RESTRICT` |
| B – Trigger | Theo mã test trong script | Kiểm tra BR10 (trùng lịch), BR7/BR8 (xe không thuộc khách), BR27 (cổng bảo trì/ngưng), BR15, BR3, BR18, chuyên biệt hóa EER và BR28 (bảo toàn lịch sử) |
| Hành vi tự động | Theo mã test trong script | Kiểm tra cập nhật trạng thái đặt chỗ/cổng khi phiên sạc kết thúc |

Các test thao tác thành công đều được bọc trong `START TRANSACTION ... ROLLBACK` nên không làm thay đổi dữ liệu. Vì phần lớn test cố ý gây lỗi, hãy chạy script theo một trong hai cách để nó không dừng ở lỗi đầu tiên:

```bash
mysql -u root -p --force < 5_1_constraint_tests.sql
```

Hoặc trong MySQL Workbench, bỏ chọn **Stop script execution on errors**.

### Kiểm thử phân quyền – `5_2_rbac_tests.sql`

Chạy **sau** `5_2_rbac.sql`. Mỗi khối lệnh phải được chạy bằng **một tài khoản khác nhau** (trong Workbench tạo connection riêng cho từng tài khoản, hoặc dùng `mysql -u <tài khoản> -p`). Dữ liệu dùng trong test lấy từ bộ dữ liệu mẫu.

| Tài khoản | Test | Kết quả kỳ vọng |
| --------- | ---- | --------------- |
| `kh_1` (khách hàng) | R01–R13 | Chỉ thấy đặt chỗ và hóa đơn của mình; đọc bảng gốc, ghi trực tiếp, xóa, đọc `AUDIT_LOG` đều bị từ chối (lỗi 1142); đặt chỗ qua thủ tục bị trigger chặn khi trùng giờ hoặc xe không thuộc khách; không hủy được đặt chỗ của người khác; không giả mạo được phạm vi bằng `SET @ma_kh` |
| `kh_3` (khách hàng) | – | Chỉ thấy đặt chỗ 18, không thấy đặt chỗ 17 của khách khác |
| `op_1` (nhân viên trạm 1) | O01–O07 | Chỉ thấy dữ liệu trong phạm vi trạm 1; xử lý đặt chỗ qua stored procedure; không được cập nhật trực tiếp view/bảng, sửa bảng giá, đổi trạng thái cổng trực tiếp hoặc đọc dữ liệu khách hàng |
| `op_2-3` (nhân viên trạm 2, 3) | O08 | Không thấy đặt chỗ của trạm 1 |
| `admin_demo` (quản trị) | A01–A05 | Sửa bảng giá và đổi trạng thái cổng thành công và được ghi vào `AUDIT_LOG` đúng tên tài khoản; `DELETE` và sửa / ghi `AUDIT_LOG` bị từ chối |
| `root` | V01–V02 | Thu hồi quyền (`REVOKE`) rồi kiểm tra lại: tài khoản bị mất quyền ngay; sau đó cấp lại quyền bằng `GRANT` |

Hai lệnh `REVOKE` ở cuối file (V01, V02) được đi kèm lệnh `GRANT` để khôi phục, hãy chạy cả cặp để không làm mất quyền của tài khoản demo.

## 8. Tài liệu, dữ liệu và báo cáo

- Báo cáo chi tiết: xem `Project_Report_Phase1.pdf`, `Project_Report_Phase2.docx` và `final.docx`.
- Báo cáo tiến độ: `group11_phase2_week6.docx`, `group11_phase2_week9.docx`.
- Tập dữ liệu mẫu: `4_2a_mock_data.sql`.
- Kiểm thử và bảo mật: `5_1_constraint_tests.sql` (ràng buộc, trigger), `5_2_rbac.sql` và `5_2_rbac_tests.sql` (phân quyền).
