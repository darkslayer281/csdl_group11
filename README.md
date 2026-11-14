# CSDL_GROUP11 – Smart City EV Charging & Parking Management

Đồ án môn Cơ sở dữ liệu – Học viện Công nghệ Bưu chính Viễn thông cơ sở TP.HCM (PTITHCM)

## 1. Thông tin chung
- **Môn học:** Cơ sở dữ liệu
- **Lớp / Khóa:** D25CQCAT02-N – Khóa 2025
- **Giảng viên hướng dẫn:** Lê Hà Thanh (lehathanh@ptithcm.edu.vn)
- **Học kỳ / Năm học:** Học kì I (2026 - 2027)

## 2. Thành viên nhóm
| STT | Họ và tên | MSSV | GitHub |
|-----|-----------|------|--------|
| 1 | Nguyễn Tiến Trung | N25DCAT117 | [@darkslayer281](https://github.com/darkslayer281) |
| 2 | Mai Chí Trung | N25DCAT116 | [@mct727069](https://github.com/mct727069) |
| 3 | Lê Hữu Triết | N25DCAT115 | [@lhtriet2906-source](https://github.com/lhtriet2906-source) |

## 3. Giới thiệu dự án
[2–4 câu mô tả: bài toán quản lý trạm sạc xe điện và bãi đỗ xe trong thành phố thông minh, các thực thể chính, mục tiêu của CSDL.]

## 4. Cấu trúc repo
| File | Nội dung |
|------|----------|
| `4_1_ddl_script.sql` | Script tạo cấu trúc CSDL (DDL) |
| `4_2a_mock_data.sql` | Dữ liệu mẫu |
| `4_2b_queries_and_performance.sql` | Truy vấn và đánh giá hiệu năng |
| `Project_Report_Phase1.pdf` | Báo cáo giai đoạn 1 |
| `Project_Report_Phase2.docx` | Báo cáo giai đoạn 2 |
| `group11_phase2_week6.docx`, `group11_phase2_week9.docx` | Báo cáo tiến độ tuần 6, tuần 9 |

## 5. Hướng dẫn cài đặt
- Yêu cầu: [DBMS và phiên bản, ví dụ MySQL 8.0 / PostgreSQL 16]
- Clone repo:
  ```bash
  git clone https://github.com/darkslayer281/csdl_group11.git
  cd csdl_group11
  ```

## 6. Triển khai
1. Tạo database: `CREATE DATABASE [tên_db];`
2. Chạy `4_1_ddl_script.sql` để tạo bảng, khóa, ràng buộc.
3. Chạy `4_2a_mock_data.sql` để nạp dữ liệu mẫu.

## 7. Vận hành
- Chạy `4_2b_queries_and_performance.sql` để thực thi các truy vấn và xem kết quả/hiệu năng.
- [Ghi chú thêm: index, view, procedure… nếu có]

## 8. Tài liệu, dữ liệu và báo cáo
- Báo cáo chi tiết: xem các file `Project_Report_Phase1.pdf`, `Project_Report_Phase2.docx`.
- Tập dữ liệu: `4_2a_mock_data.sql`.
