-- =====================================================================
-- 5_2_rbac.sql - Phan quyen theo vai tro (BR24, BR25, BR26, BR28)
-- Chay SAU 4_1 va 4_2a, voi tai khoan quan tri CSDL (root). Chay lai duoc nhieu lan.
-- Mat khau trong script chi la mat khau DEMO - phai doi khi trien khai that.
-- =====================================================================
USE ev_charging_db;

-- (A) Vai tro (BR26) --------------------------------------------------------
CREATE ROLE IF NOT EXISTS 'role_admin', 'role_operator', 'role_customer';

-- Dua ve trang thai sach de script chay lai duoc (idempotent)
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'role_admin', 'role_operator', 'role_customer';

-- (B) Ham xac dinh pham vi du lieu tu ten tai khoan dang ket noi -------------
-- USER() tra ve tai khoan da xac thuc luc ket noi nen khong gia mao duoc bang SET @bien.
-- Quy uoc: kh_<MaKH> (khach hang), op_<ds tram> (vd op_1 hoac op_1-2).
DROP FUNCTION IF EXISTS fn_ma_kh_hien_tai;
DROP FUNCTION IF EXISTS fn_ds_tram_hien_tai;
DELIMITER $$
CREATE FUNCTION fn_ma_kh_hien_tai() RETURNS INT
NO SQL
BEGIN
    DECLARE v_user VARCHAR(100);
    SET v_user = SUBSTRING_INDEX(USER(), '@', 1);
    IF v_user REGEXP '^kh_[0-9]+$' THEN
        RETURN CAST(SUBSTRING(v_user, 4) AS UNSIGNED);
    END IF;
    RETURN NULL;
END$$

CREATE FUNCTION fn_ds_tram_hien_tai() RETURNS VARCHAR(100)
NO SQL
BEGIN
    DECLARE v_user VARCHAR(100);
    SET v_user = SUBSTRING_INDEX(USER(), '@', 1);
    IF v_user REGEXP '^op_[0-9]+(-[0-9]+)*$' THEN
        RETURN REPLACE(SUBSTRING(v_user, 4), '-', ',');
    END IF;
    RETURN '';
END$$
DELIMITER ;

-- (C) View gioi han pham vi ----------------------------------------------------
CREATE OR REPLACE VIEW v_kh_phuong_tien AS
SELECT MaXe, BienSo, HangXe, DungLuongPin_kWh
FROM PHUONG_TIEN
WHERE MaKH = fn_ma_kh_hien_tai();

CREATE OR REPLACE VIEW v_kh_dat_cho AS
SELECT d.MaDatCho, d.TG_DatBatDau, d.TG_DatKetThuc, d.TrangThaiDat,
       d.MaXe, c.SoHieuCong, t.TenTram
FROM DAT_CHO d
JOIN CONG_SAC c ON c.MaCong = d.MaCong
JOIN TRAM_SAC t ON t.MaTram = c.MaTram
WHERE d.MaKH = fn_ma_kh_hien_tai();

CREATE OR REPLACE VIEW v_kh_hoa_don AS
SELECT MaHD, TenTram, SoHieuCong, TG_BatDauTT, TG_KetThucTT,
       DienNangTieuThu_kWh, TongTien, PhuongThucTT, TG_ThanhToan
FROM v_hoa_don_chi_tiet
WHERE MaKH = fn_ma_kh_hien_tai();

CREATE OR REPLACE VIEW v_op_cong_sac AS
SELECT * FROM v_cong_sac_chi_tiet
WHERE FIND_IN_SET(MaTram, fn_ds_tram_hien_tai()) > 0;

CREATE OR REPLACE VIEW v_op_dat_cho AS
SELECT d.*
FROM DAT_CHO d
WHERE d.MaCong IN (SELECT c.MaCong FROM CONG_SAC c
                   WHERE FIND_IN_SET(c.MaTram, fn_ds_tram_hien_tai()) > 0);

CREATE OR REPLACE VIEW v_op_hoa_don AS
SELECT * FROM v_hoa_don_chi_tiet
WHERE FIND_IN_SET(MaTram, fn_ds_tram_hien_tai()) > 0;

