/* ============================================================================
   PHASE 3 - SECTION 4.2 (part b): ADVANCED QUERIES & PERFORMANCE TEST CASES
   Run AFTER 4_1_ddl_script.sql and 4_2a_mock_data.sql.
   Part A: 13 queries (JOIN / Subquery / Aggregation / Window / CTE)
   Part B: performance tests with EXPLAIN ANALYZE (200,000-row copy of DAT_CHO)
   ============================================================================ */
USE ev_charging_db;
SET NAMES utf8mb4;

SET @now_demo      = '2026-10-21 12:00:00';   -- "hom nay" cua bo du lieu mau
SET @phi_do_xe_gio = 10000;                   -- VND/gio (gia dinh, xem 4_2a)

/* ############################################################################
   PART A - ADVANCED QUERIES
   ############################################################################ */

/* ---------------------------------------------------------------------------
   Q1 [JOIN 6 bang] Lich dat cho sap toi (Confirmed/Pending) kem khach, xe, tram, cong
   --------------------------------------------------------------------------- */
SELECT  d.MaDatCho, d.TG_DatBatDau, d.TG_DatKetThuc, d.TrangThaiDat,
        kh.HoTen, px.BienSo, t.TenTram, c.SoHieuCong, v.CongSuat_kW
FROM    DAT_CHO d
JOIN    KHACH_HANG kh         ON kh.MaKH  = d.MaKH
JOIN    PHUONG_TIEN px        ON px.MaXe  = d.MaXe
JOIN    CONG_SAC c            ON c.MaCong = d.MaCong
JOIN    TRAM_SAC t            ON t.MaTram = c.MaTram
JOIN    v_cong_sac_chi_tiet v ON v.MaCong = c.MaCong
WHERE   d.TrangThaiDat IN ('Confirmed','Pending')
  AND   d.TG_DatBatDau >= @now_demo
ORDER BY d.TG_DatBatDau
LIMIT 20;   -- co hon 140 dat cho sap toi, chi hien 20 dong dau

/* ---------------------------------------------------------------------------
   Q2 [JOIN + Aggregation] Doanh thu, san luong kWh va doanh thu/kWh theo tram, theo thang
   --------------------------------------------------------------------------- */
SELECT  t.TenTram,
        DATE_FORMAT(h.TG_ThanhToan, '%Y-%m')              AS Thang,
        COUNT(*)                                          AS SoHoaDon,
        SUM(p.DienNangTieuThu_kWh)                        AS TongKWh,
        SUM(h.TongTien)                                   AS DoanhThu,
        ROUND(SUM(h.TongTien) / SUM(p.DienNangTieuThu_kWh), 0) AS DoanhThuTrenKWh
FROM    HOA_DON h
JOIN    PHIEN_SAC p ON p.MaPhien  = h.MaPhien
JOIN    DAT_CHO d   ON d.MaDatCho = p.MaDatCho
JOIN    CONG_SAC c  ON c.MaCong   = d.MaCong
JOIN    TRAM_SAC t  ON t.MaTram   = c.MaTram
GROUP BY t.MaTram, t.TenTram, DATE_FORMAT(h.TG_ThanhToan, '%Y-%m')
ORDER BY Thang, DoanhThu DESC;

/* ---------------------------------------------------------------------------
   Q3 [Subquery trong HAVING] Khach hang co tong chi tieu cao hon muc trung binh
   --------------------------------------------------------------------------- */
SELECT  kh.MaKH, kh.HoTen, kh.LoaiKH,
        COUNT(h.MaHD)    AS SoHoaDon,
        SUM(h.TongTien)  AS TongChiTieu
