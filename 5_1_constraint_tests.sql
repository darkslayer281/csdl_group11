-- =====================================================================
-- 5_1_constraint_tests.sql - Test case chung minh rang buoc chan du lieu sai
-- Chay SAU 4_1 va 4_2a. Nen chay bang: mysql --force < file  (hoac Workbench: bo chon
-- "Stop script execution on errors") de cac loi duoc in ra va script chay tiep.
-- Cac test thanh cong deu boc trong START TRANSACTION ... ROLLBACK de khong doi du lieu.
-- =====================================================================
USE ev_charging_db;

-- ======================= NHOM A: rang buoc khai bao ==================
SELECT 'T01 UNIQUE SDT trung' AS Test;
INSERT INTO KHACH_HANG (HoTen, SDT) VALUES ('Test Trung SDT', '0901234567');

SELECT 'T02 UNIQUE BienSo trung' AS Test;
INSERT INTO PHUONG_TIEN (BienSo, HangXe, DungLuongPin_kWh, MaKH)
VALUES ('51H-123.45', 'VinFast VF8', 50, 1);

SELECT 'T03 CHECK dung luong pin <= 0' AS Test;
INSERT INTO PHUONG_TIEN (BienSo, HangXe, DungLuongPin_kWh, MaKH)
VALUES ('51Z-000.01', 'VinFast VF8', -10, 1);

SELECT 'T04 NOT NULL HoTen' AS Test;
INSERT INTO KHACH_HANG (HoTen, SDT) VALUES (NULL, '0911111111');

SELECT 'T05 ENUM LoaiKH ngoai mien gia tri' AS Test;
INSERT INTO KHACH_HANG (HoTen, SDT, LoaiKH) VALUES ('Test Enum', '0922222222', 'VIP');

SELECT 'T06 CHECK kinh do ngoai [-180,180]' AS Test;
INSERT INTO TRAM_SAC (TenTram, DiaChi, KinhDo, ViDo) VALUES ('Tram X', 'Dia chi X', 200, 10);

SELECT 'T07 UNIQUE (MaTram, SoHieuCong)' AS Test;
INSERT INTO CONG_SAC (SoHieuCong, LoaiDauSac, MaTram) VALUES ('A01', 'AC', 1);

SELECT 'T08 CHECK BANG_GIA: gio ket thuc <= gio bat dau (dung tram tam de khong vuong trigger BR18)' AS Test;
START TRANSACTION;
INSERT INTO TRAM_SAC (TenTram, DiaChi) VALUES ('Tram tam', 'x');
SET @tt = LAST_INSERT_ID();
INSERT INTO BANG_GIA (MaTram, LoaiNgay, KhungGioBatDau, KhungGioKetThuc, DonGia_kWh)
VALUES (@tt, 'Thuong', '10:00:00', '08:00:00', 3000);

SELECT 'T09 CHECK BANG_GIA: don gia am' AS Test;
INSERT INTO BANG_GIA (MaTram, LoaiNgay, KhungGioBatDau, KhungGioKetThuc, DonGia_kWh)
VALUES (@tt, 'Thuong', '10:00:00', '11:00:00', -1);
ROLLBACK;

SELECT 'T10 CHECK DAT_CHO: ket thuc <= bat dau (BR9, BR21)' AS Test;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, MaKH, MaXe, MaCong)
VALUES ('2026-12-01 10:00:00', '2026-12-01 09:00:00', 1, 1, 1);

SELECT 'T11 FK: MaCong khong ton tai (BR22)' AS Test;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, MaKH, MaXe, MaCong)
VALUES ('2026-12-01 08:00:00', '2026-12-01 09:00:00', 1, 1, 999);

SELECT 'T12 FK RESTRICT: xoa tram dang co du lieu con (BR22)' AS Test;
DELETE FROM TRAM_SAC WHERE MaTram = 1;

SELECT 'T13 CHECK PHIEN_SAC: kWh am (BR17)' AS Test;
INSERT INTO PHIEN_SAC (TG_BatDauTT, TG_KetThucTT, DienNangTieuThu_kWh, MaDatCho)
VALUES ('2026-10-22 08:00:00', '2026-10-22 09:00:00', -5, 17);

SELECT 'T14 CHECK PHIEN_SAC: ket thuc < bat dau (BR21)' AS Test;
INSERT INTO PHIEN_SAC (TG_BatDauTT, TG_KetThucTT, DienNangTieuThu_kWh, MaDatCho)
VALUES ('2026-10-22 09:00:00', '2026-10-22 08:00:00', 5, 17);

SELECT 'T15 UNIQUE 1:1: hai hoa don cho cung mot phien' AS Test;
INSERT INTO HOA_DON (TongTien, MaPhien, MaBangGia)
SELECT 1000, MaPhien, 2 FROM HOA_DON LIMIT 1;

SELECT 'T16 CHECK HOA_DON: tong tien am' AS Test;
INSERT INTO HOA_DON (TongTien, MaPhien, MaBangGia) VALUES (-1, 15, 2);

-- ======================= NHOM B: trigger =============================
-- Du lieu dung trong nhom nay (theo muc 4.2): dat cho 17 (xe 51H-123.45, cong A02 = MaCong 2,
-- 22/10 08:00-10:00, Confirmed), dat cho 18 (xe 59A-246.80, cong A04 = MaCong 4, Pending),
-- phien 15 dang chay tren cong 3, cong 9 = Maintenance, cong 12 = Inactive.
SET @xe_an  = (SELECT MaXe FROM PHUONG_TIEN WHERE BienSo = '51H-123.45');
SET @kh_an  = (SELECT MaKH FROM PHUONG_TIEN WHERE BienSo = '51H-123.45');
SET @kh_khac = (SELECT MaKH FROM PHUONG_TIEN WHERE BienSo = '59A-246.80');