-- (D) Thu tuc cho khach hang: khach khong duoc ghi truc tiep vao DAT_CHO ------
DROP PROCEDURE IF EXISTS sp_khach_dat_cho;
DROP PROCEDURE IF EXISTS sp_khach_huy_dat_cho;
DELIMITER $$
CREATE PROCEDURE sp_khach_dat_cho(IN p_MaXe INT, IN p_MaCong INT,
                                  IN p_Bat DATETIME, IN p_Ket DATETIME)
SQL SECURITY DEFINER
BEGIN
    IF fn_ma_kh_hien_tai() IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '[RBAC] Only customer accounts may book';
    END IF;
    INSERT INTO DAT_CHO (TG_DatBatDau, TG_DatKetThuc, TrangThaiDat, MaKH, MaXe, MaCong)
    VALUES (p_Bat, p_Ket, 'Pending', fn_ma_kh_hien_tai(), p_MaXe, p_MaCong);
    SELECT LAST_INSERT_ID() AS MaDatChoMoi;
END$$

CREATE PROCEDURE sp_khach_huy_dat_cho(IN p_MaDatCho BIGINT)
SQL SECURITY DEFINER
BEGIN
    UPDATE DAT_CHO
       SET TrangThaiDat = 'Cancelled'
     WHERE MaDatCho = p_MaDatCho
       AND MaKH = fn_ma_kh_hien_tai()
       AND TrangThaiDat IN ('Pending', 'Confirmed');
    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '[RBAC] Reservation not found, not yours, or not cancellable';
    END IF;
END$$
DELIMITER ;

-- (E) GRANT ----------------------------------------------------------------
-- E1. Admin: tao/sua (khong DELETE - BR28); rieng AUDIT_LOG chi doc (chi trigger ghi)
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.KHACH_HANG     TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.PHUONG_TIEN    TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.TRAM_SAC       TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.CONG_SAC       TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.CONG_AC        TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.CONG_DC_NHANH  TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.BANG_GIA       TO 'role_admin';   -- BR25
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.DAT_CHO        TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.PHIEN_SAC      TO 'role_admin';
GRANT SELECT, INSERT, UPDATE ON ev_charging_db.HOA_DON        TO 'role_admin';
GRANT SELECT                 ON ev_charging_db.AUDIT_LOG      TO 'role_admin';
GRANT SELECT ON ev_charging_db.v_cong_sac_chi_tiet   TO 'role_admin';
GRANT SELECT ON ev_charging_db.v_cong_dang_trong     TO 'role_admin';
GRANT SELECT ON ev_charging_db.v_phien_sac_thoi_luong TO 'role_admin';
GRANT SELECT ON ev_charging_db.v_hoa_don_chi_tiet    TO 'role_admin';
GRANT SELECT ON ev_charging_db.v_doanh_thu_tram      TO 'role_admin';

-- E2. Station Operator: chi doc view theo tram duoc giao; xu ly dat cho cua tram minh
GRANT SELECT ON ev_charging_db.v_op_cong_sac          TO 'role_operator';
GRANT SELECT ON ev_charging_db.v_op_dat_cho           TO 'role_operator';
GRANT SELECT ON ev_charging_db.v_op_hoa_don           TO 'role_operator';
GRANT UPDATE (TrangThaiDat) ON ev_charging_db.v_op_dat_cho TO 'role_operator';
GRANT SELECT ON ev_charging_db.BANG_GIA               TO 'role_operator';  -- chi doc bieu gia

-- E3. Customer: thong tin cong khai + du lieu cua chinh minh + thu tuc dat/huy
GRANT SELECT ON ev_charging_db.TRAM_SAC               TO 'role_customer';
GRANT SELECT ON ev_charging_db.BANG_GIA               TO 'role_customer';
GRANT SELECT ON ev_charging_db.v_cong_dang_trong      TO 'role_customer';  -- O1
GRANT SELECT ON ev_charging_db.v_kh_phuong_tien       TO 'role_customer';
GRANT SELECT ON ev_charging_db.v_kh_dat_cho           TO 'role_customer';
GRANT SELECT ON ev_charging_db.v_kh_hoa_don           TO 'role_customer';
GRANT EXECUTE ON PROCEDURE ev_charging_db.sp_khach_dat_cho     TO 'role_customer';
GRANT EXECUTE ON PROCEDURE ev_charging_db.sp_khach_huy_dat_cho TO 'role_customer';