FROM    KHACH_HANG kh
JOIN    DAT_CHO d   ON d.MaKH     = kh.MaKH
JOIN    PHIEN_SAC p ON p.MaDatCho = d.MaDatCho
JOIN    HOA_DON h   ON h.MaPhien  = p.MaPhien
GROUP BY kh.MaKH, kh.HoTen, kh.LoaiKH
HAVING  SUM(h.TongTien) > (
          SELECT AVG(tong_kh.TongChiTieu)
          FROM  (SELECT SUM(h2.TongTien) AS TongChiTieu
                 FROM   DAT_CHO d2
                 JOIN   PHIEN_SAC p2 ON p2.MaDatCho = d2.MaDatCho
                 JOIN   HOA_DON h2   ON h2.MaPhien  = p2.MaPhien
                 GROUP BY d2.MaKH) AS tong_kh)
ORDER BY TongChiTieu DESC
LIMIT 20;   -- top 20 trong so khach co chi tieu cao hon muc trung binh

/* ---------------------------------------------------------------------------
   Q4 [NOT EXISTS + scalar subquery] Khach hang chua tung su dung dich vu
   --------------------------------------------------------------------------- */
SELECT  kh.MaKH, kh.HoTen, kh.SDT,
        (SELECT COUNT(*) FROM PHUONG_TIEN px WHERE px.MaKH = kh.MaKH) AS SoXeDangKy
FROM    KHACH_HANG kh
WHERE   NOT EXISTS (SELECT 1
                    FROM   DAT_CHO d
                    WHERE  d.MaKH = kh.MaKH
                      AND  d.TrangThaiDat IN ('Active','Completed'))
ORDER BY kh.MaKH
LIMIT 20;   -- du lieu mau co hon 1.100 khach, chi hien 20 dong dau

/* ---------------------------------------------------------------------------
   Q5 [Conditional aggregation] Ty le huy / no-show cua tung khach hang (BR11, BR12)
   --------------------------------------------------------------------------- */
SELECT  kh.MaKH, kh.HoTen,
        COUNT(*)                                                    AS TongDatCho,
        SUM(CASE WHEN d.TrangThaiDat = 'Cancelled' THEN 1 ELSE 0 END) AS SoLanHuy,
        SUM(CASE WHEN d.TrangThaiDat = 'No-show'   THEN 1 ELSE 0 END) AS SoLanNoShow,
        ROUND(100 * SUM(d.TrangThaiDat IN ('Cancelled','No-show')) / COUNT(*), 1) AS TyLeKhongSuDung_Pct
FROM    KHACH_HANG kh
JOIN    DAT_CHO d ON d.MaKH = kh.MaKH
GROUP BY kh.MaKH, kh.HoTen
HAVING  SUM(d.TrangThaiDat IN ('Cancelled','No-show')) > 0
ORDER BY TyLeKhongSuDung_Pct DESC, SoLanHuy + SoLanNoShow DESC
LIMIT 20;   -- top 20 khach co ty le huy/no-show cao nhat

/* ---------------------------------------------------------------------------
   Q6 [Window RANK] Cong sac cong suat lon nhat cua moi tram (gom ca dong hang)
   --------------------------------------------------------------------------- */
SELECT  x.TenTram, x.SoHieuCong, x.NhomCong, x.LoaiDauSac, x.CongSuat_kW
FROM   (SELECT v.*,
               RANK() OVER (PARTITION BY v.MaTram ORDER BY v.CongSuat_kW DESC) AS Hang
        FROM   v_cong_sac_chi_tiet v) x
WHERE   x.Hang = 1
ORDER BY x.TenTram, x.SoHieuCong;

/* ---------------------------------------------------------------------------
   Q7 [CTE + JOIN theo khoang thoi gian] Tinh hoa don khi phien sac keo dai qua
   nhieu khung gia (BR19, BR20) va doi chieu voi HOA_DON.TongTien da luu
   --------------------------------------------------------------------------- */
