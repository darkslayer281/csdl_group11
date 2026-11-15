-- =====================================================================
-- 5_2_rbac_tests.sql - Kiem thu phan quyen. Moi khoi chay bang MOT tai khoan khac nhau
-- (Workbench: tao connection moi cho tung tai khoan; hoac mysql -u <tai khoan> -p).
-- Mat khau demo: admin_demo = Admin#2026 | op_1, op_2-3 = Oper#2026 | kh_1, kh_2 = Cust#2026
-- Du lieu: MaKH 1 = Nguyen Van An (xe 51H-123.45, dat cho 17); MaKH 3 = Le Hoang Cuong (dat cho 18).
-- Mat khau demo them: kh_3 = Cust#2026
-- =====================================================================
USE ev_charging_db;

-- ############ Dang nhap bang kh_1 (Customer, MaKH = 1) ############
SELECT MaDatCho, TrangThaiDat, SoHieuCong, TenTram FROM v_kh_dat_cho;      -- R01 chi thay dat cho cua minh
SELECT MaHD, TongTien FROM v_kh_hoa_don;                                    -- R01 chi thay hoa don cua minh
SELECT * FROM KHACH_HANG;                                                   -- R02 loi 1142
SELECT * FROM DAT_CHO;                                                      -- R03 loi 1142
INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, MaKH, MaXe, MaCong)
VALUES ('2026-12-05 08:00:00', '2026-12-05 09:00:00', 1, 1, 1);             -- R04 loi 1142
DELETE FROM DAT_CHO WHERE MaDatCho = 17;                                    -- R05 loi 1142
UPDATE v_kh_dat_cho SET TrangThaiDat = 'Completed' WHERE MaDatCho = 17;     -- R06 loi 1142
SELECT * FROM AUDIT_LOG;                                                    -- R07 loi 1142
CALL sp_khach_dat_cho(1, 2, '2026-10-22 09:00:00', '2026-10-22 10:30:00');  -- R08 loi [BR10]
CALL sp_khach_dat_cho(2, 1, '2026-12-05 08:00:00', '2026-12-05 09:00:00');  -- R09 loi [BR7/BR8] (xe cua khach khac)
CALL sp_khach_dat_cho(1, 1, '2026-12-05 08:00:00', '2026-12-05 09:00:00');  -- R10 thanh cong, tra ve MaDatChoMoi
CALL sp_khach_huy_dat_cho(18);                                              -- R11 loi [RBAC] (dat cho cua nguoi khac)
SET @ma_kh = 2;                                                             -- R12 thu gia mao pham vi bang bien phien
SELECT COUNT(*) AS SoDatChoThayDuoc FROM v_kh_dat_cho;                      --     ket qua van la cua MaKH = 1
SELECT COUNT(*) AS CongDangTrong FROM v_cong_dang_trong;                    -- R13 thong tin cong khai doc duoc

-- ############ Dang nhap bang kh_3 (Customer, MaKH = 3) ############
SELECT MaDatCho, TrangThaiDat FROM v_kh_dat_cho WHERE MaDatCho IN (17, 18);  -- chi thay dat cho 18, khong thay 17

-- ############ Dang nhap bang op_1 (Operator, tram 1 - Landmark 81) ############
SELECT MaDatCho, MaCong, TrangThaiDat FROM v_op_dat_cho;                    -- O01 chi dat cho cua cong thuoc tram 1
SELECT SoHieuCong, TrangThai FROM v_op_cong_sac;                            -- O01 chi cong cua tram 1
UPDATE v_op_dat_cho SET TrangThaiDat = 'Cancelled' WHERE MaDatCho = 18;     -- O02 thanh cong (1 dong)
UPDATE v_op_dat_cho SET TrangThaiDat = 'Cancelled' WHERE MaDatCho = 16;     -- O03 0 dong (dat cho cua tram khac)
UPDATE v_op_dat_cho SET TG_DatKetThuc = '2026-10-22 12:00:00' WHERE MaDatCho = 17;  -- O04 loi 1143 (cot khong duoc cap)
UPDATE BANG_GIA SET DonGia_kWh = 1 WHERE MaBangGia = 3;                     -- O05 loi 1142 (BR25)
UPDATE CONG_SAC SET TrangThai = 'Maintenance' WHERE MaCong = 1;             -- O06 loi 1142 (BR24)
SELECT * FROM KHACH_HANG;                                                   -- O07 loi 1142

-- ############ Dang nhap bang op_2-3 (Operator, tram 2 va 3) ############
SELECT MaDatCho, MaCong FROM v_op_dat_cho;                                  -- O08 khong thay dat cho cua tram 1

-- ############ Dang nhap bang admin_demo (Admin) ############
UPDATE BANG_GIA SET DonGia_kWh = 4600 WHERE MaBangGia = 3;                  -- A01 thanh cong
SELECT TenBang, MaBanGhi, HanhDong, NguoiThucHien,
       JSON_EXTRACT(GiaTriCu, '$.DonGia_kWh') AS Cu,
       JSON_EXTRACT(GiaTriMoi, '$.DonGia_kWh') AS Moi
FROM AUDIT_LOG ORDER BY MaLog DESC LIMIT 1;                                 -- A01 NguoiThucHien = admin_demo@localhost
UPDATE CONG_SAC SET TrangThai = 'Maintenance' WHERE MaCong = 5;             -- A02 thanh cong, ghi AUDIT_LOG
DELETE FROM DAT_CHO WHERE MaDatCho = 17;                                    -- A03 loi 1142 (khong co DELETE - BR28)
UPDATE AUDIT_LOG SET HanhDong = 'INSERT' WHERE MaLog = 1;                   -- A04 loi 1142 (nhat ky bat bien)
INSERT INTO AUDIT_LOG (TenBang, MaBanGhi, HanhDong, NguoiThucHien)
VALUES ('x', 1, 'INSERT', 'x');                                             -- A05 loi 1142

-- ############ Dang nhap bang root: thu hoi quyen (REVOKE) roi kiem tra lai ############
REVOKE 'role_customer' FROM 'kh_2'@'localhost';                             -- V01 -> kh_2 dang nhap lai: loi 1044
GRANT  'role_customer' TO   'kh_2'@'localhost';
SET DEFAULT ROLE ALL TO 'kh_2'@'localhost';
REVOKE UPDATE (TrangThaiDat) ON ev_charging_db.v_op_dat_cho FROM 'role_operator';  -- V02 -> op_1 UPDATE: loi 1142
GRANT  UPDATE (TrangThaiDat) ON ev_charging_db.v_op_dat_cho TO   'role_operator';