-- (F) Tai khoan demo ---------------------------------------------------------
CREATE USER IF NOT EXISTS 'admin_demo'@'localhost' IDENTIFIED BY 'Admin#2026';
CREATE USER IF NOT EXISTS 'op_1'@'localhost'       IDENTIFIED BY 'Oper#2026';
CREATE USER IF NOT EXISTS 'op_2-3'@'localhost'     IDENTIFIED BY 'Oper#2026';
CREATE USER IF NOT EXISTS 'kh_1'@'localhost'       IDENTIFIED BY 'Cust#2026';
CREATE USER IF NOT EXISTS 'kh_2'@'localhost'       IDENTIFIED BY 'Cust#2026';
CREATE USER IF NOT EXISTS 'kh_3'@'localhost'       IDENTIFIED BY 'Cust#2026';
GRANT 'role_admin'    TO 'admin_demo'@'localhost';
GRANT 'role_operator' TO 'op_1'@'localhost', 'op_2-3'@'localhost';
GRANT 'role_customer' TO 'kh_1'@'localhost', 'kh_2'@'localhost', 'kh_3'@'localhost';
SET DEFAULT ROLE ALL TO 'admin_demo'@'localhost', 'op_1'@'localhost', 'op_2-3'@'localhost',
                        'kh_1'@'localhost', 'kh_2'@'localhost', 'kh_3'@'localhost';

-- (G) Va loi ghi vet: CURRENT_USER() trong trigger tra ve DEFINER cua trigger (root),
--     khong phai nguoi dang thao tac -> doi sang USER() de ghi dung tai khoan (BR25, BR28).
DROP TRIGGER IF EXISTS trg_bang_gia_ai;
DROP TRIGGER IF EXISTS trg_bang_gia_au;
DROP TRIGGER IF EXISTS trg_cong_sac_au;
DELIMITER $$
CREATE TRIGGER trg_bang_gia_ai
AFTER INSERT ON BANG_GIA
FOR EACH ROW
BEGIN
    INSERT INTO AUDIT_LOG (TenBang, MaBanGhi, HanhDong, GiaTriCu, GiaTriMoi, NguoiThucHien)
    VALUES ('BANG_GIA', NEW.MaBangGia, 'INSERT', NULL,
            JSON_OBJECT('MaTram', NEW.MaTram, 'LoaiNgay', NEW.LoaiNgay,
                        'KhungGioBatDau', CAST(NEW.KhungGioBatDau AS CHAR),
                        'KhungGioKetThuc', CAST(NEW.KhungGioKetThuc AS CHAR),
                        'DonGia_kWh', NEW.DonGia_kWh),
            USER());
END$$

CREATE TRIGGER trg_bang_gia_au
AFTER UPDATE ON BANG_GIA
FOR EACH ROW
BEGIN
    INSERT INTO AUDIT_LOG (TenBang, MaBanGhi, HanhDong, GiaTriCu, GiaTriMoi, NguoiThucHien)
    VALUES ('BANG_GIA', NEW.MaBangGia, 'UPDATE',
            JSON_OBJECT('LoaiNgay', OLD.LoaiNgay,
                        'KhungGioBatDau', CAST(OLD.KhungGioBatDau AS CHAR),
                        'KhungGioKetThuc', CAST(OLD.KhungGioKetThuc AS CHAR),
                        'DonGia_kWh', OLD.DonGia_kWh),
            JSON_OBJECT('LoaiNgay', NEW.LoaiNgay,
                        'KhungGioBatDau', CAST(NEW.KhungGioBatDau AS CHAR),
                        'KhungGioKetThuc', CAST(NEW.KhungGioKetThuc AS CHAR),
                        'DonGia_kWh', NEW.DonGia_kWh),
            USER());
END$$

CREATE TRIGGER trg_cong_sac_au
AFTER UPDATE ON CONG_SAC
FOR EACH ROW
BEGIN
    IF NOT (OLD.TrangThai <=> NEW.TrangThai) THEN
        INSERT INTO AUDIT_LOG (TenBang, MaBanGhi, HanhDong, GiaTriCu, GiaTriMoi, NguoiThucHien)
        VALUES ('CONG_SAC', NEW.MaCong, 'UPDATE',
                JSON_OBJECT('TrangThai', OLD.TrangThai),
                JSON_OBJECT('TrangThai', NEW.TrangThai),
                USER());
    END IF;
END$$
DELIMITER ;