WITH phien AS (
    SELECT  p.MaPhien, p.TG_BatDauTT AS s, p.TG_KetThucTT AS e,
            p.DienNangTieuThu_kWh AS kwh, c.MaTram,
            CASE WHEN DATE(p.TG_BatDauTT) IN ('2026-09-02') THEN 'LeTet'
                 WHEN DAYOFWEEK(p.TG_BatDauTT) IN (1,7)     THEN 'CuoiTuan'
                 ELSE 'Thuong' END AS LoaiNgay
    FROM    PHIEN_SAC p
    JOIN    DAT_CHO d  ON d.MaDatCho = p.MaDatCho
    JOIN    CONG_SAC c ON c.MaCong   = d.MaCong
    WHERE   p.TG_KetThucTT IS NOT NULL
),
theo_khung AS (
    SELECT  ph.*, w.MaBangGia, w.DonGia_kWh,
            GREATEST(0, TIMESTAMPDIFF(SECOND,
                GREATEST(ph.s, TIMESTAMP(DATE(ph.s), w.KhungGioBatDau)),
                LEAST(ph.e,    TIMESTAMP(DATE(ph.s), w.KhungGioKetThuc)))) AS GiayTrongKhung
    FROM    phien ph
    JOIN    BANG_GIA w ON w.MaTram = ph.MaTram AND w.LoaiNgay = ph.LoaiNgay
)
SELECT  tk.MaPhien,
        TIMESTAMPDIFF(MINUTE, tk.s, tk.e)                          AS ThoiLuong_Phut,
        tk.kwh                                                     AS kWh,
        SUM(tk.GiayTrongKhung > 0)                                 AS SoKhungGiaApDung,
        ROUND(tk.kwh * SUM(tk.GiayTrongKhung * tk.DonGia_kWh)
                     / TIMESTAMPDIFF(SECOND, tk.s, tk.e), 0)       AS TienDien,
        ROUND(TIMESTAMPDIFF(SECOND, tk.s, tk.e) / 3600 * @phi_do_xe_gio, 0) AS PhiDoXe,
        ROUND(tk.kwh * SUM(tk.GiayTrongKhung * tk.DonGia_kWh)
                     / TIMESTAMPDIFF(SECOND, tk.s, tk.e)
            + TIMESTAMPDIFF(SECOND, tk.s, tk.e) / 3600 * @phi_do_xe_gio, 0) AS TongTien_Tinh,
        h.TongTien                                                 AS TongTien_DaLuu
FROM    theo_khung tk
JOIN    HOA_DON h ON h.MaPhien = tk.MaPhien
GROUP BY tk.MaPhien, tk.s, tk.e, tk.kwh, h.TongTien
ORDER BY SoKhungGiaApDung DESC, tk.MaPhien
LIMIT 20;   -- co ~3.000 hoa don; uu tien hien cac phien qua nhieu khung gia

/* ---------------------------------------------------------------------------
   Q8 [Self-join] Kiem toan BR10: tim cac cap dat cho chong khoang thoi gian tren cung cong
   (ky vong: 0 dong - trigger trg_dat_cho_bi/bu da chan)
   --------------------------------------------------------------------------- */
SELECT  a.MaCong,
        a.MaDatCho AS DatCho_A, a.TG_DatBatDau AS A_Bat, a.TG_DatKetThuc AS A_Ket,
        b.MaDatCho AS DatCho_B, b.TG_DatBatDau AS B_Bat, b.TG_DatKetThuc AS B_Ket
FROM    DAT_CHO a
JOIN    DAT_CHO b ON  b.MaCong   = a.MaCong
                  AND b.MaDatCho > a.MaDatCho
                  AND a.TG_DatBatDau < b.TG_DatKetThuc
                  AND a.TG_DatKetThuc > b.TG_DatBatDau
WHERE   a.TrangThaiDat IN ('Pending','Confirmed','Active')
  AND   b.TrangThaiDat IN ('Pending','Confirmed','Active');

/* ---------------------------------------------------------------------------
   Q9 [View + Aggregation] Hieu suat su dung cong: thoi gian sac thuc te / thoi gian da dat
   --------------------------------------------------------------------------- */
