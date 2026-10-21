/* ============================================================================
   PHASE 3 - SECTION 4.1: DDL SCRIPT
   Project : #1 Smart City EV Charging & Parking Management (Group 11)
   DBMS    : MySQL 8.0.16+ (CHECK constraints are enforced from 8.0.16)
   Content : 1) Database  2) Tables  3) Indexes  4) Views  5) Triggers
   Style   : UPPERCASE keywords, UPPER_SNAKE_CASE tables, PascalCase columns,
             constraint prefixes pk_/fk_/uq_/ck_, indexes idx_, views v_,
             triggers trg_<table>_<b|a><i|u|d>.
   ============================================================================ */

DROP DATABASE IF EXISTS ev_charging_db;
CREATE DATABASE ev_charging_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE ev_charging_db;

/* ============================================================================
   1. TABLES (order: parent tables first)
   ============================================================================ */

-- 1.1 KHACH_HANG ------------------------------------------------------------
CREATE TABLE KHACH_HANG (
  MaKH    INT           NOT NULL AUTO_INCREMENT,
  HoTen   VARCHAR(100)  NOT NULL,
  SDT     VARCHAR(15)   NOT NULL,
  Email   VARCHAR(100)  NULL,
  DiaChi  VARCHAR(255)  NULL,
  LoaiKH  ENUM('CaNhan','DoanhNghiep') NOT NULL DEFAULT 'CaNhan',
  CONSTRAINT pk_khach_hang PRIMARY KEY (MaKH),
  CONSTRAINT uq_khach_hang_sdt   UNIQUE (SDT),
  CONSTRAINT uq_khach_hang_email UNIQUE (Email)
) ENGINE = InnoDB;