SELECT 'T17 BR10: dat cho chong gio tren cung cong (INSERT)' AS Test;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, TrangThaiDat, MaKH, MaXe, MaCong)
VALUES ('2026-10-22 09:00:00', '2026-10-22 10:30:00', 'Pending', @kh_an, @xe_an, 2);

SELECT 'T18 Doi chung: dat cho SAT bien (10:00-11:00 ngay sau 08:00-10:00) la HOP LE' AS Test;
START TRANSACTION;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, TrangThaiDat, MaKH, MaXe, MaCong)
VALUES ('2026-10-22 10:00:00', '2026-10-22 11:00:00', 'Pending', @kh_an, @xe_an, 2);
SELECT MaDatCho, TrangThaiDat FROM DAT_CHO WHERE MaCong = 2 AND TG_DatBatDau = '2026-10-22 10:00:00';
ROLLBACK;

SELECT 'T19 BR10: doi cong cua dat cho 18 sang cong 2 (trung gio voi dat cho 17) (UPDATE)' AS Test;
UPDATE DAT_CHO SET MaCong = 2 WHERE MaDatCho = 18;

SELECT 'T20 BR7/BR8: xe khong thuoc khach hang' AS Test;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, MaKH, MaXe, MaCong)
VALUES ('2026-12-01 08:00:00', '2026-12-01 09:00:00', @kh_khac, @xe_an, 1);

SELECT 'T21 BR27: dat cho tren cong Maintenance (MaCong 9)' AS Test;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, MaKH, MaXe, MaCong)
VALUES ('2026-12-01 08:00:00', '2026-12-01 09:00:00', @kh_an, @xe_an, 9);

SELECT 'T22 BR27: dat cho tren cong Inactive (MaCong 12)' AS Test;
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, MaKH, MaXe, MaCong)
VALUES ('2026-12-01 08:00:00', '2026-12-01 09:00:00', @kh_an, @xe_an, 12);

SELECT 'T23 BR15: tao phien sac cho dat cho dang Pending (dat cho 18)' AS Test;
INSERT INTO PHIEN_SAC (TG_BatDauTT, MaDatCho) VALUES ('2026-10-22 09:00:00', 18);

SELECT 'T24 BR3: cong 3 dang co phien chay, khong cho phien thu hai' AS Test;
START TRANSACTION;
INSERT INTO DAT_CHO (MaDatCho, TG_DatBatDau, TG_DatKetThuc, TrangThaiDat, MaKH, MaXe, MaCong)
SELECT 9002, '2026-12-01 08:00:00', '2026-12-01 09:00:00', 'Confirmed', d.MaKH, d.MaXe, d.MaCong
FROM DAT_CHO d WHERE d.MaDatCho = 15;
INSERT INTO PHIEN_SAC (TG_BatDauTT, MaDatCho) VALUES ('2026-12-01 08:00:00', 9002);
ROLLBACK;

SELECT 'T25 BR18: khung gia chong lan (16:00-18:00 cat khung 06:00-17:00 va 17:00-22:00)' AS Test;
INSERT INTO BANG_GIA (MaTram, LoaiNgay, KhungGioBatDau, KhungGioKetThuc, DonGia_kWh)
VALUES (1, 'Thuong', '16:00:00', '18:00:00', 4000);

SELECT 'T26 EER: cong AC (MaCong 1) bi dua vao CONG_DC_NHANH' AS Test;
INSERT INTO CONG_DC_NHANH (MaCong, CongSuat_kW, ChuanKetNoi) VALUES (1, 50, 'CCS2');

SELECT 'T27 EER: cong DC (MaCong 2, CCS2) bi dua vao CONG_AC' AS Test;
INSERT INTO CONG_AC (MaCong, CongSuat_kW) VALUES (2, 7.4);

SELECT 'T28 EER: ChuanKetNoi khac LoaiDauSac cua cong cha' AS Test;
INSERT INTO CONG_DC_NHANH (MaCong, CongSuat_kW, ChuanKetNoi) VALUES (4, 50, 'CCS2');

SELECT 'T29 BR28: xoa cung DAT_CHO' AS Test;
DELETE FROM DAT_CHO WHERE MaDatCho = 17;

SELECT 'T30 BR28: xoa cung PHIEN_SAC' AS Test;
DELETE FROM PHIEN_SAC WHERE MaPhien = (SELECT MaPhien FROM HOA_DON LIMIT 1);

SELECT 'T31 BR28: xoa cung HOA_DON' AS Test;
DELETE FROM HOA_DON LIMIT 1;

SELECT 'T32 BR28: xoa cung BANG_GIA' AS Test;
DELETE FROM BANG_GIA WHERE MaBangGia = 1;

-- ======================= NHOM C: hanh vi tu dong =====================
SELECT 'T33 BR11: ket thuc phien dang chay 15 -> dat cho Completed, cong Available' AS Test;
START TRANSACTION;
SELECT 'truoc' AS Moc, d.TrangThaiDat, c.TrangThai AS TrangThaiCong
FROM DAT_CHO d JOIN CONG_SAC c ON c.MaCong = d.MaCong WHERE d.MaDatCho = 15;
UPDATE PHIEN_SAC SET TG_KetThucTT = '2026-10-21 11:00:00' WHERE MaPhien = 15;
SELECT 'sau' AS Moc, d.TrangThaiDat, c.TrangThai AS TrangThaiCong
FROM DAT_CHO d JOIN CONG_SAC c ON c.MaCong = d.MaCong WHERE d.MaDatCho = 15;
ROLLBACK;