SELECT  c.TenTram, c.SoHieuCong, c.NhomCong,
        COUNT(*)                                   AS SoPhien,
        SUM(v.ThoiLuongSacThucTe_Phut)             AS PhutSacThucTe,
        SUM(v.ThoiLuongDatCho_Phut)                AS PhutDaDat,
        ROUND(100 * SUM(v.ThoiLuongSacThucTe_Phut)
                  / SUM(v.ThoiLuongDatCho_Phut), 1) AS HieuSuat_Pct
FROM    v_phien_sac_thoi_luong v
JOIN    v_cong_sac_chi_tiet c ON c.MaCong = v.MaCong
WHERE   v.TG_KetThucTT IS NOT NULL
GROUP BY c.MaCong, c.TenTram, c.SoHieuCong, c.NhomCong
ORDER BY HieuSuat_Pct DESC;

/* ---------------------------------------------------------------------------
   Q10 [Subquery in FROM + Window SUM OVER] Phan bo phien sac theo gio bat dau, danh dau cao diem
   --------------------------------------------------------------------------- */
SELECT  g.Gio,
        CASE WHEN g.Gio BETWEEN 17 AND 21 THEN 'Cao diem'
             WHEN g.Gio >= 22 OR g.Gio < 6 THEN 'Thap diem'
             ELSE 'Binh thuong' END                         AS NhomGio,
        g.SoPhien,
        ROUND(g.TongKWh, 1)                                  AS TongKWh,
        ROUND(100 * g.SoPhien / SUM(g.SoPhien) OVER (), 1)   AS TyLe_Pct
FROM   (SELECT HOUR(TG_BatDauTT) AS Gio,
               COUNT(*)          AS SoPhien,
               SUM(DienNangTieuThu_kWh) AS TongKWh
        FROM   PHIEN_SAC
        GROUP BY HOUR(TG_BatDauTT)) g
ORDER BY g.Gio;

/* ---------------------------------------------------------------------------
   Q11 [CTE + ROW_NUMBER] Top 2 khach hang dung nhieu dien nhat o moi tram
   --------------------------------------------------------------------------- */
WITH tong AS (
    SELECT  t.MaTram, t.TenTram, kh.MaKH, kh.HoTen,
            SUM(p.DienNangTieuThu_kWh) AS TongKWh
    FROM    PHIEN_SAC p
    JOIN    DAT_CHO d     ON d.MaDatCho = p.MaDatCho
    JOIN    KHACH_HANG kh ON kh.MaKH    = d.MaKH
    JOIN    CONG_SAC c    ON c.MaCong   = d.MaCong
    JOIN    TRAM_SAC t    ON t.MaTram   = c.MaTram
    GROUP BY t.MaTram, t.TenTram, kh.MaKH, kh.HoTen
),
xep_hang AS (
    SELECT  tong.*, ROW_NUMBER() OVER (PARTITION BY MaTram ORDER BY TongKWh DESC) AS Hang
    FROM    tong
)
SELECT  TenTram, Hang, HoTen, TongKWh
FROM    xep_hang
WHERE   Hang <= 2
ORDER BY MaTram, Hang;

/* ---------------------------------------------------------------------------
   Q12 [Correlated subquery] Phien dang chay + dat cho ke tiep tren cung cong
   (canh bao nguy co xung dot - BR14)
   --------------------------------------------------------------------------- */
SELECT  p.MaPhien, kh.HoTen, px.BienSo, t.TenTram, c.SoHieuCong,
        p.TG_BatDauTT,
        TIMESTAMPDIFF(MINUTE, p.TG_BatDauTT, @now_demo) AS PhutDaSac,
        d.TG_DatKetThuc                                  AS DuKienKetThuc,
        CASE WHEN @now_demo > d.TG_DatKetThuc
             THEN 'Qua gio dat cho' ELSE 'Dung gio' END  AS TinhTrang,
        (SELECT MIN(n.TG_DatBatDau)
         FROM   DAT_CHO n
         WHERE  n.MaCong    = d.MaCong
           AND  n.MaDatCho <> d.MaDatCho
           AND  n.TrangThaiDat IN ('Pending','Confirmed')
           AND  n.TG_DatKetThuc > @now_demo)             AS DatChoKeTiep   -- co the < gio hien tai: khach ke tiep dang cho