-- 1.2 PHUONG_TIEN -----------------------------------------------------------
CREATE TABLE PHUONG_TIEN (
  MaXe              INT           NOT NULL AUTO_INCREMENT,
  BienSo            VARCHAR(15)   NOT NULL,
  HangXe            VARCHAR(50)   NULL,
  DungLuongPin_kWh  DECIMAL(6,2)  NULL,
  MaKH              INT           NOT NULL,
  CONSTRAINT pk_phuong_tien PRIMARY KEY (MaXe),
  CONSTRAINT uq_phuong_tien_bienso UNIQUE (BienSo),
  CONSTRAINT ck_phuong_tien_pin CHECK (DungLuongPin_kWh > 0),
  CONSTRAINT fk_phuong_tien_khach_hang FOREIGN KEY (MaKH)
    REFERENCES KHACH_HANG (MaKH) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.3 TRAM_SAC --------------------------------------------------------------
CREATE TABLE TRAM_SAC (
  MaTram      INT           NOT NULL AUTO_INCREMENT,
  TenTram     VARCHAR(100)  NOT NULL,
  DiaChi      VARCHAR(255)  NOT NULL,
  KinhDo      DECIMAL(9,6)  NULL,
  ViDo        DECIMAL(9,6)  NULL,
  TongSoCong  SMALLINT      NOT NULL DEFAULT 0,
  CONSTRAINT pk_tram_sac PRIMARY KEY (MaTram),
  CONSTRAINT ck_tram_sac_tongsocong CHECK (TongSoCong >= 0),
  CONSTRAINT ck_tram_sac_kinhdo CHECK (KinhDo BETWEEN -180 AND 180),
  CONSTRAINT ck_tram_sac_vido   CHECK (ViDo   BETWEEN  -90 AND  90)
) ENGINE = InnoDB;

-- 1.4 CONG_SAC (superclass of the EER specialization) -----------------------
CREATE TABLE CONG_SAC (
  MaCong      INT          NOT NULL AUTO_INCREMENT,
  SoHieuCong  VARCHAR(10)  NOT NULL,
  LoaiDauSac  ENUM('AC','CCS1','CCS2','CHAdeMO') NOT NULL,
  TrangThai   ENUM('Available','Occupied','Maintenance','Inactive')
              NOT NULL DEFAULT 'Available',
  MaTram      INT          NOT NULL,
  CONSTRAINT pk_cong_sac PRIMARY KEY (MaCong),
  CONSTRAINT uq_cong_sac_tram_sohieu UNIQUE (MaTram, SoHieuCong),
  CONSTRAINT fk_cong_sac_tram_sac FOREIGN KEY (MaTram)
    REFERENCES TRAM_SAC (MaTram) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.5 CONG_AC (subclass) ----------------------------------------------------
CREATE TABLE CONG_AC (
  MaCong       INT           NOT NULL,
  CongSuat_kW  DECIMAL(6,2)  NOT NULL,
  CONSTRAINT pk_cong_ac PRIMARY KEY (MaCong),
  CONSTRAINT ck_cong_ac_congsuat CHECK (CongSuat_kW > 0),
  CONSTRAINT fk_cong_ac_cong_sac FOREIGN KEY (MaCong)
    REFERENCES CONG_SAC (MaCong) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.6 CONG_DC_NHANH (subclass) ----------------------------------------------
CREATE TABLE CONG_DC_NHANH (
  MaCong       INT           NOT NULL,
  CongSuat_kW  DECIMAL(6,2)  NOT NULL,
  ChuanKetNoi  ENUM('CCS1','CCS2','CHAdeMO') NOT NULL,
  CONSTRAINT pk_cong_dc_nhanh PRIMARY KEY (MaCong),
  CONSTRAINT ck_cong_dc_congsuat CHECK (CongSuat_kW > 0),
  CONSTRAINT fk_cong_dc_cong_sac FOREIGN KEY (MaCong)
    REFERENCES CONG_SAC (MaCong) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.7 BANG_GIA --------------------------------------------------------------
CREATE TABLE BANG_GIA (
  MaBangGia        INT            NOT NULL AUTO_INCREMENT,
  KhungGioBatDau   TIME           NOT NULL,
  KhungGioKetThuc  TIME           NOT NULL,
  LoaiNgay         ENUM('Thuong','CuoiTuan','LeTet') NOT NULL,
  DonGia_kWh       DECIMAL(10,2)  NOT NULL,
  MaTram           INT            NOT NULL,
  CONSTRAINT pk_bang_gia PRIMARY KEY (MaBangGia),
  CONSTRAINT uq_bang_gia_tram_ngay_gio UNIQUE (MaTram, LoaiNgay, KhungGioBatDau),
  CONSTRAINT ck_bang_gia_khung_gio CHECK (KhungGioKetThuc > KhungGioBatDau),
  CONSTRAINT ck_bang_gia_dongia    CHECK (DonGia_kWh >= 0),
  CONSTRAINT fk_bang_gia_tram_sac FOREIGN KEY (MaTram)
    REFERENCES TRAM_SAC (MaTram) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.8 DAT_CHO ---------------------------------------------------------------
CREATE TABLE DAT_CHO (
  MaDatCho       BIGINT    NOT NULL AUTO_INCREMENT,
  TG_DatBatDau   DATETIME  NOT NULL,
  TG_DatKetThuc  DATETIME  NOT NULL,
  TrangThaiDat   ENUM('Pending','Confirmed','Active','Completed','Cancelled','No-show')
                 NOT NULL DEFAULT 'Pending',
  MaKH           INT       NOT NULL,
  MaXe           INT       NOT NULL,
  MaCong         INT       NOT NULL,
  CONSTRAINT pk_dat_cho PRIMARY KEY (MaDatCho),
  CONSTRAINT ck_dat_cho_thoi_gian CHECK (TG_DatKetThuc > TG_DatBatDau),   -- BR9, BR21
  CONSTRAINT fk_dat_cho_khach_hang FOREIGN KEY (MaKH)
    REFERENCES KHACH_HANG (MaKH) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_dat_cho_phuong_tien FOREIGN KEY (MaXe)
    REFERENCES PHUONG_TIEN (MaXe) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_dat_cho_cong_sac FOREIGN KEY (MaCong)
    REFERENCES CONG_SAC (MaCong) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.9 PHIEN_SAC -------------------------------------------------------------
CREATE TABLE PHIEN_SAC (
  MaPhien              BIGINT        NOT NULL AUTO_INCREMENT,
  TG_BatDauTT          DATETIME      NOT NULL,
  TG_KetThucTT         DATETIME      NULL,
  DienNangTieuThu_kWh  DECIMAL(8,3)  NOT NULL DEFAULT 0,
  MaDatCho             BIGINT        NOT NULL,
  CONSTRAINT pk_phien_sac PRIMARY KEY (MaPhien),
  CONSTRAINT uq_phien_sac_dat_cho UNIQUE (MaDatCho),                      -- quan he 1:1
  CONSTRAINT ck_phien_sac_thoi_gian CHECK (TG_KetThucTT IS NULL
                                           OR TG_KetThucTT > TG_BatDauTT), -- BR21
  CONSTRAINT ck_phien_sac_kwh CHECK (DienNangTieuThu_kWh >= 0),            -- BR17
  CONSTRAINT fk_phien_sac_dat_cho FOREIGN KEY (MaDatCho)
    REFERENCES DAT_CHO (MaDatCho) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.10 HOA_DON --------------------------------------------------------------
CREATE TABLE HOA_DON (
  MaHD          BIGINT         NOT NULL AUTO_INCREMENT,
  TongTien      DECIMAL(12,2)  NOT NULL DEFAULT 0,
  TG_ThanhToan  DATETIME       NULL,
  PhuongThucTT  ENUM('TienMat','TheNganHang','ViDienTu') NULL,
  MaPhien       BIGINT         NOT NULL,
  MaBangGia     INT            NOT NULL,
  CONSTRAINT pk_hoa_don PRIMARY KEY (MaHD),
  CONSTRAINT uq_hoa_don_phien UNIQUE (MaPhien),                           -- quan he 1:1
  CONSTRAINT ck_hoa_don_tongtien CHECK (TongTien >= 0),
  CONSTRAINT fk_hoa_don_phien_sac FOREIGN KEY (MaPhien)
    REFERENCES PHIEN_SAC (MaPhien) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_hoa_don_bang_gia FOREIGN KEY (MaBangGia)
    REFERENCES BANG_GIA (MaBangGia) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE = InnoDB;

-- 1.11 AUDIT_LOG (BR25, BR28: luu vet thay doi bang gia / trang thai cong) ---
CREATE TABLE AUDIT_LOG (
  MaLog          BIGINT        NOT NULL AUTO_INCREMENT,
  TenBang        VARCHAR(50)   NOT NULL,
  MaBanGhi       BIGINT        NOT NULL,
  HanhDong       ENUM('INSERT','UPDATE','DELETE') NOT NULL,
  GiaTriCu       JSON          NULL,
  GiaTriMoi      JSON          NULL,
  NguoiThucHien  VARCHAR(100)  NOT NULL,
  TG_ThucHien    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_audit_log PRIMARY KEY (MaLog)
) ENGINE = InnoDB;

/* ============================================================================
   2. INDEXES (FK columns without an explicit index get one automatically)
   ============================================================================ */

-- Kiem tra trung lich theo cong + khoang thoi gian (BR10) va tra cuu lich cong
CREATE INDEX idx_dat_cho_cong_thoigian ON DAT_CHO (MaCong, TG_DatBatDau, TG_DatKetThuc);
-- Lich su dat cho cua khach hang (thuoc tinh dan xuat LichSuKhungGioDat)
CREATE INDEX idx_dat_cho_kh_thoigian   ON DAT_CHO (MaKH, TG_DatBatDau);
-- Loc theo trang thai dat cho (Confirmed/Active/No-show...)
CREATE INDEX idx_dat_cho_trangthai     ON DAT_CHO (TrangThaiDat);
-- Tim cong con trong theo tram
CREATE INDEX idx_cong_sac_tram_trangthai ON CONG_SAC (MaTram, TrangThai);
-- Bao cao phien sac theo thoi gian
CREATE INDEX idx_phien_sac_batdau      ON PHIEN_SAC (TG_BatDauTT);
-- Phien dang sac (TG_KetThucTT IS NULL) - dung cho BR3
CREATE INDEX idx_phien_sac_ketthuc     ON PHIEN_SAC (TG_KetThucTT);
-- Bao cao doanh thu theo ngay thanh toan
CREATE INDEX idx_hoa_don_thanhtoan     ON HOA_DON (TG_ThanhToan);
-- Tra cuu muc gia ap dung theo khung gio
CREATE INDEX idx_bang_gia_khung_gio    ON BANG_GIA (MaTram, LoaiNgay, KhungGioBatDau, KhungGioKetThuc);
-- Truy van nhat ky
CREATE INDEX idx_audit_log_bang_banghi ON AUDIT_LOG (TenBang, MaBanGhi);
CREATE INDEX idx_audit_log_thoigian    ON AUDIT_LOG (TG_ThucHien);

/* ============================================================================
   3. VIEWS
   ============================================================================ */

-- 3.1 Thong tin chi tiet cong sac (gop lop cha + 2 lop con cua EER)
CREATE OR REPLACE VIEW v_cong_sac_chi_tiet AS
SELECT  c.MaCong,
        t.MaTram,
        t.TenTram,
        c.SoHieuCong,
        c.LoaiDauSac,
        CASE WHEN ac.MaCong IS NOT NULL THEN 'AC' ELSE 'DC_NHANH' END AS NhomCong,
        COALESCE(ac.CongSuat_kW, dc.CongSuat_kW)                       AS CongSuat_kW,
        c.TrangThai
FROM    CONG_SAC c
JOIN    TRAM_SAC t             ON t.MaTram  = c.MaTram
LEFT JOIN CONG_AC ac           ON ac.MaCong = c.MaCong
LEFT JOIN CONG_DC_NHANH dc     ON dc.MaCong = c.MaCong;

-- 3.2 Cong dang trong ngay luc nay (muc tieu O1: tinh trang kha dung)
CREATE OR REPLACE VIEW v_cong_dang_trong AS
SELECT  v.*
FROM    v_cong_sac_chi_tiet v
WHERE   v.TrangThai = 'Available'
  AND   NOT EXISTS (
          SELECT 1
          FROM   DAT_CHO d
          WHERE  d.MaCong = v.MaCong
            AND  d.TrangThaiDat IN ('Confirmed','Active')
            AND  NOW() >= d.TG_DatBatDau
            AND  NOW() <  d.TG_DatKetThuc
        );

-- 3.3 Thoi luong dat cho / phien sac - thuoc tinh dan xuat, khong luu (BR16)
CREATE OR REPLACE VIEW v_phien_sac_thoi_luong AS
SELECT  p.MaPhien,
        d.MaDatCho,
        d.MaKH,
        d.MaXe,
        d.MaCong,
        d.TG_DatBatDau,
        d.TG_DatKetThuc,
        TIMESTAMPDIFF(MINUTE, d.TG_DatBatDau, d.TG_DatKetThuc) AS ThoiLuongDatCho_Phut,
        p.TG_BatDauTT,
        p.TG_KetThucTT,
        TIMESTAMPDIFF(MINUTE, p.TG_BatDauTT, p.TG_KetThucTT)   AS ThoiLuongSacThucTe_Phut,
        p.DienNangTieuThu_kWh
FROM    PHIEN_SAC p
JOIN    DAT_CHO d ON d.MaDatCho = p.MaDatCho;

-- 3.4 Hoa don chi tiet (khach hang, xe, tram, cong, muc gia ap dung)
CREATE OR REPLACE VIEW v_hoa_don_chi_tiet AS
SELECT  h.MaHD,
        kh.MaKH,
        kh.HoTen,
        px.BienSo,
        t.MaTram,
        t.TenTram,
        c.SoHieuCong,
        p.TG_BatDauTT,
        p.TG_KetThucTT,
        TIMESTAMPDIFF(MINUTE, p.TG_BatDauTT, p.TG_KetThucTT) AS ThoiLuongSac_Phut,
        p.DienNangTieuThu_kWh,
        bg.LoaiNgay,
        bg.KhungGioBatDau,
        bg.KhungGioKetThuc,
        bg.DonGia_kWh,
        h.TongTien,
        h.PhuongThucTT,
        h.TG_ThanhToan
FROM    HOA_DON h
JOIN    PHIEN_SAC p    ON p.MaPhien   = h.MaPhien
JOIN    DAT_CHO d      ON d.MaDatCho  = p.MaDatCho
JOIN    KHACH_HANG kh  ON kh.MaKH     = d.MaKH
JOIN    PHUONG_TIEN px ON px.MaXe     = d.MaXe
JOIN    CONG_SAC c     ON c.MaCong    = d.MaCong
JOIN    TRAM_SAC t     ON t.MaTram    = c.MaTram
JOIN    BANG_GIA bg    ON bg.MaBangGia = h.MaBangGia;

-- 3.5 Doanh thu & san luong dien theo tram (phuc vu bao cao - O5)
CREATE OR REPLACE VIEW v_doanh_thu_tram AS
SELECT  t.MaTram,
        t.TenTram,
        COUNT(h.MaHD)                         AS SoHoaDon,
        COALESCE(SUM(p.DienNangTieuThu_kWh), 0) AS TongKWh,
        COALESCE(SUM(h.TongTien), 0)          AS TongDoanhThu
FROM    TRAM_SAC t
LEFT JOIN CONG_SAC c   ON c.MaTram   = t.MaTram
LEFT JOIN DAT_CHO d    ON d.MaCong   = c.MaCong
LEFT JOIN PHIEN_SAC p  ON p.MaDatCho = d.MaDatCho
LEFT JOIN HOA_DON h    ON h.MaPhien  = p.MaPhien
GROUP BY t.MaTram, t.TenTram;

/* ============================================================================
   4. TRIGGERS
   Loi nghiep vu duoc nem bang SIGNAL SQLSTATE '45000'.
   ============================================================================ */
DELIMITER $$

/* ---------- 4.1 Chuyen biet hoa CONG_SAC: disjoint (EER 2.1.4) ------------- */

CREATE TRIGGER trg_cong_ac_bi
BEFORE INSERT ON CONG_AC
FOR EACH ROW
BEGIN
  DECLARE v_loai VARCHAR(10);

  SELECT LoaiDauSac INTO v_loai FROM CONG_SAC WHERE MaCong = NEW.MaCong;

  IF v_loai IS NULL OR v_loai <> 'AC' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[EER] CONG_AC requires parent CONG_SAC.LoaiDauSac = AC';
  END IF;

  IF EXISTS (SELECT 1 FROM CONG_DC_NHANH WHERE MaCong = NEW.MaCong) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[EER] Disjoint violated: port already exists in CONG_DC_NHANH';
  END IF;
END$$

CREATE TRIGGER trg_cong_dc_nhanh_bi
BEFORE INSERT ON CONG_DC_NHANH
FOR EACH ROW
BEGIN
  DECLARE v_loai VARCHAR(10);

  SELECT LoaiDauSac INTO v_loai FROM CONG_SAC WHERE MaCong = NEW.MaCong;

  IF v_loai IS NULL OR v_loai = 'AC' OR v_loai <> NEW.ChuanKetNoi THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[EER] CONG_DC_NHANH.ChuanKetNoi must equal parent LoaiDauSac (non-AC)';
  END IF;

  IF EXISTS (SELECT 1 FROM CONG_AC WHERE MaCong = NEW.MaCong) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[EER] Disjoint violated: port already exists in CONG_AC';
  END IF;
END$$

/* ---------- 4.2 BANG_GIA: chong chong lan khung gio (BR18, BR23) ----------- */

CREATE TRIGGER trg_bang_gia_bi
BEFORE INSERT ON BANG_GIA
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1
             FROM   BANG_GIA g
             WHERE  g.MaTram   = NEW.MaTram
               AND  g.LoaiNgay = NEW.LoaiNgay
               AND  NEW.KhungGioBatDau  < g.KhungGioKetThuc
               AND  NEW.KhungGioKetThuc > g.KhungGioBatDau) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[BR18] Price time window overlaps an existing rate';
  END IF;
END$$

CREATE TRIGGER trg_bang_gia_bu
BEFORE UPDATE ON BANG_GIA
FOR EACH ROW
BEGIN
  IF EXISTS (SELECT 1
             FROM   BANG_GIA g
             WHERE  g.MaBangGia <> OLD.MaBangGia
               AND  g.MaTram   = NEW.MaTram
               AND  g.LoaiNgay = NEW.LoaiNgay
               AND  NEW.KhungGioBatDau  < g.KhungGioKetThuc
               AND  NEW.KhungGioKetThuc > g.KhungGioBatDau) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[BR18] Price time window overlaps an existing rate';
  END IF;
END$$

/* ---------- 4.3 BANG_GIA: audit log (BR25, BR28) --------------------------- */

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
          CURRENT_USER());
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
          CURRENT_USER());
END$$

/* ---------- 4.4 CONG_SAC: log doi trang thai bao tri (BR27, BR28) ---------- */

CREATE TRIGGER trg_cong_sac_au
AFTER UPDATE ON CONG_SAC
FOR EACH ROW
BEGIN
  IF NOT (OLD.TrangThai <=> NEW.TrangThai) THEN
    INSERT INTO AUDIT_LOG (TenBang, MaBanGhi, HanhDong, GiaTriCu, GiaTriMoi, NguoiThucHien)
    VALUES ('CONG_SAC', NEW.MaCong, 'UPDATE',
            JSON_OBJECT('TrangThai', OLD.TrangThai),
            JSON_OBJECT('TrangThai', NEW.TrangThai),
            CURRENT_USER());
  END IF;
END$$

/* ---------- 4.5 DAT_CHO: chong trung lich + chan cong bao tri -------------- */
/* BR7/BR8 xe phai thuoc khach; BR10 khong chong khoang thoi gian; BR27 cong
   Maintenance/Inactive khong nhan dat cho moi.                                 */

CREATE TRIGGER trg_dat_cho_bi
BEFORE INSERT ON DAT_CHO
FOR EACH ROW
BEGIN
  DECLARE v_trangthai_cong VARCHAR(20);

  IF NOT EXISTS (SELECT 1 FROM PHUONG_TIEN WHERE MaXe = NEW.MaXe AND MaKH = NEW.MaKH) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[BR7/BR8] Vehicle is not registered under this customer';
  END IF;

  SELECT TrangThai INTO v_trangthai_cong FROM CONG_SAC WHERE MaCong = NEW.MaCong;

  IF NEW.TrangThaiDat IN ('Pending','Confirmed','Active') THEN
    IF v_trangthai_cong IN ('Maintenance','Inactive') THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '[BR27] Port is under maintenance/inactive - booking blocked';
    END IF;

    IF EXISTS (SELECT 1
               FROM   DAT_CHO d
               WHERE  d.MaCong = NEW.MaCong
                 AND  d.TrangThaiDat IN ('Pending','Confirmed','Active')
                 AND  NEW.TG_DatBatDau  < d.TG_DatKetThuc
                 AND  NEW.TG_DatKetThuc > d.TG_DatBatDau) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '[BR10] Reservation overlaps an existing one on the same port';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_dat_cho_bu
BEFORE UPDATE ON DAT_CHO
FOR EACH ROW
BEGIN
  DECLARE v_trangthai_cong VARCHAR(20);

  IF NEW.TrangThaiDat IN ('Pending','Confirmed','Active') THEN
    -- BR27 chi kiem tra khi doi cong hoac doi gio (tranh chan viec dong lich cu)
    IF NOT (OLD.MaCong <=> NEW.MaCong) THEN
      SELECT TrangThai INTO v_trangthai_cong FROM CONG_SAC WHERE MaCong = NEW.MaCong;
      IF v_trangthai_cong IN ('Maintenance','Inactive') THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = '[BR27] Port is under maintenance/inactive - booking blocked';
      END IF;
    END IF;

    IF EXISTS (SELECT 1
               FROM   DAT_CHO d
               WHERE  d.MaDatCho <> OLD.MaDatCho
                 AND  d.MaCong = NEW.MaCong
                 AND  d.TrangThaiDat IN ('Pending','Confirmed','Active')
                 AND  NEW.TG_DatBatDau  < d.TG_DatKetThuc
                 AND  NEW.TG_DatKetThuc > d.TG_DatBatDau) THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = '[BR10] Reservation overlaps an existing one on the same port';
    END IF;
  END IF;
END$$

/* ---------- 4.6 PHIEN_SAC: vong doi dat cho -> phien sac (BR3, BR15) ------- */

CREATE TRIGGER trg_phien_sac_bi
BEFORE INSERT ON PHIEN_SAC
FOR EACH ROW
BEGIN
  DECLARE v_trangthai VARCHAR(20);
  DECLARE v_cong      INT;

  SELECT TrangThaiDat, MaCong INTO v_trangthai, v_cong
  FROM   DAT_CHO WHERE MaDatCho = NEW.MaDatCho;

  IF v_trangthai IS NULL OR v_trangthai <> 'Confirmed' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[BR15] Charging session requires a Confirmed reservation';
  END IF;

  -- BR3: mot cong tai mot thoi diem chi phuc vu mot phien dang chay
  IF NEW.TG_KetThucTT IS NULL AND EXISTS (
       SELECT 1
       FROM   PHIEN_SAC p
       JOIN   DAT_CHO d ON d.MaDatCho = p.MaDatCho
       WHERE  d.MaCong = v_cong
         AND  p.TG_KetThucTT IS NULL) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = '[BR3] Port already has a running charging session';
  END IF;
END$$

CREATE TRIGGER trg_phien_sac_ai
AFTER INSERT ON PHIEN_SAC
FOR EACH ROW
BEGIN
  DECLARE v_cong INT;

  SELECT MaCong INTO v_cong FROM DAT_CHO WHERE MaDatCho = NEW.MaDatCho;

  IF NEW.TG_KetThucTT IS NULL THEN
    -- phien dang chay: dat cho -> Active, cong -> Occupied
    UPDATE DAT_CHO SET TrangThaiDat = 'Active'    WHERE MaDatCho = NEW.MaDatCho;
    UPDATE CONG_SAC SET TrangThai   = 'Occupied'
    WHERE  MaCong = v_cong AND TrangThai = 'Available';
  ELSE
    -- phien da ket thuc san (nap du lieu lich su): dat cho -> Completed
    UPDATE DAT_CHO SET TrangThaiDat = 'Completed' WHERE MaDatCho = NEW.MaDatCho;
  END IF;
END$$

CREATE TRIGGER trg_phien_sac_au
AFTER UPDATE ON PHIEN_SAC
FOR EACH ROW
BEGIN
  DECLARE v_cong INT;

  IF OLD.TG_KetThucTT IS NULL AND NEW.TG_KetThucTT IS NOT NULL THEN
    SELECT MaCong INTO v_cong FROM DAT_CHO WHERE MaDatCho = NEW.MaDatCho;

    UPDATE DAT_CHO  SET TrangThaiDat = 'Completed' WHERE MaDatCho = NEW.MaDatCho;
    UPDATE CONG_SAC SET TrangThai    = 'Available'
    WHERE  MaCong = v_cong AND TrangThai = 'Occupied';   -- khong de len Maintenance
  END IF;
END$$

/* ---------- 4.7 Cam xoa cung du lieu lich su (BR28) ------------------------ */

CREATE TRIGGER trg_dat_cho_bd BEFORE DELETE ON DAT_CHO FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = '[BR28] Hard delete is not allowed - set TrangThaiDat = Cancelled instead';
END$$

CREATE TRIGGER trg_phien_sac_bd BEFORE DELETE ON PHIEN_SAC FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = '[BR28] Hard delete of charging sessions is not allowed';
END$$

CREATE TRIGGER trg_hoa_don_bd BEFORE DELETE ON HOA_DON FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = '[BR28] Hard delete of invoices is not allowed';
END$$

CREATE TRIGGER trg_bang_gia_bd BEFORE DELETE ON BANG_GIA FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = '[BR28] Hard delete of rates is not allowed - keep price history';
END$$

DELIMITER ;

/* ============================================================================
   5. QUICK CHECK
   ============================================================================ */
-- SHOW FULL TABLES IN ev_charging_db;             -- 11 tables + 5 views
-- SHOW TRIGGERS  FROM ev_charging_db;             -- 16 triggers
-- SHOW INDEX     FROM DAT_CHO;