FROM    PHIEN_SAC p
JOIN    DAT_CHO d      ON d.MaDatCho = p.MaDatCho
JOIN    KHACH_HANG kh  ON kh.MaKH    = d.MaKH
JOIN    PHUONG_TIEN px ON px.MaXe    = d.MaXe
JOIN    CONG_SAC c     ON c.MaCong   = d.MaCong
JOIN    TRAM_SAC t     ON t.MaTram   = c.MaTram
WHERE   p.TG_KetThucTT IS NULL;

/* ---------------------------------------------------------------------------
   Q13 [NOT EXISTS + View] Goi y cong thay the con trong cung tram cho mot khung gio (BR14)
   --------------------------------------------------------------------------- */
SET @tram = 1;
SET @tu   = '2026-10-22 08:00:00';
SET @den  = '2026-10-22 10:00:00';

SELECT  v.MaCong, v.SoHieuCong, v.NhomCong, v.LoaiDauSac, v.CongSuat_kW
FROM    v_cong_sac_chi_tiet v
WHERE   v.MaTram = @tram
  AND   v.TrangThai NOT IN ('Maintenance','Inactive')
  AND   NOT EXISTS (SELECT 1
                    FROM   DAT_CHO d
                    WHERE  d.MaCong = v.MaCong
                      AND  d.TrangThaiDat IN ('Pending','Confirmed','Active')
                      AND  @tu  < d.TG_DatKetThuc
                      AND  @den > d.TG_DatBatDau)
ORDER BY v.CongSuat_kW DESC;


/* ############################################################################
   PART B - PERFORMANCE TEST CASES
   Bang DAT_CHO_PERF = ban sao cua DAT_CHO (giu nguyen index, khong co FK/trigger)
   nap 200.000 dong bang recursive CTE. So sanh EXPLAIN ANALYZE co / khong dung index.
   ############################################################################ */

DROP TABLE IF EXISTS DAT_CHO_PERF;
CREATE TABLE DAT_CHO_PERF LIKE DAT_CHO;

SET SESSION cte_max_recursion_depth = 200000;

INSERT INTO DAT_CHO_PERF (TG_DatBatDau, TG_DatKetThuc, TrangThaiDat, MaKH, MaXe, MaCong)
WITH RECURSIVE seq AS (
    SELECT 1 AS n
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 200000
)
SELECT  DATE_ADD('2025-01-01 00:00:00', INTERVAL n * 5 MINUTE),
        DATE_ADD('2025-01-01 01:00:00', INTERVAL n * 5 MINUTE),
        ELT(1 + MOD(n, 6), 'Pending','Confirmed','Active','Completed','Cancelled','No-show'),
        1 + MOD(n, 8),
        1 + MOD(n, 10),
        1 + MOD(n, 12)
FROM    seq;

ANALYZE TABLE DAT_CHO_PERF;
SELECT COUNT(*) AS SoDong FROM DAT_CHO_PERF;

/* --- P1: kiem tra trung lich tren 1 cong (truy van cua trigger BR10) ---------
   Index ky vong: idx_dat_cho_cong_thoigian (MaCong, TG_DatBatDau, TG_DatKetThuc) */
-- P1a: CO index
EXPLAIN ANALYZE
SELECT COUNT(*) FROM DAT_CHO_PERF d
WHERE  d.MaCong = 5
  AND  d.TrangThaiDat IN ('Pending','Confirmed','Active')
  AND  '2025-06-01 10:00:00' > d.TG_DatBatDau
  AND  '2025-06-01 08:00:00' < d.TG_DatKetThuc
  AND  d.TG_DatBatDau >= '2025-05-31 00:00:00';

-- P1b: KHONG dung index (ep full scan)
EXPLAIN ANALYZE
SELECT COUNT(*) FROM DAT_CHO_PERF d IGNORE INDEX (idx_dat_cho_cong_thoigian)
WHERE  d.MaCong = 5
  AND  d.TrangThaiDat IN ('Pending','Confirmed','Active')
  AND  '2025-06-01 10:00:00' > d.TG_DatBatDau
  AND  '2025-06-01 08:00:00' < d.TG_DatKetThuc
  AND  d.TG_DatBatDau >= '2025-05-31 00:00:00';

/* --- P2: lich su dat cho cua mot khach hang, sap xep theo thoi gian ----------
   Index ky vong: idx_dat_cho_kh_thoigian (MaKH, TG_DatBatDau) - tranh filesort  */
-- P2a: CO index
EXPLAIN ANALYZE
SELECT MaDatCho, TG_DatBatDau, TG_DatKetThuc, TrangThaiDat
FROM   DAT_CHO_PERF
WHERE  MaKH = 3 AND TG_DatBatDau >= '2026-01-01'
ORDER BY TG_DatBatDau
LIMIT 20;

-- P2b: KHONG dung index
EXPLAIN ANALYZE
SELECT MaDatCho, TG_DatBatDau, TG_DatKetThuc, TrangThaiDat
FROM   DAT_CHO_PERF IGNORE INDEX (idx_dat_cho_kh_thoigian)
WHERE  MaKH = 3 AND TG_DatBatDau >= '2026-01-01'
ORDER BY TG_DatBatDau
LIMIT 20;

/* --- P3: loc theo trang thai - cot co do chon loc THAP (chi 6 gia tri) --------
   Cot co do chon loc thap (6 gia tri) nhung COUNT(*) chi can dem trong index
   (covering/index-only scan) nen index van giam chi phi. Neu SELECT * thi
   trinh toi uu co the bo qua index - nen so sanh them neu can.                */
EXPLAIN ANALYZE
SELECT COUNT(*) FROM DAT_CHO_PERF WHERE TrangThaiDat = 'Confirmed';

EXPLAIN ANALYZE
SELECT COUNT(*) FROM DAT_CHO_PERF IGNORE INDEX (idx_dat_cho_trangthai)
WHERE TrangThaiDat = 'Confirmed';

/* --- P4: truy van Q13 tren du lieu that (kiem tra khong full scan DAT_CHO) --- */
EXPLAIN ANALYZE
SELECT v.MaCong
FROM   v_cong_sac_chi_tiet v
WHERE  v.MaTram = @tram
  AND  v.TrangThai NOT IN ('Maintenance','Inactive')
  AND  NOT EXISTS (SELECT 1 FROM DAT_CHO d
                   WHERE d.MaCong = v.MaCong
                     AND d.TrangThaiDat IN ('Pending','Confirmed','Active')
                     AND @tu < d.TG_DatKetThuc AND @den > d.TG_DatBatDau);

/* --- Don dep -------------------------------------------------------------- */
DROP TABLE IF EXISTS DAT_CHO_PERF;

/* ----------------------------------------------------------------------------
   MAU BANG KET QUA DE DIEN VAO BAO CAO (lay tu EXPLAIN ANALYZE tren may nhom)
   | Test | Cau hinh        | Access type / Index dung        | Dong quet | Thoi gian (ms) |
   | P1a  | Co index        | range / idx_dat_cho_cong_thoigian |           |                |
   | P1b  | IGNORE INDEX    | ALL (full table scan)           | 200000    |                |
   | P2a  | Co index        | ref+range / idx_dat_cho_kh_thoigian |         |                |
   | P2b  | IGNORE INDEX    | ALL + filesort                  | 200000    |                |
   | P3a/b| trangthai       | (do chon loc thap)              |           |                |
   ---------------------------------------------------------------------------- */
